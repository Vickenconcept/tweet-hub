<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Twitter Auto-Comment Settings</h2>
        
        @if($connectedUsername)
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="bx bx-check-circle text-green-600 text-xl mr-2"></i>
                    <div>
                        <p class="text-sm font-semibold text-green-800">Connected account: <span>@</span><span class="font-bold">{{ $connectedUsername }}</span></p>
                        <p class="text-xs text-green-700 mt-1">Your Twitter account is connected and ready for auto-commenting.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-center">
                    <i class="bx bx-error-circle text-amber-600 text-xl mr-2"></i>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Twitter account not connected</p>
                        <p class="text-xs text-amber-700 mt-1">Please connect your Twitter account first to enable auto-commenting.</p>
                    </div>
                </div>
            </div>
        @endif

        <p class="text-gray-600 mb-6">
            Configure auto-commenting settings for your Twitter account. Your API credentials are automatically configured when you connect your account.
        </p>

        @if (session('message'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                {{ session('message') }}
            </div>
        @endif

        @if ($this->successMessage)
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                {{ $this->successMessage }}
            </div>
        @endif

        @if ($this->errorMessage)
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                {{ $this->errorMessage }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="space-y-6">
            <!-- Auto Comment Settings -->
            <div class="border-b border-gray-200 pb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Auto Comment Settings</h3>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="autoCommentEnabled" class="block text-sm font-medium text-gray-700 mb-1">
                                Enable Auto Comment
                            </label>
                            <p class="text-sm text-gray-500">
                                Automatically reply to mentions and keyword matches using AI
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   id="autoCommentEnabled" 
                                   wire:model.live="autoCommentEnabled"
                                   class="sr-only peer"
                                   @if(!$this->isConfigured) disabled @endif>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600 @if(!$this->isConfigured) opacity-50 cursor-not-allowed @endif"></div>
                        </label>
                    </div>

                    @if(!$this->isConfigured)
                        <p class="text-sm text-amber-600 bg-amber-50 p-3 rounded-lg border border-amber-200">
                            ⚠️ Please connect your Twitter account first to enable auto-commenting.
                        </p>
                    @endif

                    <div>
                        <label for="dailyCommentLimit" class="block text-sm font-medium text-gray-700 mb-2">
                            Daily Comment Limit
                        </label>
                        <input type="number" 
                               id="dailyCommentLimit" 
                               wire:model="dailyCommentLimit"
                               min="1" 
                               max="1000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Maximum number of auto-comments per day (1-1000)</p>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="border-b border-gray-200 pb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Today's Statistics</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-600">Comments Posted Today</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $commentsPostedToday }} / {{ $dailyCommentLimit }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-600">Last Comment</p>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ $lastCommentAt ? \Carbon\Carbon::parse($lastCommentAt)->diffForHumans() : 'Never' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Test Tweet Section -->
            <div class="pb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Test Connection</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Send a test tweet to verify your API credentials are working correctly.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="testTweetText" class="block text-sm font-medium text-gray-700 mb-2">
                            Test Tweet Text
                        </label>
                        <textarea id="testTweetText" 
                                  wire:model="testTweetText"
                                  rows="3"
                                  maxlength="280"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Enter test tweet text (max 280 characters)"></textarea>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ mb_strlen($testTweetText) }} / 280 characters
                        </p>
                    </div>

                    @if ($testTweetError)
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                            {{ $testTweetError }}
                        </div>
                    @endif

                    @if ($testTweetSuccess)
                        <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                            ✅ Test tweet sent successfully!
                        </div>
                    @endif

                    <button type="button" 
                            wire:click="sendTestTweet"
                            wire:loading.attr="disabled"
                            wire:target="sendTestTweet"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <span wire:loading.remove wire:target="sendTestTweet">Send Test Tweet</span>
                        <span wire:loading wire:target="sendTestTweet" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sending...
                        </span>
                    </button>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span wire:loading.remove wire:target="save">Save Settings</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

