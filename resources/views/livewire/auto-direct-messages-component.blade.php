<div class="space-y-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Auto Direct Messages</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Automatically send DMs when people interact with your tweets (likes, replies, quotes).
                </p>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-green-200 p-4" x-data="{ show: true }"
                x-show="show" x-init="setTimeout(() => { show = false }, 4000)">
                <div class="flex items-center text-green-700 text-sm">
                    <i class="bx bx-check-circle mr-2 text-lg"></i>
                    <span>{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-red-200 p-4" x-data="{ show: true }"
                x-show="show" x-init="setTimeout(() => { show = false }, 8000)">
                <div class="flex items-start text-sm">
                    <i class="bx bx-error-circle mr-2 text-lg text-red-600"></i>
                    <p class="text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Twitter API Plan Info -->
        <div class="bg-green-50 border border-green-300 rounded-2xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-green-900 mb-1">✅ Works on Basic Plan - Uses Public Replies</h4>
                    <p class="text-xs text-green-800 leading-relaxed">
                        <strong>Good news:</strong> This feature works on <strong>Basic Plan ($100/month)</strong>! 
                        Instead of DMs (which require Pro Plan), the system sends <strong>public replies</strong> mentioning the user. 
                        This creates social proof, reaches non-followers, and often converts 3-5x better than DMs. 
                        <strong>Rate limit:</strong> 300 replies/day on Basic Plan.
                    </p>
                </div>
            </div>
        </div>

        <!-- Enable/Disable Toggle -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <input type="checkbox" wire:model.live="interactionAutoDmEnabled" id="interactionEnabled"
                    class="mt-1 rounded border-gray-300 text-green-600 focus:ring-green-500">
                <div class="flex-1">
                    <label for="interactionEnabled" class="text-sm font-medium text-gray-900 cursor-pointer">
                        Enable Auto DM on Interactions
                    </label>
                    <p class="text-xs text-gray-600 mt-1">
                        When enabled, public replies will be sent automatically to users who interact with ANY of your recent tweets.
                        <span class="font-medium text-green-700">(Works on Basic Plan - uses public replies)</span>
                    </p>
                </div>
            </div>
        </div>

        @if ($interactionAutoDmEnabled)
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">DM Template</label>
                        <button type="button" wire:click="generateDmTemplate" wire:loading.attr="disabled"
                            wire:target="generateDmTemplate"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-300 rounded-xl hover:bg-green-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <div wire:loading.remove wire:target="generateDmTemplate" class="flex">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                                <span>Generate with AI</span>
                            </div>
                            <div wire:loading wire:target="generateDmTemplate" class="flex items-center gap-1.5">
                                <span>
                                    <svg class="animate-spin h-3 w-3 text-green-700" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </span>
                                <span> Generating...</span>
                            </div>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-2">This message will be sent as a public reply mentioning users who interact with your tweets.</p>

                    @if ($generatingTemplate)
                        <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-xs text-blue-700">AI is generating your DM template...</p>
                        </div>
                    @endif

                    <div class="mb-2">
                        <input type="text" wire:model.defer="aiPrompt"
                            class="w-full rounded-xl border-2 border-gray-400 px-3 py-2 bg-white focus:border-green-500 focus:ring-green-500 text-xs placeholder-gray-400"
                            placeholder="Optional: Describe what you want the DM to say">
                    </div>

                    <textarea rows="4" wire:model.defer="interactionDmTemplate"
                        class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm resize-none placeholder-gray-400"
                        placeholder="Hey! Thanks for engaging with my tweet. I'd love to connect!"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Daily DM Limit</label>
                    <input type="number" min="1" max="200" wire:model.defer="interactionDailyLimit"
                        class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400">
                    <p class="text-xs text-gray-400 mt-1">Maximum number of interaction DMs to send per day.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Monitor Interactions</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorLikes"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Likes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorRetweets"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Retweets</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorReplies"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Replies</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.defer="monitorQuotes"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Quote Tweets</span>
                        </label>
                    </div>
                </div>

                <button wire:click="saveInteractionSettings" wire:loading.attr="disabled"
                    wire:target="saveInteractionSettings"
                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="saveInteractionSettings">Save Settings</span>
                    <span wire:loading wire:target="saveInteractionSettings" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Saving...
                    </span>
                </button>

                <div class="mt-6 p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <p class="text-xs text-gray-600">
                        <strong>How it works:</strong> When enabled, the system automatically monitors your recent tweets every 15 minutes. 
                        If someone likes, replies, or quotes your tweets, they'll receive a public reply mentioning them (e.g., "@username Thanks for the like!"). 
                        This creates social proof and often performs better than DMs. The system prevents duplicate replies and respects your daily limit.
                    </p>
                </div>
            </div>
        @else
            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200">
                <p class="text-sm text-gray-600">
                    Enable the toggle above to start automatically sending DMs to users who interact with your tweets.
                </p>
            </div>
        @endif

        <!-- Sent DMs Tracking Section -->
        <div class="mt-8 border-t border-dashed border-gray-100 pt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Sent DMs History</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Track all DMs sent through auto interactions (similar to TweetHunter).
                    </p>
                </div>
                <button wire:click="loadSentDms" wire:loading.attr="disabled"
                    wire:target="loadSentDms"
                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-medium text-gray-800 hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="loadSentDms">Refresh</span>
                    <span wire:loading wire:target="loadSentDms" class="inline-flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3 text-gray-600" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Loading...
                    </span>
                </button>
            </div>

            @if ($loadingSentDms)
                <p class="text-xs text-gray-500 text-center py-4">Loading sent DMs...</p>
            @elseif (!empty($sentDms))
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach ($sentDms as $dm)
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if (!empty($dm['recipient_username']))
                                            <a href="https://twitter.com/{{ $dm['recipient_username'] }}" target="_blank"
                                                class="text-sm font-medium text-gray-900 hover:text-green-700">
                                                {{ $dm['recipient_name'] ?? '@' . $dm['recipient_username'] }}
                                            </a>
                                            <span class="text-xs text-gray-500">@{{ $dm['recipient_username'] }}</span>
                                        @else
                                            <span class="text-sm font-medium text-gray-900">User ID: {{ $dm['twitter_recipient_id'] }}</span>
                                        @endif
                                        @if (!empty($dm['interaction_type']))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($dm['interaction_type'] === 'like') bg-red-100 text-red-800
                                                @elseif($dm['interaction_type'] === 'reply') bg-blue-100 text-blue-800
                                                @elseif($dm['interaction_type'] === 'quote') bg-purple-100 text-purple-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ ucfirst($dm['interaction_type']) }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600 mb-1">{{ \Illuminate\Support\Str::limit($dm['dm_text'] ?? '', 100) }}</p>
                                    @if (!empty($dm['tweet_id']))
                                        <a href="https://twitter.com/i/web/status/{{ $dm['tweet_id'] }}" target="_blank"
                                            class="text-xs text-blue-600 hover:underline">View Tweet</a>
                                    @endif
                                </div>
                                <div class="text-right">
                                    @if (!empty($dm['sent_at']))
                                        <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($dm['sent_at'])->diffForHumans() }}</p>
                                    @endif
                                    @if (!empty($dm['twitter_event_id']))
                                        <p class="text-xs text-gray-400 mt-1">Event: {{ \Illuminate\Support\Str::limit($dm['twitter_event_id'], 10) }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-500 text-center py-4">No sent DMs yet. DMs will appear here once they're sent.</p>
            @endif
        </div>
    </div>
</div>
