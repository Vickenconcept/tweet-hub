<div>
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full text-center relative">
                <h2 class="text-2xl font-bold mb-4">Connect X Account</h2>
                <p class="mb-6 text-gray-600">To use this app, you must connect your X (Twitter) account.</p>
                <button wire:click="connectXAccount" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-2xl shadow transition cursor-pointer">Connect X Account</button>
                <div class="mt-6 text-xs text-gray-400">You will be redirected to X (Twitter) to authorize access.</div>
            </div>
        </div>
    @endif
</div>
