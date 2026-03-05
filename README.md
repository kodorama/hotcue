# HotCue — NativePHP macOS Menu Bar OBS Controller

A macOS menu bar application built with **NativePHP + Laravel**, controlling **OBS Studio** via **OBS WebSocket v5**. Register global hotkeys to switch scenes, mute/unmute inputs, adjust volumes, start/stop streaming & recording — all without touching OBS's UI.

<img width="1778" height="936" alt="image" src="https://github.com/user-attachments/assets/c9147cae-ce3e-4751-b152-8b147da67454" />
<img width="794" height="640" alt="image" src="https://github.com/user-attachments/assets/73e6e5ea-6edb-4ae2-ad88-e161ca929a97" />
<img width="694" height="728" alt="image" src="https://github.com/user-attachments/assets/03f6ec4f-7ebc-4701-9b8b-751016b5f586" />
<img width="694" height="716" alt="image" src="https://github.com/user-attachments/assets/d87f7742-0d5c-40c0-9b88-af5da8f7cce5" />

## Features

- 🎯 **Menu Bar App** — runs quietly in your macOS menu bar
- 🔌 **OBS WebSocket v5** — connects to OBS Studio v28+ with SHA-256 auth
- ⌨️ **Global Hotkeys** — system-wide shortcuts even when the Settings window is closed
- 🎬 **13 Action Types** — switch scene, toggle/set mute, adjust/set volume dB, start/stop/toggle streaming & recording, pause/resume recording
- ➕ **Multiple Actions per Hotkey** — chain actions, executed in order on each keypress
- 🔒 **Encrypted Storage** — OBS password stored with Laravel `Crypt` (AES-256-CBC)
- 🔄 **Auto-reconnect** — exponential backoff (0.5 → 1 → 2 → 5 → 10s) + ±20% jitter
- 🌗 **Dark Mode** — class-based, respects system preference, persisted in `localStorage`
- 📋 **Import/Export** — JSON backup/restore of your entire hotkey configuration
- 🎙️ **OBS Event Subscriptions** — reacts to scene changes, mute state, stream/record state
- ▶️ **Test Actions** — fire any hotkey's action immediately from the Settings UI

## Architecture

Strict layer separation (enforced by copilot-instructions.md):

| Layer | Responsibility |
|-------|---------------|
| **Livewire** | Settings UI — reads/writes DB only, never touches WebSockets |
| **ObsDiagnosticsService** | Isolated ReactPHP loop for one-shot requests (Test Connection, Test Action) |
| **ObsConnectionManager** | Single persistent OBS connection; reconnect backoff; event listener registry |
| **ObsWebSocketClient** | RFC 6455 WebSocket over `react/socket`; OBS v5 handshake; request/response correlation |
| **ObsActionRunner** | Maps action types to OBS v5 request names; validates payloads |
| **HotkeyRegistry** | Registers/unregisters NativePHP global shortcuts |
| **HotkeyDispatcher** | Hotkey press → load actions → ObsActionRunner |
| **HotkeyExporter** | JSON export/import with accelerator normalization |
| **SQLite** | Settings singleton + hotkeys + hotkey_actions |

## Requirements

- macOS 12+
- PHP 8.2+
- Node.js 20+ (for building assets)
- [OBS Studio](https://obsproject.com) v28+ with WebSocket server enabled

## Quick Start

```bash
# 1. Install PHP + JS dependencies
composer install
npm install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Run database migrations
php artisan migrate

# 4. Build frontend assets
npm run build

# 5a. Run as desktop app (NativePHP)
php artisan native:serve

# 5b. OR run in browser for development
php artisan serve
# → open http://localhost:8000/settings
```

## Hotkey Accelerator Format

Modifiers in order: `cmd+ctrl+alt+shift+{key}` (key lowercase)

Examples: `cmd+shift+1`, `alt+f4`, `cmd+ctrl+m`, `f13`

## Build for Distribution

```bash
php artisan native:build
# → dist/HotCue-{version}-mac.dmg
```

For a signed + notarized build, set in `.env`:
```
NATIVEPHP_APPLE_ID=you@example.com
NATIVEPHP_APPLE_ID_PASS=xxxx-xxxx-xxxx-xxxx   # App-specific password
NATIVEPHP_APPLE_TEAM_ID=XXXXXXXXXX
```

Then: `php artisan native:build --sign`

## Running Tests

```bash
php vendor/bin/phpunit          # 78 tests, 159 assertions
php vendor/bin/phpunit --testdox  # verbose output
```

## OBS Setup

1. Open OBS → Tools → WebSocket Server Settings
2. Enable WebSocket server (default port: 4455)
3. Set a password (recommended)
4. In HotCue Settings → Connection tab: enter host/port/password → Save → Test Connection
