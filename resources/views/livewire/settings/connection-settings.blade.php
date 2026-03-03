<div class="p-6">
    {{-- OBS Status Badge --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold dark:text-white">OBS Connection Settings</h2>

        <div class="flex items-center gap-3">
            @if($obsStatus === 'connected')
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Connected
                </span>
            @elseif($obsStatus === 'disconnected')
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Disconnected
                </span>
                <button wire:click="reconnect"
                        wire:loading.attr="disabled" wire:target="reconnect"
                        class="px-3 py-1 text-sm bg-gray-700 text-white rounded-md hover:bg-gray-900 disabled:opacity-50">
                    <span wire:loading.remove wire:target="reconnect">Reconnect</span>
                    <span wire:loading wire:target="reconnect">Connecting…</span>
                </button>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                    Status unknown
                </span>
            @endif

            <button wire:click="refreshStatus" title="Refresh status"
                    class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded">
                ↺
            </button>
        </div>
    </div>

    @if(session('saved'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 rounded-md">
            {{ session('saved') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4 max-w-2xl">
        <div>
            <label class="block text-sm font-medium mb-2 dark:text-gray-200">Host</label>
            <input type="text" wire:model="ws_host" class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
            @error('ws_host') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-2 dark:text-gray-200">Port</label>
            <input type="number" wire:model="ws_port" class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
            @error('ws_port') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="ws_secure" class="rounded">
                <span class="text-sm font-medium dark:text-gray-200">Use Secure WebSocket (wss://)</span>
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2 dark:text-gray-200">
                Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span>
            </label>
            <input type="password" wire:model="ws_password" autocomplete="new-password"
                   class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md" placeholder="••••••••">
            @error('ws_password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="auto_connect" class="rounded">
                <span class="text-sm font-medium dark:text-gray-200">Auto-connect on launch</span>
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Save Settings
            </button>

            <button type="button" wire:click="testConnection"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="testConnection">Test Connection</span>
                <span wire:loading wire:target="testConnection">Testing…</span>
            </button>
        </div>

        @if($testResult)
            <div class="mt-4 p-4 rounded-md {{ str_starts_with($testResult, '✓') ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200' }}">
                {{ $testResult }}
            </div>
        @endif
    </form>
</div>

