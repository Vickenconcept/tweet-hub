<div>
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div class="bg-white rounded-3xl shadow-xl p-8 max-w-md w-full text-center relative">
                <h2 class="text-2xl font-bold mb-4">Connect X Account</h2>
                <p class="mb-4 text-gray-600">To use this app, you must connect your X (Twitter) account.</p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center mt-4">
                    <button
                        wire:click="connectXAccount"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-2xl shadow transition cursor-pointer w-full sm:w-auto">
                        Connect X Account
                    </button>
                    <a
                        href="{{ route('auth.logout') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-2xl border border-gray-200 transition cursor-pointer w-full sm:w-auto inline-flex items-center justify-center">
                        Logout
                    </a>
                </div>

                <div class="mt-6 text-xs text-gray-400">
                    You will be redirected to X (Twitter) to authorize access.
                </div>
            </div>
        </div>
    @endif
</div>
