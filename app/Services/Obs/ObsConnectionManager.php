<?php

namespace App\Services\Obs;

use App\Models\Setting;
use App\Services\Obs\Contracts\ObsClient;
use App\Services\Obs\Exceptions\ObsNotConnected;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

class ObsConnectionManager
{
    private ?ObsClient $client = null;
    private ?TimerInterface $reconnectTimer = null;
    private int $reconnectAttempt = 0;
    private bool $connecting = false;
    /** @var array<string, callable[]> Persisted across reconnects */
    private array $eventListeners = [];
    private const MAX_BACKOFF = 10; // seconds

    public function __construct(
        private readonly LoopInterface $loop
    ) {
    }

    public function isConnected(): bool
    {
        return $this->client && $this->client->isConnected();
    }

    public function getClient(): ?ObsClient
    {
        return $this->client;
    }

    /**
     * Register a persistent event listener.
     * Automatically re-registered on reconnect.
     */
    public function onEvent(string $eventType, callable $listener): void
    {
        $this->eventListeners[$eventType][] = $listener;
        // Also register on currently active client if connected
        if ($this->client instanceof ObsWebSocketClient) {
            $this->client->onEvent($eventType, $listener);
        }
    }

    /**
     * Remove all listeners for an event type.
     */
    public function offEvent(string $eventType): void
    {
        unset($this->eventListeners[$eventType]);
        if ($this->client instanceof ObsWebSocketClient) {
            $this->client->offEvent($eventType);
        }
    }

    /**
     * Connect to OBS using stored settings.
     */
    public function connect(?string $host = null, ?int $port = null, ?bool $secure = null, ?string $password = null): PromiseInterface
    {
        if ($this->isConnected() || $this->connecting) {
            return resolve(null);
        }

        // Cancel any pending reconnect timer so we don't double-connect
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }

        $this->connecting = true;
        $settings = Setting::instance();

        $host     = $host     ?? $settings->ws_host;
        $port     = $port     ?? $settings->ws_port;
        $secure   = $secure   ?? $settings->ws_secure;
        $password = $password ?? $settings->ws_password;

        $protocol = $secure ? 'wss' : 'ws';
        $uri      = "{$protocol}://{$host}:{$port}";

        Log::info('Connecting to OBS', ['uri' => $uri]);

        $this->client = new ObsWebSocketClient($this->loop, $uri, $password);

        // Wire any persisted event listeners onto the new client
        foreach ($this->eventListeners as $eventType => $listeners) {
            foreach ($listeners as $listener) {
                $this->client->onEvent($eventType, $listener);
            }
        }

        // Auto-reconnect when the connection drops unexpectedly
        $this->client->onDisconnect(function () {
            $this->client = null;
            $this->connecting = false;
            Log::warning('OBS connection lost, scheduling reconnect');
            $this->scheduleReconnect();
        });

        return $this->client->connect()
            ->then(function () {
                $this->reconnectAttempt = 0;
                $this->connecting = false;
                Log::info('Successfully connected to OBS');
                return true;
            })
            ->catch(function (\Throwable $e) {
                Log::error('Failed to connect to OBS', ['error' => $e->getMessage()]);
                $this->client = null;
                $this->connecting = false;
                throw $e;
            });
    }

    /**
     * Disconnect from OBS.
     */
    public function disconnect(): void
    {
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }

        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }

        $this->reconnectAttempt = 0;
        $this->connecting = false;
        Log::info('Disconnected from OBS');
    }

    /**
     * Ensure connected, reconnect if necessary.
     */
    public function ensureConnected(): PromiseInterface
    {
        if ($this->isConnected()) {
            return resolve(null);
        }

        return $this->connect();
    }

    /**
     * Schedule reconnect with exponential backoff and jitter.
     */
    public function scheduleReconnect(): void
    {
        if ($this->reconnectTimer) {
            return; // Already scheduled
        }

        $backoff = min(pow(2, $this->reconnectAttempt) * 0.5, self::MAX_BACKOFF);
        $jitter  = $backoff * 0.2 * (mt_rand(-100, 100) / 100);
        $delay   = max(0.1, $backoff + $jitter);

        $this->reconnectAttempt++;

        Log::info('Scheduling OBS reconnect', [
            'attempt' => $this->reconnectAttempt,
            'delay'   => round($delay, 2),
        ]);

        $this->reconnectTimer = $this->loop->addTimer($delay, function () {
            $this->reconnectTimer = null;
            $this->connect()->catch(function () {
                $this->scheduleReconnect();
            });
        });
    }

    /**
     * Send a request through the client.
     */
    public function sendRequest(string $requestType, array $requestData = []): PromiseInterface
    {
        if (!$this->isConnected()) {
            return reject(new ObsNotConnected('Not connected to OBS'));
        }

        return $this->client->sendRequest($requestType, $requestData);
    }
}

