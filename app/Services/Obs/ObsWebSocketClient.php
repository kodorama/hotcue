<?php

namespace App\Services\Obs;

use App\Services\Obs\Contracts\ObsClient;
use App\Services\Obs\Exceptions\ObsAuthFailed;
use App\Services\Obs\Exceptions\ObsNotConnected;
use App\Services\Obs\Exceptions\ObsProtocolError;
use App\Services\Obs\Exceptions\ObsRequestFailed;
use App\Services\Obs\Exceptions\ObsRequestTimeout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

use function React\Promise\Timer\timeout;

class ObsWebSocketClient implements ObsClient
{
    private ?ConnectionInterface $connection = null;
    private array $pendingRequests = [];
    private bool $identified = false;
    private ?Deferred $connectDeferred = null;
    private string $readBuffer = '';
    private bool $handshakeDone = false;
    private string $wsKey = '';

    /** @var callable|null */
    private $onDisconnectCallback = null;

    /** @var array<string, callable[]> Event type → list of listener callables */
    private array $eventListeners = [];

    private const REQUEST_TIMEOUT = 5;
    private const CONNECT_TIMEOUT = 10;

    /**
     * Register a listener for a named OBS event type.
     * Listener receives the event data array.
     * Multiple listeners per event are supported.
     *
     * @param string   $eventType e.g. 'CurrentProgramSceneChanged'
     * @param callable $listener  fn(array $eventData): void
     */
    public function onEvent(string $eventType, callable $listener): void
    {
        $this->eventListeners[$eventType][] = $listener;
    }

    /**
     * Remove all listeners for a given event type.
     */
    public function offEvent(string $eventType): void
    {
        unset($this->eventListeners[$eventType]);
    }

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly string $uri,
        private readonly ?string $password = null
    ) {
    }

    public function onDisconnect(callable $callback): void
    {
        $this->onDisconnectCallback = $callback;
    }

    public function connect(): PromiseInterface
    {
        $this->connectDeferred = new Deferred();

        // Convert ws:// / wss:// to tcp:// / tls://
        $tcpUri = $this->toTcpUri($this->uri);
        $secure  = str_starts_with($this->uri, 'wss://');

        $context = $secure ? ['tls' => ['verify_peer' => false, 'verify_peer_name' => false]] : [];
        $connector = new Connector($context, $this->loop);

        $connector->connect($tcpUri)->then(
            function (ConnectionInterface $conn) {
                $this->connection = $conn;

                $conn->on('data',  fn($data) => $this->onData($data));
                $conn->on('close', fn()       => $this->onClose());
                $conn->on('error', fn($e)     => $this->onError($e));

                $this->sendHttpUpgrade();
            },
            function (\Throwable $e) {
                Log::error('OBS WebSocket connect failed', ['error' => $e->getMessage()]);
                $d = $this->connectDeferred;
                $this->connectDeferred = null;
                $d?->reject($e);
            }
        );

        $promise = $this->connectDeferred->promise();

        return timeout($promise, self::CONNECT_TIMEOUT, $this->loop)
            ->then(null, function (\Throwable $e) {
                $this->cleanup();
                throw $e;
            });
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->identified;
    }

    public function sendRequest(string $requestType, array $requestData = []): PromiseInterface
    {
        if (!$this->isConnected()) {
            return \React\Promise\reject(new ObsNotConnected());
        }

        $requestId = Str::uuid()->toString();
        $deferred  = new Deferred();

        $this->pendingRequests[$requestId] = [
            'deferred'  => $deferred,
            'type'      => $requestType,
            'startTime' => microtime(true),
        ];

        $frame = json_encode([
            'op' => 6,
            'd'  => [
                'requestType' => $requestType,
                'requestId'   => $requestId,
                'requestData' => $requestData,
            ],
        ]);

        $this->sendWsFrame($frame);

        Log::debug('OBS request sent', ['type' => $requestType, 'id' => $requestId]);

        return timeout($deferred->promise(), self::REQUEST_TIMEOUT, $this->loop)
            ->then(null, function (\Throwable $e) use ($requestId, $requestType) {
                unset($this->pendingRequests[$requestId]);
                if ($e instanceof \React\Promise\Timer\TimeoutException) {
                    throw new ObsRequestTimeout($requestId, $requestType);
                }
                throw $e;
            });
    }

    public function close(): void
    {
        $this->connection?->close();
        $this->cleanup();
    }

    // ─── TCP / HTTP Upgrade ────────────────────────────────────────────────────

    private function toTcpUri(string $wsUri): string
    {
        $parsed = parse_url($wsUri);
        $scheme = ($parsed['scheme'] ?? 'ws') === 'wss' ? 'tls' : 'tcp';
        $host   = $parsed['host'] ?? '127.0.0.1';
        $port   = $parsed['port'] ?? (($parsed['scheme'] ?? 'ws') === 'wss' ? 443 : 80);
        return "{$scheme}://{$host}:{$port}";
    }

    private function sendHttpUpgrade(): void
    {
        $parsed = parse_url($this->uri);
        $host   = $parsed['host'] ?? '127.0.0.1';
        $port   = $parsed['port'] ?? '';
        $path   = ($parsed['path'] ?? '/') ?: '/';
        $portStr = $port ? ":{$port}" : '';

        // Generate a random WebSocket key
        $this->wsKey = base64_encode(random_bytes(16));

        $headers = implode("\r\n", [
            "GET {$path} HTTP/1.1",
            "Host: {$host}{$portStr}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$this->wsKey}",
            "Sec-WebSocket-Version: 13",
            "Origin: http://{$host}",
        ]);

        $this->connection->write("{$headers}\r\n\r\n");
    }

    private function onData(string $data): void
    {
        $this->readBuffer .= $data;

        if (!$this->handshakeDone) {
            $this->processHttpHandshake();
            return;
        }

        $this->processWsFrames();
    }

    private function processHttpHandshake(): void
    {
        // Wait for full HTTP response header
        if (!str_contains($this->readBuffer, "\r\n\r\n")) {
            return;
        }

        $pos      = strpos($this->readBuffer, "\r\n\r\n");
        $header   = substr($this->readBuffer, 0, $pos);
        $this->readBuffer = substr($this->readBuffer, $pos + 4);

        $firstLine = strtok($header, "\r\n");

        if (!str_contains($firstLine, '101')) {
            $this->connectDeferred?->reject(
                new ObsProtocolError("WebSocket upgrade failed: {$firstLine}")
            );
            $this->connectDeferred = null;
            $this->cleanup();
            return;
        }

        // Verify Sec-WebSocket-Accept
        $expectedAccept = base64_encode(
            sha1($this->wsKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );
        if (!str_contains($header, $expectedAccept)) {
            $this->connectDeferred?->reject(
                new ObsProtocolError('WebSocket accept key mismatch')
            );
            $this->connectDeferred = null;
            $this->cleanup();
            return;
        }

        $this->handshakeDone = true;
        Log::info('OBS WebSocket HTTP upgrade complete', ['uri' => $this->uri]);

        // Any remaining bytes after the headers are WS frame data
        if ($this->readBuffer !== '') {
            $this->processWsFrames();
        }
    }

    // ─── WebSocket Frame Parsing (RFC 6455) ────────────────────────────────────

    private function processWsFrames(): void
    {
        while (($frame = $this->readOneFrame()) !== null) {
            $this->dispatchFrame($frame);
        }
    }

    /**
     * Try to extract one complete WebSocket frame from the read buffer.
     * Returns the payload string, or null if not enough data yet.
     */
    private function readOneFrame(): ?string
    {
        $buf = $this->readBuffer;
        $len = strlen($buf);

        if ($len < 2) {
            return null;
        }

        $byte1   = ord($buf[0]);
        $byte2   = ord($buf[1]);
        $opcode  = $byte1 & 0x0F;
        $masked  = ($byte2 & 0x80) !== 0;
        $payLen  = $byte2 & 0x7F;

        $headerLen = 2 + ($masked ? 4 : 0);

        if ($payLen === 126) {
            $headerLen += 2;
            if ($len < $headerLen) {
                return null;
            }
            $payLen = unpack('n', substr($buf, 2, 2))[1];
            $maskOffset = 4;
        } elseif ($payLen === 127) {
            $headerLen += 8;
            if ($len < $headerLen) {
                return null;
            }
            $hi     = unpack('N', substr($buf, 2, 4))[1];
            $lo     = unpack('N', substr($buf, 6, 4))[1];
            $payLen = ($hi << 32) | $lo;
            $maskOffset = 10;
        } else {
            $maskOffset = 2;
        }

        $totalLen = $headerLen + $payLen;
        if ($len < $totalLen) {
            return null;
        }

        $payload = substr($buf, $headerLen, $payLen);

        if ($masked) {
            $maskKey = substr($buf, $maskOffset, 4);
            for ($i = 0; $i < $payLen; $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
            }
        }

        $this->readBuffer = substr($buf, $totalLen);

        // Handle control frames
        if ($opcode === 0x8) { // Close
            $this->onClose();
            return null;
        }
        if ($opcode === 0x9) { // Ping → send Pong
            $this->sendWsFrame($payload, 0xA);
            return null;
        }
        if ($opcode === 0xA) { // Pong
            return null;
        }

        return $payload;
    }

    /**
     * Send a WebSocket text frame (client → server, always masked per RFC 6455).
     */
    private function sendWsFrame(string $payload, int $opcode = 0x1): void
    {
        if (!$this->connection) {
            return;
        }

        $len     = strlen($payload);
        $mask    = random_bytes(4);
        $masked  = '';

        for ($i = 0; $i < $len; $i++) {
            $masked .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }

        $header = chr(0x80 | $opcode); // FIN + opcode

        if ($len < 126) {
            $header .= chr(0x80 | $len); // MASK bit set
        } elseif ($len < 65536) {
            $header .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $header .= chr(0x80 | 127) . pack('N', 0) . pack('N', $len);
        }

        $this->connection->write($header . $mask . $masked);
    }

    // ─── OBS Protocol ─────────────────────────────────────────────────────────

    private function dispatchFrame(string $payload): void
    {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('OBS invalid JSON', ['error' => $e->getMessage()]);
            return;
        }

        $op = $data['op'] ?? null;

        match ((int) $op) {
            0 => $this->handleHello($data['d'] ?? []),
            2 => $this->handleIdentified($data['d'] ?? []),
            5 => $this->handleEvent($data['d'] ?? []),
            7 => $this->handleRequestResponse($data['d'] ?? []),
            default => Log::warning('OBS unknown opcode', ['op' => $op]),
        };
    }

    private function handleHello(array $data): void
    {
        Log::info('OBS Hello received', ['rpcVersion' => $data['rpcVersion'] ?? '?']);

        $authentication = null;
        if (isset($data['authentication']) && $this->password) {
            $authentication = $this->computeAuth(
                $data['authentication']['challenge'],
                $data['authentication']['salt']
            );
        }

        $this->sendWsFrame(json_encode([
            'op' => 1,
            'd'  => [
                'rpcVersion'      => 1,
                'authentication'  => $authentication,
                'eventSubscriptions' => 33,
            ],
        ]));
    }

    private function handleIdentified(array $data): void
    {
        $this->identified = true;
        Log::info('OBS Identified', ['negotiatedRpcVersion' => $data['negotiatedRpcVersion'] ?? 1]);

        $d = $this->connectDeferred;
        $this->connectDeferred = null;
        $d?->resolve(true);
    }

    private function handleRequestResponse(array $data): void
    {
        $requestId = $data['requestId'] ?? null;

        if (!$requestId || !isset($this->pendingRequests[$requestId])) {
            Log::warning('OBS response for unknown request', ['requestId' => $requestId]);
            return;
        }

        $pending = $this->pendingRequests[$requestId];
        unset($this->pendingRequests[$requestId]);

        $elapsed = round((microtime(true) - $pending['startTime']) * 1000, 2);
        Log::debug('OBS response', ['type' => $pending['type'], 'elapsed_ms' => $elapsed]);

        $success = $data['requestStatus']['result'] ?? false;

        if ($success) {
            $pending['deferred']->resolve($data['responseData'] ?? []);
        } else {
            $code    = $data['requestStatus']['code'] ?? 0;
            $message = $data['requestStatus']['comment'] ?? 'Unknown error';
            $pending['deferred']->reject(new ObsRequestFailed($code, $message, $pending['type']));
        }
    }

    private function handleEvent(array $data): void
    {
        $eventType = $data['eventType'] ?? 'unknown';
        Log::debug('OBS event', ['type' => $eventType]);

        $listeners = $this->eventListeners[$eventType] ?? [];
        foreach ($listeners as $listener) {
            try {
                $listener($data['eventData'] ?? []);
            } catch (\Throwable $e) {
                Log::error('OBS event listener threw', ['type' => $eventType, 'error' => $e->getMessage()]);
            }
        }
    }

    private function onClose(): void
    {
        Log::warning('OBS WebSocket closed');
        $wasIdentified = $this->identified;
        $this->cleanup();

        if ($wasIdentified && $this->onDisconnectCallback) {
            ($this->onDisconnectCallback)();
        }
    }

    private function onError(\Throwable $e): void
    {
        Log::error('OBS WebSocket error', ['error' => $e->getMessage()]);
        $deferred = $this->connectDeferred;
        $this->connectDeferred = null;
        $this->cleanup();
        $deferred?->reject($e);
    }

    private function computeAuth(string $challenge, string $salt): string
    {
        $secret = base64_encode(hash('sha256', $this->password . $salt, true));
        return base64_encode(hash('sha256', $secret . $challenge, true));
    }

    private function cleanup(): void
    {
        $wasHandshakeDone = $this->handshakeDone;

        $this->connection    = null;
        $this->identified    = false;
        $this->handshakeDone = false;
        $this->readBuffer    = '';
        // Note: intentionally do NOT clear eventListeners here — they should
        // survive reconnects so the manager can re-attach them after identify.

        foreach ($this->pendingRequests as $pending) {
            $pending['deferred']->reject(new ObsNotConnected('Connection closed'));
        }
        $this->pendingRequests = [];

        if ($this->connectDeferred) {
            // If WS handshake was done, but we never got Identified, it's likely an auth failure
            $reason = $wasHandshakeDone
                ? new ObsAuthFailed('OBS closed connection before Identify — check password')
                : new ObsNotConnected('Connection closed before WebSocket upgrade');
            $this->connectDeferred->reject($reason);
            $this->connectDeferred = null;
        }
    }
}

