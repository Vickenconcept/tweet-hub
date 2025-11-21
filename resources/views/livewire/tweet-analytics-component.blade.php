<div x-data="{ init() { setTimeout(() => $wire.loadRecentTweets(), 3000); } }" class="space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">Analytics</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    Tweet Analytics
                </h1>
                <p class="text-gray-500 mt-2 text-sm md:text-base">
                    <i class="bx bx-bar-chart-alt-2 mr-1 text-green-600"></i>
                    Analyze engagement metrics and interactions
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <button wire:click="clearCache" 
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-colors">
                    <i class="bx bx-refresh text-lg"></i>
                    Clear Cache
                </button>
                <button wire:click="loadRecentTweets" 
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-colors">
                    <i class="bx bx-refresh text-lg"></i>
                    <span wire:loading.remove wire:target="loadRecentTweets">Refresh</span>
                    <span wire:loading wire:target="loadRecentTweets">Loading...</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Error/Success Messages -->
    @if($errorMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4">
            <div class="flex items-start">
                <i class="bx bx-error-circle mr-2 text-lg text-red-600"></i>
                <div class="flex-1">
                    <p class="text-red-700">{{ $errorMessage }}</p>
                    @if(strpos($errorMessage, 'Rate limit exceeded') !== false)
                        <p class="text-sm mt-2 text-red-600">
                            <i class="bx bx-info-circle mr-1"></i>
                            Twitter API has rate limits to prevent abuse. Try again in a few minutes.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($successMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4">
            <div class="flex items-center text-green-700">
                <i class="bx bx-check-circle mr-2 text-lg"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif
    
    <!-- Tweet Selection Section -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Select Tweet</p>
            <h3 class="text-2xl font-semibold text-gray-900 mt-2 flex items-center">
                <div class="w-10 h-10 bg-green-50 rounded-2xl flex items-center justify-center mr-3">
                    <i class="bx bx-twitter text-xl text-green-600"></i>
                </div>
                Select Tweet to Analyze
            </h3>
        </div>
        
        @if($loading && empty($recentTweets))
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-4"></div>
                <p class="text-gray-600">Loading your recent tweets...</p>
            </div>
            @elseif(count($recentTweets) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                     @foreach($recentTweets as $tweet)
                         @php
                             // Safe property access with existence checks
                             $tweetId = null;
                             $tweetText = 'No content available';
                             $tweetCreatedAt = now();
                             $publicMetrics = null;
                             $likeCount = 0;
                             $retweetCount = 0;
                             $replyCount = 0;
                             $quoteCount = 0;
                             
                             if (is_object($tweet)) {
                                 $tweetId = $tweet->id ?? null;
                                 $tweetText = $tweet->text ?? 'No content available';
                                 $tweetCreatedAt = $tweet->created_at ?? now();
                                 $publicMetrics = $tweet->public_metrics ?? null;
                             } else if (is_array($tweet)) {
                                 $tweetId = $tweet['id'] ?? null;
                                 $tweetText = $tweet['text'] ?? 'No content available';
                                 $tweetCreatedAt = $tweet['created_at'] ?? now();
                                 $publicMetrics = $tweet['public_metrics'] ?? null;
                             }
                             
                             // Extract metrics safely
                             if ($publicMetrics) {
                                 if (is_object($publicMetrics)) {
                                     $likeCount = $publicMetrics->like_count ?? 0;
                                     $retweetCount = $publicMetrics->retweet_count ?? 0;
                                     $replyCount = $publicMetrics->reply_count ?? 0;
                                     $quoteCount = $publicMetrics->quote_count ?? 0;
                                 } else if (is_array($publicMetrics)) {
                                     $likeCount = $publicMetrics['like_count'] ?? 0;
                                     $retweetCount = $publicMetrics['retweet_count'] ?? 0;
                                     $replyCount = $publicMetrics['reply_count'] ?? 0;
                                     $quoteCount = $publicMetrics['quote_count'] ?? 0;
                                 }
                             }
                             
                             // Check if tweet is selected
                             $isSelected = false;
                             if ($selectedTweet && $tweetId) {
                                 $selectedTweetId = is_object($selectedTweet) ? ($selectedTweet->id ?? null) : ($selectedTweet['id'] ?? null);
                                 $isSelected = $selectedTweetId === $tweetId;
                             }
                         @endphp
                         @if($tweetId)
                         <div class="border border-gray-200 rounded-2xl p-4 hover:border-green-300 transition-colors cursor-pointer {{ $isSelected ? 'border-green-500 bg-green-50 shadow-sm' : '' }}"
                              wire:click="analyzeTweet('{{ $tweetId }}')">
                             <div class="flex items-start justify-between mb-2">
                                 <span class="text-xs text-gray-500 flex items-center gap-1">
                                     <i class="bx bx-time"></i>
                                     {{ \Carbon\Carbon::parse($tweetCreatedAt)->diffForHumans() }}
                                 </span>
                                 @if($isSelected)
                                     <i class="bx bx-check-circle text-green-600 text-lg"></i>
                                 @endif
                             </div>
                             <p class="text-sm text-gray-800 mb-3 line-clamp-3">
                                 {{ $tweetText }}
                             </p>
                             <div class="flex items-center gap-4 text-xs text-gray-500">
                                 <span class="flex items-center">
                                     <i class="bx bx-like mr-1"></i>
                                     {{ $likeCount }}
                                 </span>
                                 <span class="flex items-center">
                                     <i class="bx bx-refresh mr-1"></i>
                                     {{ $retweetCount }}
                                 </span>
                                 <span class="flex items-center">
                                     <i class="bx bx-message mr-1"></i>
                                     {{ $replyCount }}
                                 </span>
                                 <span class="flex items-center">
                                     <i class="bx bx-quote-left mr-1"></i>
                                     {{ $quoteCount }}
                                 </span>
                             </div>
                         </div>
                         @else
                         <!-- Fallback for tweets without ID -->
                         <div class="border border-yellow-200 rounded-lg p-4 bg-yellow-50">
                             <div class="flex items-start justify-between mb-2">
                                 <span class="text-xs text-yellow-600">Invalid Tweet Data</span>
                                 <i class="bx bx-error text-yellow-500"></i>
                             </div>
                             <p class="text-sm text-gray-800 mb-3">
                                 {{ $tweetText }}
                             </p>
                             <div class="text-xs text-yellow-600">
                                 This tweet has missing or invalid data structure
                             </div>
                         </div>
                         @endif
                     @endforeach
                </div>
        @else
            <div class="text-center py-12 text-gray-500">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                    <i class="bx bx-twitter text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No recent tweets found</h3>
                <p class="text-gray-600 mb-1">Start tweeting to see analytics here</p>
            </div>
        @endif
    </div>
    
    <!-- Analytics Section -->
    @if($selectedTweet)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
             @php
                     // Safely extract tweet data with comprehensive null checks
                     $selectedTweetText = 'No content available';
                     $selectedLikeCount = 0;
                     $selectedRetweetCount = 0;
                     $selectedReplyCount = 0;
                     $selectedQuoteCount = 0;
                     
                     if (is_object($selectedTweet)) {
                         $selectedTweetText = $selectedTweet->text ?? 'No content available';
                         $selectedTweetMetrics = $selectedTweet->public_metrics ?? null;
                         if ($selectedTweetMetrics) {
                             $selectedLikeCount = $selectedTweetMetrics->like_count ?? 0;
                             $selectedRetweetCount = $selectedTweetMetrics->retweet_count ?? 0;
                             $selectedReplyCount = $selectedTweetMetrics->reply_count ?? 0;
                             $selectedQuoteCount = $selectedTweetMetrics->quote_count ?? 0;
                         }
                     } elseif (is_array($selectedTweet)) {
                         $selectedTweetText = $selectedTweet['text'] ?? 'No content available';
                         $selectedTweetMetrics = $selectedTweet['public_metrics'] ?? null;
                         if ($selectedTweetMetrics) {
                             if (is_object($selectedTweetMetrics)) {
                                 $selectedLikeCount = $selectedTweetMetrics->like_count ?? 0;
                                 $selectedRetweetCount = $selectedTweetMetrics->retweet_count ?? 0;
                                 $selectedReplyCount = $selectedTweetMetrics->reply_count ?? 0;
                                 $selectedQuoteCount = $selectedTweetMetrics->quote_count ?? 0;
                             } else {
                                 $selectedLikeCount = $selectedTweetMetrics['like_count'] ?? 0;
                                 $selectedRetweetCount = $selectedTweetMetrics['retweet_count'] ?? 0;
                                 $selectedReplyCount = $selectedTweetMetrics['reply_count'] ?? 0;
                                 $selectedQuoteCount = $selectedTweetMetrics['quote_count'] ?? 0;
                             }
                         }
                     }
                 @endphp
             <div class="mb-6">
                 <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Analytics</p>
                 <h3 class="text-2xl font-semibold text-gray-900 mt-2 flex items-center">
                     <div class="w-10 h-10 bg-green-50 rounded-2xl flex items-center justify-center mr-3">
                         <i class="bx bx-bar-chart-alt-2 text-xl text-green-600"></i>
                     </div>
                     Analytics for Selected Tweet
                 </h3>
                 <p class="text-sm text-gray-600 mt-2">{{ Str::limit($selectedTweetText, 100) }}</p>
             </div>
             
             <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                 <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                     <div class="flex items-center gap-2 mb-1">
                         <i class="bx bx-like text-red-500"></i>
                         <span class="text-sm font-semibold text-gray-700">Likes</span>
                     </div>
                     <p class="text-2xl font-bold text-gray-900">{{ $selectedLikeCount }}</p>
                 </div>
                 <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                     <div class="flex items-center gap-2 mb-1">
                         <i class="bx bx-refresh text-green-500"></i>
                         <span class="text-sm font-semibold text-gray-700">Retweets</span>
                     </div>
                     <p class="text-2xl font-bold text-gray-900">{{ $selectedRetweetCount }}</p>
                 </div>
                 <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                     <div class="flex items-center gap-2 mb-1">
                         <i class="bx bx-message text-blue-500"></i>
                         <span class="text-sm font-semibold text-gray-700">Replies</span>
                     </div>
                     <p class="text-2xl font-bold text-gray-900">{{ $selectedReplyCount }}</p>
                 </div>
                 <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                     <div class="flex items-center gap-2 mb-1">
                         <i class="bx bx-quote-left text-purple-500"></i>
                         <span class="text-sm font-semibold text-gray-700">Quotes</span>
                     </div>
                     <p class="text-2xl font-bold text-gray-900">{{ $selectedQuoteCount }}</p>
                 </div>
             </div>
    
            <!-- Tabs -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-2 mb-6">
                <div class="flex space-x-1">
                     <button wire:click="setTab('likes')" 
                             class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'likes' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                         <i class="bx bx-like mr-2"></i>
                         Who Liked ({{ $selectedLikeCount }})
                     </button>
                     <button wire:click="setTab('quotes')" 
                             class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'quotes' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                         <i class="bx bx-quote-left mr-2"></i>
                         Quote Tweets ({{ $selectedQuoteCount }})
                     </button>
                     <button wire:click="setTab('replies')" 
                             class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'replies' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                         <i class="bx bx-message mr-2"></i>
                         Replies ({{ $selectedReplyCount }})
                     </button>
                </div>
            </div>
    
            <!-- Analytics Content -->
            @if($loading)
                <div class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-4"></div>
                    <p class="text-gray-600">Loading {{ $activeTab }} data...</p>
                </div>
                @else
                    @php $currentData = $this->getCurrentData(); @endphp
                    
                    @if(count($currentData) > 0)
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="font-semibold text-gray-900">
                                    {{ ucfirst(str_replace('-', ' ', $activeTab)) }} 
                                    ({{ count($currentData) }})
                                </h4>
                            </div>
    
                             @if($activeTab === 'likes')
                                 <!-- Users Who Liked -->
                                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                     @foreach($currentData as $user)
                                         @php
                                             $userName = is_object($user) ? $user->name : $user['name'];
                                             $userUsername = is_object($user) ? $user->username : $user['username'];
                                             $userProfileImage = is_object($user) ? ($user->profile_image_url ?? null) : ($user['profile_image_url'] ?? null);
                                             $userDescription = is_object($user) ? ($user->description ?? null) : ($user['description'] ?? null);
                                             $userMetrics = is_object($user) ? ($user->public_metrics ?? null) : ($user['public_metrics'] ?? null);
                                             $followersCount = $userMetrics ? (is_object($userMetrics) ? $userMetrics->followers_count : $userMetrics['followers_count']) : 0;
                                             $followingCount = $userMetrics ? (is_object($userMetrics) ? $userMetrics->following_count : $userMetrics['following_count']) : 0;
                                         @endphp
                                         <div class="bg-white border border-gray-200 rounded-2xl p-4 hover:border-green-300 hover:shadow-sm transition-all">
                                             <div class="flex items-center space-x-3">
                                                 @if($userProfileImage)
                                                     <img src="{{ $userProfileImage }}" 
                                                          alt="{{ $userName }}" 
                                                          class="w-10 h-10 rounded-full">
                                                 @else
                                                     <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                                         <i class="bx bx-user text-gray-600"></i>
                                                     </div>
                                                 @endif
                                                 <div class="flex-1 min-w-0">
                                                     <p class="text-sm font-medium text-gray-900 truncate">
                                                         {{ $userName }}
                                                     </p>
                                                     <p class="text-sm text-gray-500 truncate">
                                                         <span>@</span>{{ $userUsername }}
                                                     </p>
                                                     @if($userMetrics)
                                                         <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                                             <span>{{ $followersCount }} followers</span>
                                                             <span>{{ $followingCount }} following</span>
                                                         </div>
                                                     @endif
                                                 </div>
                                             </div>
                                             @if($userDescription)
                                                 <p class="text-xs text-gray-600 mt-2 line-clamp-2">
                                                     {{ $userDescription }}
                                                 </p>
                                             @endif
                                         </div>
                                     @endforeach
                                 </div>
    
                            @elseif($activeTab === 'quotes')
                                <!-- Quote Tweets -->
                                <div class="space-y-4">
                                    @foreach($currentData as $quote)
                                        @php
                                            $quoteText = is_object($quote) ? $quote->text : $quote['text'];
                                            $quoteCreatedAt = is_object($quote) ? $quote->created_at : $quote['created_at'];
                                            $quoteAuthorId = is_object($quote) ? ($quote->author_id ?? null) : ($quote['author_id'] ?? null);
                                            $quoteMetrics = is_object($quote) ? ($quote->public_metrics ?? null) : ($quote['public_metrics'] ?? null);
                                            $quoteLikeCount = $quoteMetrics ? (is_object($quoteMetrics) ? $quoteMetrics->like_count : $quoteMetrics['like_count']) : 0;
                                            $quoteRetweetCount = $quoteMetrics ? (is_object($quoteMetrics) ? $quoteMetrics->retweet_count : $quoteMetrics['retweet_count']) : 0;
                                            $quoteReplyCount = $quoteMetrics ? (is_object($quoteMetrics) ? $quoteMetrics->reply_count : $quoteMetrics['reply_count']) : 0;
                                        @endphp
                                        <div class="bg-white border border-gray-200 rounded-2xl p-4 hover:border-green-300 hover:shadow-sm transition-all">
                                            <div class="flex items-start space-x-3">
                                                @if($quoteAuthorId)
                                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                                                        <i class="bx bx-user text-gray-600 text-sm"></i>
                                                    </div>
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-gray-800 mb-2">
                                                        {{ $quoteText }}
                                                    </p>
                                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                                        <span class="flex items-center">
                                                            <i class="bx bx-like mr-1"></i>
                                                            {{ $quoteLikeCount }}
                                                        </span>
                                                        <span class="flex items-center">
                                                            <i class="bx bx-refresh mr-1"></i>
                                                            {{ $quoteRetweetCount }}
                                                        </span>
                                                        <span class="flex items-center">
                                                            <i class="bx bx-message mr-1"></i>
                                                            {{ $quoteReplyCount }}
                                                        </span>
                                                        <span class="text-gray-400">
                                                            {{ \Carbon\Carbon::parse($quoteCreatedAt)->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
    
                            @elseif($activeTab === 'replies')
                                <!-- Replies -->
                                <div class="space-y-4">
                                    @foreach($currentData as $reply)
                                        @php
                                            $replyText = is_object($reply) ? $reply->text : $reply['text'];
                                            $replyCreatedAt = is_object($reply) ? $reply->created_at : $reply['created_at'];
                                            $replyAuthorId = is_object($reply) ? ($reply->author_id ?? null) : ($reply['author_id'] ?? null);
                                            $replyMetrics = is_object($reply) ? ($reply->public_metrics ?? null) : ($reply['public_metrics'] ?? null);
                                            $replyLikeCount = $replyMetrics ? (is_object($replyMetrics) ? $replyMetrics->like_count : $replyMetrics['like_count']) : 0;
                                            $replyRetweetCount = $replyMetrics ? (is_object($replyMetrics) ? $replyMetrics->retweet_count : $replyMetrics['retweet_count']) : 0;
                                            $replyReplyCount = $replyMetrics ? (is_object($replyMetrics) ? $replyMetrics->reply_count : $replyMetrics['reply_count']) : 0;
                                        @endphp
                                        <div class="bg-white border border-gray-200 rounded-2xl p-4 hover:border-green-300 hover:shadow-sm transition-all">
                                            <div class="flex items-start space-x-3">
                                                @if($replyAuthorId)
                                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                                                        <i class="bx bx-user text-gray-600 text-sm"></i>
                                                    </div>
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-gray-800 mb-2">
                                                        {{ $replyText }}
                                                    </p>
                                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                                        <span class="flex items-center">
                                                            <i class="bx bx-like mr-1"></i>
                                                            {{ $replyLikeCount }}
                                                        </span>
                                                        <span class="flex items-center">
                                                            <i class="bx bx-refresh mr-1"></i>
                                                            {{ $replyRetweetCount }}
                                                        </span>
                                                        <span class="flex items-center">
                                                            <i class="bx bx-message mr-1"></i>
                                                            {{ $replyReplyCount }}
                                                        </span>
                                                        <span class="text-gray-400">
                                                            {{ \Carbon\Carbon::parse($replyCreatedAt)->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <i class="bx bx-{{ $activeTab === 'likes' ? 'like' : ($activeTab === 'quotes' ? 'quote-left' : 'message') }} text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No {{ $activeTab }} found</h3>
                    <p class="text-gray-600 mb-1">This tweet has no {{ $activeTab }} yet</p>
                </div>
                    @endif
                @endif
            </div>
    @else
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-bar-chart-alt-2 text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Select a Tweet to Analyze</h3>
            <p class="text-gray-600 mb-1">Choose a tweet from your recent posts to view detailed analytics</p>
            <p class="text-sm text-gray-500">See who liked, quoted, or replied to your tweets</p>
        </div>
    @endif
    
    <style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    </style>
    
</div>