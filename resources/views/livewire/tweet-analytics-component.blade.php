<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Tweet Analytics</h2>
                <p class="text-sm text-gray-600 mt-1">Analyze engagement metrics and interactions</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="clearCache" 
                    class="px-4 py-2 text-sm font-medium text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 transition-colors cursor-pointer">
                    <i class="bx bx-refresh mr-1"></i>
                    Clear Cache
                </button>
                <button wire:click="loadRecentTweets" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 rounded-xl hover:bg-blue-200 transition-colors cursor-pointer">
                    <i class="bx bx-refresh mr-1"></i>
                    <span wire:loading.remove wire:target="loadRecentTweets">Refresh</span>
                    <span wire:loading wire:target="loadRecentTweets">Loading...</span>
                </button>
            </div>
        </div>
    
        <!-- Error/Success Messages -->
         @if($errorMessage)
             <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">
                 <div class="flex items-center">
                     <i class="bx bx-error text-xl mr-2"></i>
                     <div>
                         <span>{{ $errorMessage }}</span>
                         @if(strpos($errorMessage, 'Rate limit exceeded') !== false)
                             <div class="mt-2 text-sm text-red-600">
                                 <i class="bx bx-info-circle mr-1"></i>
                                 Twitter API has rate limits to prevent abuse. Try again in a few minutes.
                             </div>
                             <div class="mt-3">
                                 <button wire:click="loadRecentTweets" 
                                         class="px-4 py-2 text-sm font-medium text-red-700 bg-red-200 rounded-lg hover:bg-red-300 transition-colors">
                                     <i class="bx bx-refresh mr-1"></i>
                                     Try Again
                                 </button>
                             </div>
                         @endif
                     </div>
                 </div>
             </div>
         @endif
    
        @if($successMessage)
            <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
                <div class="flex items-center">
                    <i class="bx bx-check-circle text-xl mr-2"></i>
                    <span>{{ $successMessage }}</span>
                </div>
            </div>
        @endif
    
        <!-- Tweet Selection Section -->
        <div class="bg-white rounded-xl shadow-2xl shadow-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Tweet to Analyze</h3>
            
            @if($loading && empty($recentTweets))
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Loading your recent tweets...</p>
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
                         <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors cursor-pointer {{ $isSelected ? 'border-blue-500 bg-blue-50' : '' }}"
                              wire:click="analyzeTweet('{{ $tweetId }}')">
                             <div class="flex items-start justify-between mb-2">
                                 <span class="text-xs text-gray-500">
                                     {{ \Carbon\Carbon::parse($tweetCreatedAt)->diffForHumans() }}
                                 </span>
                                 @if($isSelected)
                                     <i class="bx bx-check-circle text-blue-500"></i>
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
                <div class="text-center py-8 text-gray-500">
                    <i class="bx bx-twitter text-4xl mb-4"></i>
                    <p>No recent tweets found</p>
                    <p class="text-sm">Start tweeting to see analytics here</p>
                </div>
            @endif
        </div>
    
        <!-- Analytics Section -->
        @if($selectedTweet)
            <div class="bg-white rounded-xl shadow-2xl shadow-gray-200 p-6">
                 @php
                     $selectedTweetText = is_object($selectedTweet) ? $selectedTweet->text : $selectedTweet['text'];
                     $selectedTweetMetrics = is_object($selectedTweet) ? ($selectedTweet->public_metrics ?? null) : ($selectedTweet['public_metrics'] ?? null);
                     $selectedLikeCount = $selectedTweetMetrics ? (is_object($selectedTweetMetrics) ? $selectedTweetMetrics->like_count : $selectedTweetMetrics['like_count']) : 0;
                     $selectedRetweetCount = $selectedTweetMetrics ? (is_object($selectedTweetMetrics) ? $selectedTweetMetrics->retweet_count : $selectedTweetMetrics['retweet_count']) : 0;
                     $selectedReplyCount = $selectedTweetMetrics ? (is_object($selectedTweetMetrics) ? $selectedTweetMetrics->reply_count : $selectedTweetMetrics['reply_count']) : 0;
                     $selectedQuoteCount = $selectedTweetMetrics ? (is_object($selectedTweetMetrics) ? $selectedTweetMetrics->quote_count : $selectedTweetMetrics['quote_count']) : 0;
                 @endphp
                 <div class="flex items-center justify-between mb-6">
                     <div>
                         <h3 class="text-lg font-semibold text-gray-900">Analytics for Selected Tweet</h3>
                         <p class="text-sm text-gray-600 mt-1">{{ Str::limit($selectedTweetText, 100) }}</p>
                     </div>
                     <div class="flex items-center gap-4 text-sm text-gray-600">
                         <span class="flex items-center">
                             <i class="bx bx-like mr-1 text-red-500"></i>
                             {{ $selectedLikeCount }} Likes
                         </span>
                         <span class="flex items-center">
                             <i class="bx bx-refresh mr-1 text-blue-500"></i>
                             {{ $selectedRetweetCount }} Retweets
                         </span>
                         <span class="flex items-center">
                             <i class="bx bx-message mr-1 text-green-500"></i>
                             {{ $selectedReplyCount }} Replies
                         </span>
                         <span class="flex items-center">
                             <i class="bx bx-quote-left mr-1 text-purple-500"></i>
                             {{ $selectedQuoteCount }} Quotes
                         </span>
                     </div>
                 </div>
    
                <!-- Tabs -->
                <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-xl">
                     <button wire:click="setTab('likes')" 
                             class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer {{ $activeTab === 'likes' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
                         <i class="bx bx-like mr-2"></i>
                         Who Liked ({{ $selectedLikeCount }})
                     </button>
                     <button wire:click="setTab('quotes')" 
                             class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer {{ $activeTab === 'quotes' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
                         <i class="bx bx-quote-left mr-2"></i>
                         Quote Tweets ({{ $selectedQuoteCount }})
                     </button>
                     <button wire:click="setTab('replies')" 
                             class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer {{ $activeTab === 'replies' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
                         <i class="bx bx-message mr-2"></i>
                         Replies ({{ $selectedReplyCount }})
                     </button>
                </div>
    
                <!-- Analytics Content -->
                @if($loading)
                    <div class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-gray-600">Loading {{ $activeTab }} data...</p>
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
                                         <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
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
                                                         @{{ $userUsername }}
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
                                        <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
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
                                        <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
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
                        <div class="text-center py-8 text-gray-500">
                            <i class="bx bx-{{ $activeTab === 'likes' ? 'like' : ($activeTab === 'quotes' ? 'quote-left' : 'message') }} text-4xl mb-4"></i>
                            <p>No {{ $activeTab }} found</p>
                            <p class="text-sm">This tweet has no {{ $activeTab }} yet</p>
                        </div>
                    @endif
                @endif
            </div>
        @else
            <div class="bg-white rounded-xl shadow-2xl shadow-gray-200 p-8">
                <div class="text-center py-12 text-gray-500">
                    <i class="bx bx-bar-chart text-6xl mb-6 text-blue-500"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Select a Tweet to Analyze</h3>
                    <p class="text-gray-600 mb-4">Choose a tweet from your recent posts to view detailed analytics</p>
                    <p class="text-xs text-gray-500">See who liked, quoted, or replied to your tweets</p>
                </div>
            </div>
        @endif
    </div>
    
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