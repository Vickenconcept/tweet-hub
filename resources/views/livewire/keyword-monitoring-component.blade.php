<div class="space-y-6" x-data="{ init() { setTimeout(() => { if ($wire.keywords.length > 0 || $wire.advancedSearch) { $wire.loadTweets(false); } }, 3000); } }">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">Keyword Monitoring</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    {{ $advancedSearch ? 'Advanced Search' : 'Keyword Monitoring' }}
                </h1>
                <p class="text-gray-500 mt-2 text-sm md:text-base">
                    @if($isRateLimited)
                        <i class="bx bx-error-circle mr-1 text-orange-600"></i>
                        Rate Limited - Wait {{ $rateLimitWaitMinutes }} min(s) | Resets: {{ $rateLimitResetTime }}
                    @elseif($lastRefresh)
                        <i class="bx bx-check-circle mr-1 text-green-600"></i>
                        Last updated: {{ $lastRefresh }}
                    @else
                        <i class="bx bx-info-circle mr-1 text-blue-600"></i>
                        Monitor keywords, hashtags, and mentions on X
                    @endif
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <button type="button"
                        wire:click="refreshTweets" 
                        wire:loading.attr="disabled"
                        @if($isRateLimited) disabled style="pointer-events: none;" onclick="return false;" @endif
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold transition-colors
                               @if($isRateLimited)
                                   text-gray-400 bg-gray-100 cursor-not-allowed opacity-50
                               @else
                                   text-gray-700 hover:border-gray-300
                               @endif">
                    <i class="bx bx-sync text-lg"></i>
                    <span wire:loading.remove wire:target="refreshTweets">
                        @if($isRateLimited)
                            Rate Limited
                        @else
                            Sync Fresh Data
                        @endif
                    </span>
                    <span wire:loading wire:target="refreshTweets">Syncing...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Keyword Management Section - Hidden when advanced search is enabled -->
    @if(!$advancedSearch)
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex items-center mb-6">
            <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mr-4">
                <i class="bx bx-search text-2xl text-green-600"></i>
            </div>
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Settings</p>
                <h3 class="text-2xl font-semibold text-gray-900 mt-1">Manage Keywords</h3>
            </div>
        </div>
        
        <!-- Add New Keyword -->
        <div class="mb-6">
            <div class="flex gap-3">
                <div class="flex-1">
                    <input wire:model="newKeyword" 
                           type="text" 
                           placeholder="Enter keyword to monitor (e.g., #hashtag, @username, or keyword)"
                           class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                    @error('newKeyword') 
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button wire:click="addKeyword" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-sm">
                    <span wire:loading.remove wire:target="addKeyword">Add Keyword</span>
                    <span wire:loading wire:target="addKeyword">Adding...</span>
                </button>
            </div>
            <p class="text-sm text-gray-500 mt-2">Add keywords, hashtags (#hashtag), or usernames (@username) to monitor for mentions across Twitter.</p>
            <div class="text-xs text-gray-400 mt-1">
                <strong>Examples:</strong> laravel, #laravel, @laravel, "web development"
            </div>
        </div>

        <!-- Current Keywords -->
        @if(count($keywords) > 0)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Currently Monitoring:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($keywords as $index => $keyword)
                        <div class="flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-xl">
                            <span class="text-green-700 font-medium text-sm">{{ $keyword }}</span>
                            <button wire:click="removeKeyword({{ $index }})" 
                                    class="text-green-600 hover:text-green-800 transition-colors cursor-pointer">
                                <i class="bx bx-x text-sm"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="bx bx-search text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-600 mb-1">No keywords being monitored yet</p>
                <p class="text-sm text-gray-500">Add keywords above to start monitoring tweets</p>
            </div>
        @endif
    </div>
    @endif

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearMessage(); }, 4000)">
            <div class="flex items-center text-green-700">
                <i class="bx bx-check-circle mr-2 text-lg"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Advanced Search Toggle -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center mr-4">
                    <i class="bx bx-cog text-2xl text-gray-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Advanced Search</h3>
                    <p class="text-sm text-gray-500">Use powerful search operators and filters for precise results</p>
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model.live="advancedSearch" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                <span class="ml-3 text-sm font-medium text-gray-700">Enable Advanced Search</span>
            </label>
        </div>
    </div>

    <!-- Advanced Search Configuration Panel -->
    @if($advancedSearch)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mr-4">
                    <i class="bx bx-search-alt-2 text-2xl text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Configuration</p>
                    <h3 class="text-2xl font-semibold text-gray-900 mt-1">Advanced Search Configuration</h3>
                    <p class="text-sm text-gray-600 mt-1">Build complex queries with filters and operators</p>
                </div>
            </div>


            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <!-- Search Query -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-2">Search Query *</label>
                        <input type="text" 
                               wire:model="searchQuery" 
                               placeholder="Enter your search terms (e.g., laravel php, \"exact phrase\", -exclude)"
                               class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        <div class="text-xs text-gray-500 mt-1">
                            <p><strong>Supported operators:</strong> OR, "exact phrase", -exclude, (grouping)</p>
                            <p><strong>Examples:</strong> laravel php, "web development", -javascript, (react OR vue)</p>
                            <p><strong>Note:</strong> Use space for AND (implicit), OR for either, - for exclude</p>
                        </div>
                    </div>

                    <!-- Language Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-2">Language</label>
                        <select wire:model="language" class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <option value="">Any Language</option>
                            <option value="en">English</option>
                            <option value="es">Spanish</option>
                            <option value="fr">French</option>
                            <option value="de">German</option>
                            <option value="it">Italian</option>
                            <option value="pt">Portuguese</option>
                            <option value="ja">Japanese</option>
                            <option value="ko">Korean</option>
                            <option value="zh">Chinese</option>
                        </select>
                    </div>

                    <!-- User Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-2">From User</label>
                        <input type="text" 
                               wire:model="fromUser" 
                               placeholder="username (without @)"
                               class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                    </div>

                  
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <!-- Engagement Filters - Commented out for Basic API -->
                    {{-- <div class="space-y-3">
                        <h4 class="text-sm font-medium text-gray-700">Engagement Filters</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Min Likes</label>
                                <input type="number" 
                                       wire:model="minLikes" 
                                       placeholder="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Min Retweets</label>
                                <input type="number" 
                                       wire:model="minRetweets" 
                                       placeholder="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Min Replies</label>
                                <input type="number" 
                                       wire:model="minReplies" 
                                       placeholder="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div> --}}

                      <!-- Content Filters -->
                      <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Content Filters</h4>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="excludeRetweets" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Exclude Retweets</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="excludeReplies" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Exclude Replies</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="hasMedia" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Has Media (images/videos)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="hasLinks" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Has Links</span>
                            </label>
                            {{-- <label class="flex items-center">
                                <input type="checkbox" wire:model="isQuestion" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Question Tweets (?)</span>
                            </label> --}}
                            {{-- <label class="flex items-center">
                                <input type="checkbox" wire:model="isVerified" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Verified Users Only</span>
                            </label> --}}
                        </div>
                    </div>

                    <!-- Date Range - Commented out for Basic API -->
                    {{-- <div class="space-y-3">
                        <h4 class="text-sm font-medium text-gray-700">Date Range</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Since Date</label>
                                <input type="date" 
                                       wire:model="sinceDate" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       disabled>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Until Date</label>
                                <input type="date" 
                                       wire:model="untilDate" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       disabled>
                            </div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <i class="bx bx-info-circle text-yellow-600 text-sm mt-0.5 mr-2"></i>
                                <div class="text-xs text-yellow-800">
                                    <p><strong>Date filtering requires Elevated API access</strong></p>
                                    <p>Basic API only searches the last 7 days. Upgrade your Twitter API access to use date range filtering.</p>
                                </div>
                            </div>
                        </div>
                    </div> --}}

                    <!-- Location - Commented out for Basic API -->
                    {{-- <div class="space-y-3">
                        <h4 class="text-sm font-medium text-gray-700">Location</h4>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Near Location</label>
                            <input type="text" 
                                   wire:model="nearLocation" 
                                   placeholder="City, State, Country"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   disabled>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Within Radius</label>
                            <select wire:model="withinRadius" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled>
                                <option value="">Any Distance</option>
                                <option value="1mi">1 mile</option>
                                <option value="5mi">5 miles</option>
                                <option value="10mi">10 miles</option>
                                <option value="25mi">25 miles</option>
                                <option value="50mi">50 miles</option>
                                <option value="100mi">100 miles</option>
                            </select>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <i class="bx bx-info-circle text-yellow-600 text-sm mt-0.5 mr-2"></i>
                                <div class="text-xs text-yellow-800">
                                    <p><strong>Location filtering requires Elevated API access</strong></p>
                                    <p>Basic API does not support location-based search. Upgrade your Twitter API access to use location filtering.</p>
                                </div>
                            </div>
                        </div>
                    </div> --}}

                    <!-- Sentiment - Commented out for Basic API -->
                    {{-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sentiment</label>
                        <select wire:model="sentiment" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled>
                            <option value="">Sentiment not supported by Twitter API</option>
                            <option value="positive">Positive :)</option>
                            <option value="negative">Negative :(</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Sentiment filtering is not available in Twitter API v2</p>
                    </div> --}}
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-200">
                <div class="text-sm text-gray-600">
                    <strong>Query Preview:</strong> <code class="bg-gray-100 px-2 py-1 rounded-xl text-xs">{{ $this->buildAdvancedQuery() ?: 'Enter search terms to see query preview' }}</code>
                </div>
                <div class="flex gap-3">
                    <button wire:click="resetAdvancedSearch" 
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        <i class="bx bx-reset"></i>
                        Reset
                    </button>
                    <button wire:click="performAdvancedSearchAction" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-6 py-2 text-sm font-semibold text-white bg-green-600 rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50 shadow-sm">
                        <i class="bx bx-search" wire:loading.remove wire:target="performAdvancedSearchAction"></i>
                        <i class="bx bx-loader-alt animate-spin" wire:loading wire:target="performAdvancedSearchAction"></i>
                        <span wire:loading.remove wire:target="performAdvancedSearchAction">Search</span>
                        <span wire:loading wire:target="performAdvancedSearchAction">Searching...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearMessage(); }, 10000)">
            <div class="flex items-start">
                <i class="bx bx-error-circle mr-2 text-lg text-red-600"></i>
                <div class="flex-1">
                    <p class="text-red-700">{{ $errorMessage }}</p>
                    @if(str_contains($errorMessage, 'Rate limit exceeded'))
                        <p class="text-sm mt-2 text-red-600">
                            <i class="bx bx-info-circle mr-1"></i>
                            Twitter API has rate limits to prevent abuse. Try again in a few minutes.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Search Loading State -->
    @if($searchLoading)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-6"></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-4">
                {{ $advancedSearch ? 'Advanced Search in Progress...' : 'Searching Tweets...' }}
            </h3>
            <p class="text-gray-600 mb-4">{{ $advancedSearch ? "Looking for tweets that match your advanced search criteria" : "Looking for tweets containing your monitored keywords" }}</p>
            <p class="text-xs text-gray-500">Using cached data when available to avoid rate limits</p>
            @if($advancedSearch && !empty($searchQuery))
                <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 mt-4">
                    <p class="text-sm text-green-800">
                        <strong>Search Query:</strong> <code class="bg-green-100 px-2 py-1 rounded-xl text-xs">{{ $searchQuery }}</code>
                    </p>
                    <p class="text-xs text-green-600 mt-1">
                        <strong>Full Query:</strong> <code class="bg-green-100 px-2 py-1 rounded-xl text-xs">{{ $this->buildAdvancedQuery() }}</code>
                    </p>
                </div>
            @endif
        </div>
    @elseif(count($tweets) > 0)
        <!-- Tweets List -->
        <div class="space-y-4">
            @foreach($paginatedTweets as $tweet)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-4">
                                @php
                                    $tweetId = is_object($tweet) ? $tweet->id : $tweet['id'];
                                    $authorId = is_object($tweet) ? $tweet->author_id : $tweet['author_id'];
                                    $text = is_object($tweet) ? $tweet->text : $tweet['text'];
                                    $date = is_object($tweet) ? ($tweet->created_at ?? $tweet->timestamp ?? $tweet->date ?? null) : ($tweet['created_at'] ?? $tweet['timestamp'] ?? $tweet['date'] ?? null);
                                @endphp
                                <span class="font-semibold text-gray-500 text-md">{{ $authorId ?? 'Unknown User' }}</span>
                                <span class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded-lg">
                                    {{ $date ? \Carbon\Carbon::parse($date)->diffForHumans() : 'Unknown time' }}
                                </span>
                                <span class="text-xs text-green-600 bg-green-50 border border-green-200 px-2 py-1 rounded-lg">
                                    Keyword Match
                                </span>
                            </div>
                            <p class="text-gray-800 mb-4 text-md leading-relaxed">{{ $text ?? 'No content' }}</p>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <button wire:click="replyToTweet('{{ $tweetId }}')" 
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer border border-gray-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                    Reply
                                </button>
                                <button wire:click="likeTweet('{{ $tweetId }}')" 
                                        class="like-button inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-all duration-300 cursor-pointer relative overflow-hidden group border border-red-200"
                                        x-data="{ 
                                            liked: false, 
                                            animating: false,
                                            likeTweet() {
                                                if (this.animating) return;
                                                this.animating = true;
                                                this.liked = true;
                                                $wire.likeTweet('{{ $tweetId }}');
                                                setTimeout(() => {
                                                    this.liked = false;
                                                    this.animating = false;
                                                }, 2000);
                                            }
                                        }"
                                        @click="likeTweet()"
                                        :class="{ 'animate-pulse': animating, 'scale-105': liked }">
                                    <!-- Heart Icon with Animation -->
                                    <div class="relative inline-flex items-center gap-1">
                                        <!-- Empty Heart (default) -->
                                        <svg x-show="!liked" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                        </svg>
                                        
                                        <!-- Filled Heart (when liked) -->
                                        <svg x-show="liked" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300 animate-bounce text-red-600">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                        </svg>
                                        
                                        <!-- Sparkle Effect -->
                                        <div x-show="liked" class="absolute inset-0 pointer-events-none">
                                            <div class="absolute -top-1 -right-1 w-2 h-2 bg-yellow-400 rounded-full animate-ping"></div>
                                            <div class="absolute -bottom-1 -left-1 w-1.5 h-1.5 bg-pink-400 rounded-full animate-ping" style="animation-delay: 0.1s"></div>
                                            <div class="absolute top-0 left-1/2 w-1 h-1 bg-red-400 rounded-full animate-ping" style="animation-delay: 0.2s"></div>
                                        </div>
                                    </div>
                                    
                                    <span x-text="liked ? 'Liked!' : 'Like'" class="transition-all duration-300"></span>
                                    
                                    <!-- Ripple Effect -->
                                    <div class="absolute inset-0 rounded-xl overflow-hidden">
                                        <div x-show="liked" class="absolute inset-0 bg-red-200 opacity-30 animate-ping"></div>
                                    </div>
                                </button>
                                <button wire:click="retweetTweet('{{ $tweetId }}')" 
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded-xl transition-all duration-300 cursor-pointer relative overflow-hidden border border-green-200"
                                        x-data="{ 
                                            retweeted: false, 
                                            animating: false,
                                            retweetTweet() {
                                                if (this.animating) return;
                                                this.animating = true;
                                                this.retweeted = true;
                                                $wire.retweetTweet('{{ $tweetId }}');
                                                setTimeout(() => {
                                                    this.retweeted = false;
                                                    this.animating = false;
                                                }, 2000);
                                            }
                                        }"
                                        @click="retweetTweet()"
                                        :class="{ 'animate-pulse': animating, 'scale-105': retweeted }">
                                    <!-- Retweet Icon with Animation -->
                                    <div class="relative inline-flex items-center gap-1">
                                        <!-- Default Retweet Icon -->
                                        <svg x-show="!retweeted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0 3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                        </svg>
                                        
                                        <!-- Animated Retweet Icon -->
                                        <svg x-show="retweeted" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300 animate-spin text-green-600">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0 3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                        </svg>
                                        
                                        <!-- Success Sparkles -->
                                        <div x-show="retweeted" class="absolute inset-0 pointer-events-none">
                                            <div class="absolute -top-1 -right-1 w-2 h-2 bg-green-400 rounded-full animate-ping"></div>
                                            <div class="absolute -bottom-1 -left-1 w-1.5 h-1.5 bg-emerald-400 rounded-full animate-ping" style="animation-delay: 0.1s"></div>
                                            <div class="absolute top-0 left-1/2 w-1 h-1 bg-lime-400 rounded-full animate-ping" style="animation-delay: 0.2s"></div>
                                        </div>
                                    </div>
                                    
                                    <span x-text="retweeted ? 'Retweeted!' : 'Retweet'" class="transition-all duration-300"></span>
                                    
                                    <!-- Ripple Effect -->
                                    <div class="absolute inset-0 rounded-xl overflow-hidden">
                                        <div x-show="retweeted" class="absolute inset-0 bg-green-200 opacity-30 animate-ping"></div>
                                    </div>
                                </button>
                                <a href="https://twitter.com/i/status/{{ $tweetId }}" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer border border-gray-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                    View Tweet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination Controls -->
        @if($totalPages > 1)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-center space-x-3 mb-4">
                    <!-- Previous Page Button -->
                    <button wire:click="previousPage" 
                            wire:loading.attr="disabled"
                            @if($currentPage <= 1) disabled @endif
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-xl hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer border border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        Previous
                    </button>

                    <!-- Page Numbers -->
                    <div class="flex items-center space-x-2">
                        @for($page = 1; $page <= $totalPages; $page++)
                            @if($page == 1 || $page == $totalPages || ($page >= $currentPage - 1 && $page <= $currentPage + 1))
                                <button wire:click="goToPage({{ $page }})" 
                                        @if($page == $currentPage) disabled @endif
                                        class="px-4 py-2 text-sm font-medium rounded-xl transition-colors {{ $page == $currentPage ? 'bg-green-600 text-white shadow-sm' : 'text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200' }}">
                                    {{ $page }}
                                </button>
                            @elseif($page == $currentPage - 2 || $page == $currentPage + 2)
                                <span class="px-3 py-2 text-gray-400">...</span>
                            @endif
                        @endfor
                    </div>

                    <!-- Next Page Button -->
                    <button wire:click="nextPage" 
                            wire:loading.attr="disabled"
                            @if($currentPage >= $totalPages) disabled @endif
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-xl hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer border border-gray-200">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>

                <!-- Page Info -->
                <div class="text-center text-sm text-gray-600">
                    Page {{ $currentPage }} of {{ $totalPages }}
                    <span class="text-gray-400 mx-2">•</span>
                    Showing {{ count($paginatedTweets) }} of {{ count($tweets) }} tweets
                </div>
            </div>
        @endif
    @elseif(count($keywords) > 0)
        <!-- Empty State - Keywords set but no tweets found -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-search text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Tweets Found</h3>
            <p class="text-gray-600 mb-1">No tweets found containing your monitored keywords</p>
            <p class="text-sm text-gray-500">Try adding more keywords or check back later for new tweets</p>
        </div>
    @else
        <!-- Empty State - No keywords set -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-search text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Start Monitoring Keywords</h3>
            <p class="text-gray-600 mb-1">Add keywords above to start monitoring tweets</p>
            <p class="text-sm text-gray-500">Monitor hashtags, usernames, or any keywords you're interested in</p>
        </div>
    @endif

    <!-- Reply Modal -->
    @if($showReplyModal && $selectedTweet)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-3xl p-8 w-full max-w-lg shadow-xl">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-green-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Reply</p>
                        <h3 class="text-2xl font-semibold text-gray-900">Reply to Tweet</h3>
                    </div>
                </div>
                
                <div class="mb-6 p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <p class="text-sm text-gray-600 mb-2 font-semibold">Replying to:</p>
                    @php
                        $selectedText = is_object($selectedTweet) ? $selectedTweet->text : $selectedTweet['text'];
                    @endphp
                    <p class="text-gray-800 text-sm leading-relaxed">{{ $selectedText ?? 'No content' }}</p>
                </div>

                <div class="mb-8">
                    <label for="replyContent" class="block text-sm font-semibold text-gray-800 mb-2">Your Reply</label>
                    <textarea wire:model="replyContent" id="replyContent" rows="5"
                              class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
                              placeholder="Type your reply..."></textarea>
                    <div class="text-right mt-2">
                        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-xl">{{ strlen($replyContent) }}/280</span>
                    </div>
                    @error('replyContent') 
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <button wire:click="sendReply" 
                            wire:loading.attr="disabled"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-sm">
                        <span wire:loading.remove wire:target="sendReply">Send Reply</span>
                        <span wire:loading wire:target="sendReply">Sending...</span>
                    </button>
                    <button wire:click="cancelReply" 
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-2xl hover:bg-gray-200 transition-colors cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

