<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold dark:text-white">Hotkeys</h2>
        <div class="flex items-center gap-2">
            {{-- Export --}}
            <button wire:click="exportHotkeys"
                    wire:loading.attr="disabled" wire:target="exportHotkeys"
                    title="Export all hotkeys to JSON"
                    class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 dark:text-gray-200 border dark:border-gray-600 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                ⬇ Export JSON
            </button>
            {{-- Import trigger --}}
            <label class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 dark:text-gray-200 border dark:border-gray-600 rounded hover:bg-gray-200 dark:hover:bg-gray-600 cursor-pointer">
                ⬆ Import JSON
                <input type="file" wire:model="importFile" accept=".json" class="hidden">
            </label>
            @if($importFile)
                <button wire:click="importHotkeys"
                        wire:loading.attr="disabled" wire:target="importHotkeys"
                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="importHotkeys">Apply Import</span>
                    <span wire:loading wire:target="importHotkeys">Importing…</span>
                </button>
            @endif
            <button wire:click="create" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Add Hotkey
            </button>
        </div>
    </div>

    @if(session('hotkey_saved'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 rounded-md">
            {{ session('hotkey_saved') }}
        </div>
    @endif

    @if($importResult)
        <div class="mb-4 p-3 rounded-md text-sm {{ str_starts_with($importResult, '✓') ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200' : 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200' }}">
            {{ $importResult }}
            <button wire:click="$set('importResult', null)" class="ml-2 opacity-60 hover:opacity-100">✕</button>
        </div>
    @endif

    @if($testResult)
        <div class="mb-4 p-3 rounded-md text-sm {{ str_starts_with($testResult, '✓') ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200' }}">
            {{ $testResult }}
            <button wire:click="$set('testResult', null)" class="ml-2 opacity-60 hover:opacity-100">✕</button>
        </div>
    @endif

    <div class="space-y-2">
        @forelse($hotkeys as $hotkey)
            <div class="border dark:border-gray-700 rounded-lg p-4 flex items-center justify-between dark:bg-gray-800">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold dark:text-white">{{ $hotkey->name }}</h3>
                        <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 dark:text-gray-200 text-sm rounded font-mono">{{ $hotkey->accelerator }}</span>
                        @if($hotkey->enabled)
                            <span class="text-green-600 dark:text-green-400 text-sm font-medium">Enabled</span>
                        @else
                            <span class="text-gray-400 text-sm">Disabled</span>
                        @endif
                    </div>
                    @if($hotkey->actions->isNotEmpty())
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $hotkey->actions->count() > 1 ? $hotkey->actions->count() . ' actions: ' : 'Action: ' }}
                            {{ $hotkey->actions->map(fn($a) => $actionTypes[$a->type] ?? $a->type)->implode(', ') }}
                        </div>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button wire:click="testAction({{ $hotkey->id }})"
                            wire:loading.attr="disabled" wire:target="testAction({{ $hotkey->id }})"
                            title="Test this action now (requires OBS connection)"
                            class="px-3 py-1 text-sm bg-purple-500 hover:bg-purple-600 text-white rounded disabled:opacity-50">
                        <span wire:loading.remove wire:target="testAction({{ $hotkey->id }})">▶ Test</span>
                        <span wire:loading wire:target="testAction({{ $hotkey->id }})">…</span>
                    </button>
                    <button wire:click="toggleEnabled({{ $hotkey->id }})"
                            wire:loading.attr="disabled"
                            class="px-3 py-1 text-sm {{ $hotkey->enabled ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600' }} text-white rounded">
                        {{ $hotkey->enabled ? 'Disable' : 'Enable' }}
                    </button>
                    <button wire:click="edit({{ $hotkey->id }})"
                            class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">
                        Edit
                    </button>
                    <button wire:click="delete({{ $hotkey->id }})"
                            wire:confirm="Delete '{{ $hotkey->name }}'? This cannot be undone."
                            class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600">
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <div class="text-gray-500 dark:text-gray-400 text-center py-8 border-2 border-dashed dark:border-gray-700 rounded-lg">
                No hotkeys configured. Click "Add Hotkey" to create one.
            </div>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold dark:text-white">{{ $editingId ? 'Edit' : 'Add' }} Hotkey</h3>

                    {{-- Refresh from OBS button --}}
                    <button type="button" wire:click="discoverObs"
                            wire:loading.attr="disabled" wire:target="discoverObs"
                            class="flex items-center gap-1 px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 dark:text-gray-200 border dark:border-gray-600 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                        <span wire:loading.remove wire:target="discoverObs">🔄 Refresh from OBS</span>
                        <span wire:loading wire:target="discoverObs">Fetching…</span>
                    </button>
                </div>

                @if($discoveryError)
                    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200 rounded-md text-sm">
                        ⚠ Could not fetch OBS data: {{ $discoveryError }}. You can still type values manually.
                    </div>
                @endif

                @if(!empty($obsScenes) || !empty($obsInputs))
                    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200 rounded-md text-sm">
                        ✓ Fetched {{ count($obsScenes) }} scene(s) and {{ count($obsInputs) }} input(s) from OBS.
                    </div>
                @endif

                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Name</label>
                        <input type="text" wire:model="name" class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md" placeholder="e.g. Switch to Game Scene">
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Accelerator</label>
                        <input type="text" wire:model="accelerator" class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md font-mono" placeholder="e.g. cmd+shift+1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Modifiers: cmd, ctrl, alt, shift. Example: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cmd+shift+1</code></p>
                        @error('accelerator') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="enabled" class="rounded">
                            <span class="text-sm font-medium dark:text-gray-200">Enabled</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Action Type</label>
                        <select wire:model.live="actionType" class="w-full px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                            <optgroup label="Scene">
                                <option value="switch_scene">Switch Scene</option>
                            </optgroup>
                            <optgroup label="Audio">
                                <option value="toggle_mute">Toggle Mute</option>
                                <option value="set_mute">Set Mute</option>
                                <option value="adjust_volume_db">Adjust Volume (dB)</option>
                                <option value="set_volume_db">Set Volume (dB)</option>
                            </optgroup>
                            <optgroup label="Streaming">
                                <option value="start_streaming">Start Streaming</option>
                                <option value="stop_streaming">Stop Streaming</option>
                                <option value="toggle_streaming">Toggle Streaming</option>
                            </optgroup>
                            <optgroup label="Recording">
                                <option value="start_recording">Start Recording</option>
                                <option value="stop_recording">Stop Recording</option>
                                <option value="toggle_recording">Toggle Recording</option>
                                <option value="pause_recording">Pause Recording</option>
                                <option value="resume_recording">Resume Recording</option>
                            </optgroup>
                        </select>
                    </div>

                    {{-- Multi-action list --}}
                    <div class="border-t dark:border-gray-700 pt-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold dark:text-gray-200">Actions <span class="text-xs font-normal text-gray-400">(run in order on hotkey press)</span></h4>
                            <button type="button" wire:click="addAction"
                                    class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-900/60">
                                ＋ Add Action
                            </button>
                        </div>

                        @foreach($actions as $i => $act)
                            @php $aType = $act['type'] ?? 'switch_scene'; $aPayload = $act['payload'] ?? []; @endphp
                            <div class="border dark:border-gray-600 rounded-lg p-3 space-y-3 bg-gray-50 dark:bg-gray-750">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-gray-400 w-5">#{{ $i + 1 }}</span>
                                    <select wire:model.live="actions.{{ $i }}.type"
                                            class="flex-1 px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md text-sm">
                                        <optgroup label="Scene">
                                            <option value="switch_scene">Switch Scene</option>
                                        </optgroup>
                                        <optgroup label="Audio">
                                            <option value="toggle_mute">Toggle Mute</option>
                                            <option value="set_mute">Set Mute</option>
                                            <option value="adjust_volume_db">Adjust Volume (dB)</option>
                                            <option value="set_volume_db">Set Volume (dB)</option>
                                        </optgroup>
                                        <optgroup label="Streaming">
                                            <option value="start_streaming">Start Streaming</option>
                                            <option value="stop_streaming">Stop Streaming</option>
                                            <option value="toggle_streaming">Toggle Streaming</option>
                                        </optgroup>
                                        <optgroup label="Recording">
                                            <option value="start_recording">Start Recording</option>
                                            <option value="stop_recording">Stop Recording</option>
                                            <option value="toggle_recording">Toggle Recording</option>
                                            <option value="pause_recording">Pause Recording</option>
                                            <option value="resume_recording">Resume Recording</option>
                                        </optgroup>
                                    </select>
                                    @if(count($actions) > 1)
                                        <button type="button" wire:click="removeAction({{ $i }})"
                                                class="px-2 py-1 text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400">✕</button>
                                    @endif
                                </div>

                                @php
                                    $noParamTypes = ['start_streaming','stop_streaming','toggle_streaming','start_recording','stop_recording','toggle_recording','pause_recording','resume_recording'];
                                @endphp

                                @if(!in_array($aType, $noParamTypes))
                                    {{-- Scene --}}
                                    @if($aType === 'switch_scene')
                                        <div>
                                            <label class="block text-xs font-medium mb-1 text-gray-500 dark:text-gray-400">Scene Name</label>
                                            @if(!empty($obsScenes))
                                                <select wire:model="actions.{{ $i }}.payload.scene" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                                    <option value="">— Select —</option>
                                                    @foreach($obsScenes as $scene)
                                                        <option value="{{ $scene }}">{{ $scene }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" wire:model="actions.{{ $i }}.payload.scene" placeholder="e.g. Gaming Scene" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                            @endif
                                        </div>

                                    {{-- Toggle/Set Mute / Volume --}}
                                    @elseif(in_array($aType, ['toggle_mute','set_mute','adjust_volume_db','set_volume_db']))
                                        <div>
                                            <label class="block text-xs font-medium mb-1 text-gray-500 dark:text-gray-400">Input Name</label>
                                            @if(!empty($obsInputs))
                                                <select wire:model="actions.{{ $i }}.payload.input" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                                    <option value="">— Select —</option>
                                                    @foreach($obsInputs as $input)
                                                        <option value="{{ $input }}">{{ $input }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" wire:model="actions.{{ $i }}.payload.input" placeholder="e.g. Mic/Aux" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                            @endif
                                        </div>
                                        @if($aType === 'set_mute')
                                            <label class="flex items-center gap-2 text-sm dark:text-gray-200">
                                                <input type="checkbox" wire:model="actions.{{ $i }}.payload.muted" class="rounded">
                                                Set as Muted
                                            </label>
                                        @elseif($aType === 'adjust_volume_db')
                                            <div>
                                                <label class="block text-xs font-medium mb-1 text-gray-500 dark:text-gray-400">Delta dB</label>
                                                <input type="number" step="0.1" wire:model="actions.{{ $i }}.payload.deltaDb" placeholder="-2" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                            </div>
                                        @elseif($aType === 'set_volume_db')
                                            <div>
                                                <label class="block text-xs font-medium mb-1 text-gray-500 dark:text-gray-400">Volume dB</label>
                                                <input type="number" step="0.1" wire:model="actions.{{ $i }}.payload.db" placeholder="-10" class="w-full px-2 py-1.5 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-sm">
                                            </div>
                                        @endif
                                    @endif
                                @else
                                    <p class="text-xs text-gray-400 italic">No parameters needed.</p>
                                @endif
                            </div>
                        @endforeach

                        @error('actionPayload') <span class="text-red-500 text-sm block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3 pt-4 border-t dark:border-gray-700">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        <button type="button" wire:click="closeModal"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

