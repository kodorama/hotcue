<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBS Controller Settings</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 dark:bg-gray-900 h-full transition-colors duration-200">
    <div class="min-h-screen">
        <div class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <h1 class="text-3xl font-bold dark:text-white">OBS Controller Settings</h1>
                {{-- Dark mode toggle --}}
                <button id="dark-toggle"
                        onclick="toggleDark()"
                        class="p-2 rounded-lg text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Toggle dark mode">
                    <span id="dark-icon-light" class="hidden dark:inline text-lg">☀️</span>
                    <span id="dark-icon-dark" class="inline dark:hidden text-lg">🌙</span>
                </button>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="border-b dark:border-gray-700">
                    <nav class="flex">
                        <button onclick="showTab('connection')" id="tab-connection"
                                class="px-6 py-4 font-medium border-b-2 border-blue-600 text-blue-600">
                            Connection
                        </button>
                        <button onclick="showTab('hotkeys')" id="tab-hotkeys"
                                class="px-6 py-4 font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                            Hotkeys
                        </button>
                    </nav>
                </div>

                <div id="content-connection" class="tab-content">
                    <livewire:settings.connection-settings />
                </div>

                <div id="content-hotkeys" class="tab-content hidden">
                    <livewire:settings.hotkeys-manager />
                </div>
            </div>
        </div>
    </div>

    @livewireScripts

    <script>
        // Dark mode: persist in localStorage, respect OS preference on first load
        (function () {
            const stored = localStorage.getItem('darkMode');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (stored === null && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();

        function toggleDark() {
            const html = document.documentElement;
            html.classList.toggle('dark');
            localStorage.setItem('darkMode', html.classList.contains('dark') ? 'dark' : 'light');
        }

        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('border-blue-600', 'text-blue-600', 'border-b-2');
                el.classList.add('text-gray-600');
            });

            document.getElementById('content-' + tab).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tab);
            activeTab.classList.remove('text-gray-600');
            activeTab.classList.add('border-blue-600', 'text-blue-600', 'border-b-2');
        }
    </script>
</body>
</html>

