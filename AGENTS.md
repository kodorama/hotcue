# AGENTS.md — NativePHP OBS Controller (macOS Menubar) — ReactPHP + Livewire

This repository is a **macOS menu bar desktop app** built with **NativePHP + Laravel** using **Livewire** for UI and **ReactPHP** for networking. It controls **OBS Studio (latest)** via **OBS WebSocket v5** (OBS v28+). The app listens to **global hotkeys** and triggers OBS actions (scene switching, mute/volume control, etc.). The app includes a **Settings window** to configure OBS connection and manage hotkeys, persisted in **SQLite**.

**Hard constraints for all code contributions**
- OBS target: **latest OBS only** (OBS WebSocket **v5**, no v4 compatibility).
- WebSocket client library: **ReactPHP** (event loop based).
- UI stack: **Livewire**.
- Persistence: **SQLite**.
- Secrets: OBS password must be stored **encrypted**.

---

## 1) Product goals

### Core UX
- App runs as a **menu bar** application.
- A **Settings** window provides:
    - OBS connection settings: host/port, ws/wss, password, auto-connect.
    - Hotkey manager UI similar to OBS: add/edit/delete hotkeys + enabled toggle.
- Hotkeys persist in SQLite and re-register on launch.
- Hotkeys work even when the Settings window is closed.

### Reliability requirements
- Maintain a **single persistent WebSocket connection** to OBS (do not connect per hotkey).
- Automatic reconnect with exponential backoff and jitter.
- Never block the UI (Livewire) thread with network IO.
- Safe behavior when OBS is not running:
    - show disconnected state
    - hotkeys no-op with optional notification
    - reconnect when OBS becomes available

---

## 2) Architecture (must follow)

### Layers & responsibilities (do not mix)

#### A) Infrastructure: OBS WebSocket (ReactPHP)
- `ObsWebSocketClient`:
    - handles OBS v5 handshake (Hello → Identify)
    - implements optional authentication
    - sends requests and correlates responses by `requestId`
    - parses events and dispatches them to listeners
- `ObsConnectionManager`:
    - owns connection lifecycle (connect/disconnect/reconnect)
    - exposes `isConnected()`, `ensureConnected()`
    - keeps a shared instance of `ObsWebSocketClient`
    - reconnects with backoff

> All networking must run in the **ReactPHP event loop** and must be isolated from Livewire components.

#### B) Domain: Actions
- `ObsActionRunner`:
    - maps saved hotkey actions to OBS request(s)
    - validates payload before sending
    - converts user-friendly parameters (e.g., dB steps) into OBS request data

#### C) Application: Hotkeys
- `HotkeyRegistry`:
    - loads enabled hotkeys from DB
    - registers/unregisters them using NativePHP global hotkey API
- `HotkeyDispatcher`:
    - invoked on hotkey press
    - loads hotkey’s action + payload
    - calls `ObsActionRunner`

#### D) UI: Livewire
- Livewire components render Settings UI and perform CRUD.
- Livewire components must **not** open sockets or call OBS directly.
- Livewire calls into application services via injected classes.

---

## 3) ReactPHP implementation rules

### Event loop ownership
- There must be a **single** ReactPHP loop instance in the app process.
- `ObsConnectionManager` must attach to that loop and keep it alive as needed.
- Avoid spawning multiple loops.

### Request correlation
- Each OBS request must include a unique `requestId` (UUID).
- Maintain a `pendingRequests[requestId] = Deferred` map.
- Resolve/reject deferreds on `RequestResponse` frames.
- Apply timeouts (2–5s) per request and reject with `ObsRequestTimeout`.

### Reconnect strategy
- On disconnect/failure:
    - schedule reconnect with exponential backoff (e.g. 0.5s → 1s → 2s → 5s → 10s, capped)
    - add jitter (+/- 20%) to avoid thundering herd
- Do not spam logs; collapse repeated failures.

### Threading/Blocking
- Never use blocking socket calls.
- Do not use synchronous HTTP clients in the connection manager.
- Keep message parsing lightweight.

---

## 4) Livewire implementation rules

### Boundaries
- Livewire components:
    - read/write settings + hotkeys via Eloquent
    - call *application services* for:
        - "Test connection"
        - "Refresh scenes/inputs"
        - "Re-register hotkeys"
- Livewire must never instantiate the WS client directly.

### “Test connection” behavior
- Use `ObsDiagnosticsService` (recommended) or a short-lived mode in `ObsConnectionManager`.
- Perform:
    - connect + identify
    - `GetVersion`
- Return a structured result to Livewire:
    - success bool
    - OBS version string
    - websocket rpcVersion
    - error code/message if failure

---

## 5) Persistence (SQLite)

### Tables (preferred)
**Singleton settings**
- `settings`
    - `id` (always 1)
    - `ws_host` (string) default `127.0.0.1`
    - `ws_port` (int) default `4455`
    - `ws_secure` (bool) default false
    - `ws_password` (text, encrypted, nullable)
    - `auto_connect` (bool) default true
    - `created_at`, `updated_at`

**Hotkeys**
- `hotkeys`
    - `id`
    - `name` (string)
    - `accelerator` (string normalized, e.g. `cmd+shift+1`)
    - `enabled` (bool)
    - `created_at`, `updated_at`

**Hotkey actions**
- `hotkey_actions`
    - `id`
    - `hotkey_id` (fk)
    - `type` (string enum-like, e.g. `switch_scene`)
    - `payload` (json)
    - `created_at`, `updated_at`

### Validation rules
- `accelerator` must be unique among enabled hotkeys (enforce in application validation; DB index optional).
- `payload` must include required fields per action type.

---

## 6) Hotkey system rules

### Accelerator normalization (must be consistent)
Store normalized strings:
- modifier order: `cmd+ctrl+alt+shift+{key}`
- key lowercased: `m`, `1`, `f13`, `space`, `up`, `down`, `left`, `right`

Implement `AcceleratorNormalizer`:
- input: raw key capture from NativePHP
- output: normalized string
- include unit tests.

### Registration lifecycle
- On app start: load enabled hotkeys and register.
- On hotkey changes: re-register minimal delta.
- On conflict/unavailable shortcut: reject enabling and show UI error.

---

## 7) OBS WebSocket v5 protocol requirements

### Connection flow
1. Connect to `ws(s)://{host}:{port}`
2. Receive `Hello` (contains auth challenge/salt if auth enabled)
3. Send `Identify`:
    - `rpcVersion` set appropriately (usually 1)
    - include computed `authentication` when required
4. Wait for `Identified` before sending requests.

### Authentication
- If OBS requires auth:
    - compute auth response using password + salt + challenge per OBS v5 spec
- Never log secrets.

### Request/Response
- Send `Request` frames with `requestType`, `requestData`, `requestId`.
- Parse `RequestResponse` frames and map to pending requests.
- Errors must be wrapped into domain exceptions.

### Event subscriptions (optional but recommended)
Support subscribing to at least:
- `CurrentProgramSceneChanged`
- `InputMuteStateChanged`
- `InputVolumeChanged`
- stream/record state changes

---

## 8) Supported actions (initial scope)

Implement in `ObsActionRunner`:

1. `switch_scene`
    - payload: `{ "scene": "Scene Name" }`
    - request: `SetCurrentProgramScene`

2. `toggle_mute`
    - payload: `{ "input": "Mic/Aux" }`
    - request: `ToggleInputMute`

3. `set_mute`
    - payload: `{ "input": "Mic/Aux", "muted": true|false }`
    - request: `SetInputMute`

4. `adjust_volume_db`
    - payload: `{ "input": "Desktop Audio", "deltaDb": -2 }`
    - if cached volume exists: compute new + set
    - else: `GetInputVolume` then `SetInputVolume`

5. `set_volume_db`
    - payload: `{ "input": "Desktop Audio", "db": -10 }`

---

## 9) Error handling & logging

### Exceptions
- `ObsNotConnected`
- `ObsAuthFailed`
- `ObsRequestTimeout`
- `ObsRequestFailed` (contains OBS error code + message)
- `ObsProtocolError` (unexpected frames/invalid JSON)

### Logging rules
- Log connection lifecycle and request timing.
- Never log:
    - OBS password
    - auth response

---

## 10) Testing expectations

Minimum unit tests:
- OBS v5 auth computation
- accelerator normalization
- action payload validation

Integration tests:
- Mock `ObsClient` interface; do not require OBS in CI.

---

## 11) Implementation guidance for Copilot (how to contribute)

When generating code, follow these rules:

1. **Do not** implement OBS WebSocket calls in Livewire components.
2. **Do** add thin application services for UI to call (e.g. `ObsDiagnosticsService`, `HotkeyService`).
3. **Do** implement networking using **ReactPHP** with a **single event loop** per process.
4. **Do** keep a **single persistent OBS connection**; do not connect per hotkey press.
5. **Do** implement request/response correlation by `requestId` with a pending map and timeouts.
6. **Do** implement reconnect with exponential backoff (+ jitter) and a max delay cap.
7. **Do** validate action payloads before sending requests (return user-friendly errors).
8. **Do not** invent OBS request types—use official OBS WebSocket v5 request names only.
9. **Do** persist settings/hotkeys via SQLite migrations + Eloquent models.
10. **Do** encrypt the OBS password at rest using Laravel `Crypt`.
11. **Do** write unit tests for auth computation, accelerator normalization, and payload validation.
12. **Do not** log secrets (password, auth response) or sensitive data.

---

## 12) Expected repository layout

Suggested structure (names can vary, responsibilities cannot):

- `app/Services/Obs/`
    - `Contracts/ObsClient.php`
    - `ObsWebSocketClient.php`
    - `ObsConnectionManager.php`
    - `ObsActionRunner.php`
    - `ObsDiagnosticsService.php`
    - `Exceptions/`

- `app/Services/Hotkeys/`
    - `HotkeyRegistry.php`
    - `HotkeyDispatcher.php`
    - `AcceleratorNormalizer.php`

- `app/Models/`
    - `Setting.php`
    - `Hotkey.php`
    - `HotkeyAction.php`

- `database/migrations/`
- `app/Livewire/Settings/`
    - `ConnectionSettings.php`
    - `HotkeysManager.php`
- `resources/views/livewire/settings/`

---

## 13) Non-goals (do not implement unless asked)
- OBS WebSocket v4 support
- Windows/Linux global hotkeys
- Multi-OBS multi-profile management
- Cloud syncing or external telemetry
