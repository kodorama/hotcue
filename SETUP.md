# Setup Guide

## Quick Start

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

```bash
# Migrations will create the settings table with default values
php artisan migrate
```

### 4. Build Assets

```bash
npm run build
```

## Running the Application

### Option A: Web Mode (Development/Testing)

Start the Laravel development server:

```bash
php artisan serve
```

Then open your browser to:
- Settings: http://localhost:8000/settings

### Option B: Native macOS App

Build and run as a native menu bar application:

```bash
php artisan native:serve
```

This will:
1. Launch the app in your menu bar
2. Auto-connect to OBS if configured
3. Register all enabled hotkeys

## First-Time Configuration

### 1. Configure OBS Connection

1. Open OBS Studio (v28+)
2. Go to Tools → WebSocket Server Settings
3. Enable the WebSocket server
4. Note the port (default: 4455)
5. Set a password (optional but recommended)

### 2. Configure in the App

In the Settings window (Connection tab):
- Host: `127.0.0.1` (or your OBS server IP)
- Port: `4455` (or your configured port)
- Secure: Leave unchecked for local connections
- Password: Enter your OBS WebSocket password
- Auto-connect: Check to connect automatically on launch

Click **Test Connection** to verify. You should see:
```
✓ Connected successfully! OBS Version: 30.x.x
```

Click **Save Settings**.

### 3. Create Your First Hotkey

In the Settings window (Hotkeys tab):

1. Click **Add Hotkey**
2. Enter a name (e.g., "Switch to Gaming Scene")
3. Enter accelerator (e.g., `cmd+shift+1`)
4. Select action type: `Switch Scene`
5. Enter scene name: The exact name from OBS
6. Enable the hotkey
7. Click **Save**

The hotkey is now registered globally!

### Accelerator Format

Examples:
- `cmd+shift+1` - Command + Shift + 1
- `ctrl+alt+m` - Control + Alt + M
- `cmd+f13` - Command + F13

Modifiers are automatically normalized to: `cmd+ctrl+alt+shift+key`

## Supported Actions

### 1. Switch Scene
Switches to a specific scene in OBS.

**Parameters:**
- Scene: The exact scene name from OBS

**Example:** Switch to "Gaming Scene"

### 2. Toggle Mute
Toggles mute/unmute for an audio input.

**Parameters:**
- Input: The exact input name from OBS (e.g., "Microphone")

### 3. Set Mute
Sets mute state for an audio input.

**Parameters:**
- Input: The exact input name
- Muted: true or false

### 4. Adjust Volume (dB)
Adjusts volume by a relative amount.

**Parameters:**
- Input: The exact input name
- Delta dB: Change amount (e.g., -2 for quieter, +2 for louder)

### 5. Set Volume (dB)
Sets absolute volume level.

**Parameters:**
- Input: The exact input name
- Volume dB: Absolute level (e.g., -10)

## Troubleshooting

### Connection Issues

**Problem:** Test connection fails with "Connection failed: Connection refused"

**Solutions:**
1. Check OBS is running
2. Verify WebSocket server is enabled in OBS
3. Check port number matches OBS settings
4. For remote OBS, check firewall rules

**Problem:** Test connection fails with "Authentication failed"

**Solutions:**
1. Verify password is correct
2. Re-save password in settings (encrypted storage)
3. Check OBS WebSocket requires authentication

### Hotkey Issues

**Problem:** Hotkey doesn't work

**Solutions:**
1. Check hotkey is enabled (green indicator)
2. Verify accelerator isn't conflicting with system shortcuts
3. Check logs for error messages
4. Ensure OBS is connected

**Problem:** "This accelerator is already registered"

**Solution:**
- Each enabled hotkey must have a unique accelerator
- Disable or delete the conflicting hotkey first

### Action Issues

**Problem:** Scene switch doesn't work

**Solution:**
- Scene name must exactly match OBS (case-sensitive)
- Use the exact name shown in OBS Scenes panel

**Problem:** Volume/mute actions fail

**Solution:**
- Input name must exactly match OBS (case-sensitive)
- Check the exact name in OBS Audio Mixer

## Logs

Logs are written to `storage/logs/laravel.log`

Useful log entries:
- Connection lifecycle events
- Request timings
- Hotkey registration
- Action execution results
- Error details

## Testing

Run the test suite:

```bash
php artisan test
```

Expected output:
```
✓ AcceleratorNormalizer tests (11 tests)
✓ OBS Auth computation tests (2 tests)  
✓ Action validation tests (12 tests)

Tests: 27 passed
```

## Development

### Architecture Overview

**Never mix layers:**
- Livewire components → Application services
- Application services → Domain services
- Domain services → Infrastructure (OBS WebSocket)

**ReactPHP event loop:**
- Single loop instance per process
- All OBS networking runs in this loop
- Single persistent WebSocket connection

**Request flow:**
1. Hotkey pressed → HotkeyDispatcher
2. Load action from DB
3. Validate payload → ObsActionRunner
4. Send to OBS → ObsConnectionManager → ObsWebSocketClient
5. Track by UUID, apply timeout
6. Correlate response, resolve promise

### Adding New Actions

1. Add request type to `ObsActionRunner::executeAction()`
2. Add validation to `ObsActionRunner::validatePayload()`
3. Add to `$actionTypes` in `HotkeysManager`
4. Add payload UI in `hotkeys-manager.blade.php`
5. Write validation tests

## Next Steps

- Customize menu bar icon (replace `storage/app/menuBarIconTemplate.png`)
- Add more OBS scenes/inputs
- Create hotkeys for your workflow
- Build the app for distribution: `php artisan native:build`

## Support

For issues or questions, check:
- `.github/copilot-instructions.md` - Complete architecture
- OBS WebSocket docs: https://github.com/obsproject/obs-websocket
- NativePHP docs: https://nativephp.com

