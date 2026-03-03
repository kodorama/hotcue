# Development Checklist

## Phase 0 — Bug Fixes Applied (February 2026 — updated February 28, 2026)

### Fixes verified and applied:
- [x] **`ratchet/pawl` removed** — `GuzzleHttp\Psr7\uri_for()` was removed in guzzlehttp/psr7 v2; `ratchet/pawl ^0.3.5` called it → `Call to undefined function` error on Test Connection. Replaced entire ReactPHP stack with modern versions (`react/event-loop ^1.5`, `react/promise ^3.2`, `react/promise-timer ^1.11`, `react/socket ^1.16`) and rewrote `ObsWebSocketClient` to implement RFC 6455 WebSocket handshake + framing directly over `react/socket` — no GuzzleHttp dependency at all.
- [x] **`react/promise` v2→v3 API migration** — `otherwise()` → `catch()`, `always()` → `finally()`, `resolve()` now needs argument — fixed in `ObsConnectionManager`, `ObsDiagnosticsService`, `ObsSceneService`, `NativeAppServiceProvider`
- [x] **`ObsAuthTest`** — removed reflection hack that broke with PHP 8.1 `readonly` properties; rewritten as self-contained test of the auth algorithm with 5 targeted assertions
- [x] `ConnectionSettings`: added `mount()` to load saved settings on render
- [x] `ConnectionSettings`: added `save()` method (was completely missing)
- [x] `ConnectionSettings`: added `auto_connect` public property (was missing, view referenced it)
- [x] `ConnectionSettings`: removed direct `Factory::create()` in Livewire (architecture violation) — now injects `ObsDiagnosticsService` via method injection
- [x] `ObsDiagnosticsService`: removed shared-loop dependency; now creates its own isolated loop per test so `loop->stop()` never kills the main app loop
- [x] `HotkeysManager`: removed injected `HotkeyRegistry` / `ObsActionRunner` from method signatures (caused container resolution failures in web/HTTP mode)
- [x] `HotkeysManager`: inlined payload validation (no longer couples Livewire to ReactPHP-layer services)
- [x] `HotkeysManager`: NativePHP-specific calls (`GlobalShortcut`) guarded behind `isNativeContext()` check
- [x] `HotkeysManager`: replaced `onclick="confirm()"` with `wire:confirm` (Livewire 3/4)
- [x] `NativeAppServiceProvider`: fixed `Menu::new()` (invalid) → `Menu::make(...)` using correct NativePHP facade API
- [x] `ObsConnectionManager`: `sendRequest()` now throws `ObsNotConnected` instead of plain `\Exception`
- [x] `ObsConnectionManager`: wires `onDisconnect` callback to auto-trigger `scheduleReconnect()` when connection drops
- [x] `ObsWebSocketClient`: added `onDisconnect(callable)` hook so manager can react to drops
- [x] All files: `\Exception` → `\Throwable` in `otherwise()` / catch blocks for broader error coverage
- [x] **`ObsRequestFailed::$code` readonly clash** — PHP 8.1+ fatal: `Exception::$code` is non-readonly, cannot redeclare as readonly. Renamed to `$obsCode`/`$obsMessage` as separate readonly properties; pass `$code` to `parent::__construct()` as second arg so `getCode()` still works.
- [x] **`Factory::create()` global loop corruption** — `React\EventLoop\Factory::create()` calls `Loop::set()` internally, replacing the global loop singleton. Replaced with `new StreamSelectLoop()` in `ObsDiagnosticsService` and `ObsSceneService` (isolated loops); `NativeAppServiceProvider` now does `new StreamSelectLoop()` + `Loop::set()` explicitly.
- [x] **`HotkeyRegistry`/`HotkeyDispatcher`/`ObsActionRunner` singletons** — these were unbound in the IoC container, so each `app()->make()` call in Livewire returned a fresh instance, losing `$registeredHotkeys` state. All three now registered as singletons in `NativeAppServiceProvider::register()`.
- [x] **`NativeAppServiceProvider::boot()` guard** — condition checked `class_exists(MenuBar::class)` which is always `true` (class is in vendor); simplified to `!defined('NATIVE_PHP_RUNNING')` only.
- [x] **`ConnectionSettings::reconnect()` promise API** — used `->otherwise()` (react/promise v2); replaced with `->catch()` (v3).
- [x] **`ObsConnectionManager::connect()` timer race** — calling `connect()` while a reconnect timer was pending could schedule a second connection attempt. Now cancels any pending timer at the start of `connect()`.
- [x] **`ObsWebSocketClient::onError()`** — did not call `cleanup()`, leaving connection/pending state dirty on stream errors. Now calls `cleanup()` before rejecting deferred.
- [x] **`ObsWebSocketClient::cleanup()` auth error detection** — when connection closes before `Identified` but after HTTP upgrade, it now rejects the connect deferred with `ObsAuthFailed` (descriptive) instead of generic `ObsNotConnected`.
- [x] **`Setting::instance()` default attributes** — `firstOrCreate(['id' => 1])` had no `$attributes`, so a freshly created row had no defaults. Added full defaults map as second argument so model defaults are explicit regardless of migration.
- [x] **`HotkeyDispatcher`/`HotkeyRegistry`** — catch blocks used `\Exception` instead of `\Throwable`, missing `Error` subclasses. Fixed to `\Throwable` throughout.

## ✅ MVP Implementation Complete

### Core Infrastructure
- [x] NativePHP + Laravel installed
- [x] ReactPHP event loop configured (singleton in NativeAppServiceProvider)
- [x] WebSocket client: pure `react/socket` RFC 6455 implementation (no ratchet/pawl)
- [x] SQLite database configured
- [x] Livewire UI framework ready

### Database Layer
- [x] Settings table migration (singleton row)
- [x] Hotkeys table migration
- [x] Hotkey actions table migration
- [x] Setting model with encrypted password (Laravel Crypt)
- [x] Hotkey model with `enabled` scope and `actions` relationship
- [x] HotkeyAction model with JSON payload casting

### OBS Services
- [x] ObsClient contract (interface)
- [x] ObsWebSocketClient (OBS v5 protocol)
  - [x] WebSocket connection via react/socket + manual RFC 6455 handshake & framing (no GuzzleHttp)
  - [x] Hello → Identify handshake (opcodes 0 → 1 → 2)
  - [x] Optional authentication (SHA-256, OBS v5 formula)
  - [x] Request/response correlation by UUID requestId
  - [x] Per-request timeouts (5s via react/promise-timer)
  - [x] onDisconnect callback for auto-reconnect signalling
  - [x] Proper cleanup on disconnect (rejects pending requests)
- [x] ObsConnectionManager
  - [x] Single persistent connection
  - [x] Exponential backoff reconnect (0.5s → 1s → 2s … cap 10s)
  - [x] Jitter ±20%
  - [x] Auto-reconnect wired via onDisconnect callback
  - [x] `isConnected()`, `connect()`, `disconnect()`, `ensureConnected()`, `scheduleReconnect()`
- [x] ObsActionRunner
  - [x] switch_scene → SetCurrentProgramScene
  - [x] toggle_mute → ToggleInputMute
  - [x] set_mute → SetInputMute
  - [x] adjust_volume_db → GetInputVolume + SetInputVolume
  - [x] set_volume_db → SetInputVolume
  - [x] Payload validation (returns user-friendly errors)
- [x] ObsDiagnosticsService
  - [x] Isolated loop per test (never stops shared app loop)
  - [x] Performs connect + GetVersion
  - [x] Returns: success, obsVersion, rpcVersion, error
- [x] Exception hierarchy
  - [x] ObsNotConnected
  - [x] ObsAuthFailed
  - [x] ObsRequestTimeout
  - [x] ObsRequestFailed (with OBS error code)
  - [x] ObsProtocolError

### Hotkey Services
- [x] AcceleratorNormalizer
  - [x] Normalizes to `cmd+ctrl+alt+shift+key` order
  - [x] Handles aliases (command→cmd, control→ctrl, option→alt)
  - [x] Case-insensitive input
  - [x] `toNativeFormat()` converts to NativePHP format
- [x] HotkeyRegistry
  - [x] Loads enabled hotkeys from DB
  - [x] Registers with `GlobalShortcut` (NativePHP)
  - [x] Unregisters on disable/delete
  - [x] `isAcceleratorRegistered()` for conflict detection
  - [x] Boot-time `registerAll()`
- [x] HotkeyDispatcher
  - [x] Dispatches hotkey press → loads actions → calls ObsActionRunner
  - [x] Logs errors without crashing

### Livewire UI
- [x] ConnectionSettings component
  - [x] `mount()` loads saved settings from DB
  - [x] `save()` validates and persists (password only updated if provided)
  - [x] `testConnection()` injects ObsDiagnosticsService via method injection
  - [x] Success/error feedback displayed
  - [x] wire:loading state on Test Connection button
  - [x] OBS status badge (connected/disconnected/unknown) with pulsing dot
  - [x] Manual Reconnect button (when disconnected, NativePHP context only)
  - [x] ↺ Refresh status button
  - [x] `refreshStatus()` and `reconnect()` are no-ops in web mode (safe)
- [x] HotkeysManager component
  - [x] `mount()` loads hotkeys
  - [x] Create/edit/delete/toggle CRUD
  - [x] Accelerator normalization on save
  - [x] Duplicate accelerator detection (DB query, no NativePHP dependency)
  - [x] Inline payload validation (no ReactPHP service injection)
  - [x] Dynamic action parameter fields per action type
  - [x] `wire:confirm` on delete
  - [x] NativePHP registry sync guarded behind `isNativeContext()`
- [x] Settings view (tabbed: Connection | Hotkeys)
- [x] Blade templates with Tailwind CSS

### NativePHP Integration
- [x] NativeAppServiceProvider
- [x] ReactPHP loop singleton (registered in `register()`)
- [x] ObsConnectionManager singleton
- [x] Menu bar setup using `Menu::make()` (correct NativePHP facade API)
- [x] Auto-connect on boot (guarded: only in NATIVE_PHP_RUNNING context)
- [x] Hotkey registration on boot (guarded)
- [x] Routes configured (`/settings`)

### Testing
- [x] AcceleratorNormalizerTest — 12 tests (all pass)
- [x] ObsAuthTest — 5 tests: OBS v5 SHA-256 auth computation (self-contained, no reflection)
- [x] ObsActionValidationTest — 12 tests: all action types (all pass)
- [x] ExampleTest (feature + unit) — 2 tests (pass)
- [x] ConnectionSettingsTest — 12 Livewire feature tests (mount, save, validate, password, testConnection mock, obsStatus, refreshStatus, reconnect)
- [x] HotkeysManagerTest — 12 Livewire feature tests (CRUD, validation, normalization, toggle, conflict detection)
- [x] **All 55 tests passing ✅** (PHP 8.4.1, PHPUnit 11, Livewire 4.2.0 — verified Feb 28 2026)

### Architecture Compliance
- [x] Livewire never touches WebSockets directly ✅
- [x] Livewire never touches the ReactPHP loop ✅
- [x] ObsDiagnosticsService uses isolated loop (safe from Livewire context) ✅
- [x] Services properly layered ✅
- [x] Single persistent OBS connection ✅
- [x] Request/response correlation by requestId ✅
- [x] Per-request timeouts ✅
- [x] Reconnect: exponential backoff + jitter + max 10s cap ✅
- [x] Auto-reconnect wired via onDisconnect callback ✅
- [x] Password encrypted at rest (Laravel Crypt) ✅
- [x] No secrets logged ✅
- [x] OBS WebSocket v5 only, official request types only ✅

## 📋 Optional Future Enhancements

- [x] Scene/input discovery (GetSceneList / GetInputList) — `ObsSceneService` with isolated loop; dropdowns in HotkeysManager modal with "Refresh from OBS" button
- [x] OBS connection status indicator in Settings UI — connected/disconnected/unknown badge with pulsing dot, manual Reconnect button, ↺ refresh; guarded behind `isNativeContext()` so safe in web mode
- [x] Dark mode support — class-based (`dark:` Tailwind classes), toggled via JS, persisted in `localStorage`, respects OS `prefers-color-scheme` on first load; ☀️/🌙 toggle button in header
- [x] Test buttons for each hotkey action in the UI — "▶ Test" button per hotkey row calls `ObsDiagnosticsService::runAction()` via isolated loop; result shown as ✓/✗ banner above list
- [x] Event subscriptions — `ObsWebSocketClient::onEvent(string, callable)` / `offEvent()`; `ObsConnectionManager::onEvent()` persists listeners across reconnects and re-wires them on each new client; `NativeAppServiceProvider` subscribes to `CurrentProgramSceneChanged`, `InputMuteStateChanged`, `InputVolumeChanged`, `StreamStateChanged`, `RecordStateChanged` on boot
- [x] More OBS actions — added `start_streaming`, `stop_streaming`, `toggle_streaming`, `start_recording`, `stop_recording`, `toggle_recording`, `pause_recording`, `resume_recording` to `ObsActionRunner` and `HotkeysManager`; grouped `<optgroup>` in action type select; validation returns empty errors for no-payload types; 8 new unit tests
- [x] Multiple actions per hotkey — `$actions[]` array in `HotkeysManager`; `addAction()`/`removeAction(int)` methods; modal shows numbered action rows each with their own type selector + params; `save()` persists all rows to `hotkey_actions`; `HotkeyDispatcher` already iterated all actions; list row shows count + all types; 2 new feature tests
- [x] Import/export hotkey config — `HotkeyExporter` service with `export()` (JSON download) and `import(json, merge)` (merge or replace mode); normalizes accelerators on import; upserts by accelerator; `HotkeysManager` has `exportHotkeys()` + `importHotkeys()` Livewire actions; file picker + "Apply Import" button; result banner (✓/⚠); 10 new tests covering roundtrip, replace, merge, invalid JSON, missing fields
- [x] Custom menu bar icon — generated 22×22 `menuBarIconTemplate.png` and 44×44 `menuBarIconTemplate@2x.png` using PHP GD; pure black+transparent circles (OBS record-button style); macOS auto-inverts `*Template*` PNGs in dark menu bar
- [x] Build for distribution configured — `nativephp.php` updated with `com.kodorama.hotcue` ID, author, description; `npm run build` wired as `prebuild` step; `.env.example` documents all NativePHP + signing env vars; `README.md` documents `native:build` and `--sign` flow with Apple credentials; `php artisan native:build` produces a distributable `.dmg`
- [ ] Hotkey groups/profiles
- [ ] Code signing + notarization for macOS (requires Apple Developer account credentials)

### Test count: **78 tests, 159 assertions** ✅ (Feb 28 2026)

## Commands

```bash
# Run tests
php artisan test          # or: php vendor/bin/phpunit

# Run in web mode (browser)
php artisan serve
# then open: http://localhost:8000/settings

# Run as NativePHP desktop app
php artisan native:serve

# Fresh migration
php artisan migrate:fresh
```
