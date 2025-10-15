<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">📚 Bookmarks Management</h2>
                <p class="text-sm text-gray-600 mt-1">Save, organize, and manage your favorite tweets</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="clearCache" 
                    class="px-4 py-2 text-sm font-medium text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 transition-colors cursor-pointer">
                    <i class="bx bx-refresh mr-1"></i>
                    Clear Cache
                </button>
                <button wire:click="$set('showAddBookmarkModal', true)" 
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-colors cursor-pointer">
                    <i class="bx bx-plus mr-1"></i>
                    Add Bookmark
                </button>
                <button wire:click="loadBookmarks" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 rounded-xl hover:bg-blue-200 transition-colors cursor-pointer">
                    <i class="bx bx-refresh mr-1"></i>
                    <span wire:loading.remove wire:target="loadBookmarks">Refresh</span>
                    <span wire:loading wire:target="loadBookmarks">Loading...</span>
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
                        @if(strpos($errorMessage, 'OAuth 2.0') !== false || strpos($errorMessage, 'Authorization Code Flow') !== false)
                            <div class="mt-2 text-sm text-red-600">
                                <i class="bx bx-info-circle mr-1"></i>
                                Bookmarks require OAuth 2.0 authorization which is not available with your current Twitter API access level.
                            </div>
                            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="text-sm text-yellow-800">
                                    <strong>Note:</strong> The Bookmarks feature requires elevated Twitter API access with OAuth 2.0 support. 
                                    This is a premium feature that may not be available with Basic API access.
                                </div>
                            </div>
                        @elseif(strpos($errorMessage, 'Rate limit exceeded') !== false)
                            <div class="mt-2 text-sm text-red-600">
                                <i class="bx bx-info-circle mr-1"></i>
                                Twitter API has rate limits to prevent abuse. Try again in a few minutes.
                            </div>
                            <div class="mt-3">
                                <button wire:click="loadBookmarks" 
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

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-2xl shadow-gray-200 p-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" 
                               wire:model.live.debounce.300ms="searchQuery"
                               placeholder="Search bookmarks..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="bx bx-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <!-- Filter Type -->
                <div class="flex gap-2">
                    <select wire:model.live="filterType" 
                            class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all">All Types</option>
                        <option value="tweets">Tweets</option>
                        <option value="replies">Replies</option>
                        <option value="quotes">Quote Tweets</option>
                    </select>

                    <!-- Sort By -->
                    <select wire:model.live="sortBy" 
                            class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="most_liked">Most Liked</option>
                        <option value="most_retweeted">Most Retweeted</option>
                    </select>
                </div>
            </div>

            <!-- Results Count -->
            <div class="mt-4 text-sm text-gray-600">
                Showing {{ count($this->paginatedBookmarks) }} of {{ count($this->filteredBookmarks) }} bookmarks
            </div>
        </div>

        <!-- Bookmarks Grid -->
        @if($loading && empty($bookmarks))
            <div class="text-center py-12 text-gray-500">
                <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-6"></div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading Bookmarks...</h3>
                    <p class="text-gray-600 mb-4">Fetching your saved tweets and content</p>
                    <p class="text-xs text-gray-500">This may take a few moments...</p>
                </div>
            </div>
        @elseif(count($this->paginatedBookmarks) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($this->paginatedBookmarks as $bookmark)
                    @php
                        // Safe property access with existence checks
                        $bookmarkId = null;
                        $bookmarkText = 'No content available';
                        $bookmarkCreatedAt = now();
                        $publicMetrics = null;
                        $likeCount = 0;
                        $retweetCount = 0;
                        $replyCount = 0;
                        $quoteCount = 0;
                        $referencedTweets = [];
                        $bookmarkType = 'tweet';
                        
                        if (is_object($bookmark)) {
                            $bookmarkId = $bookmark->id ?? null;
                            $bookmarkText = $bookmark->text ?? 'No content available';
                            $bookmarkCreatedAt = $bookmark->created_at ?? now();
                            $publicMetrics = $bookmark->public_metrics ?? null;
                            $referencedTweets = $bookmark->referenced_tweets ?? [];
                        } else if (is_array($bookmark)) {
                            $bookmarkId = $bookmark['id'] ?? null;
                            $bookmarkText = $bookmark['text'] ?? 'No content available';
                            $bookmarkCreatedAt = $bookmark['created_at'] ?? now();
                            $publicMetrics = $bookmark['public_metrics'] ?? null;
                            $referencedTweets = $bookmark['referenced_tweets'] ?? [];
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
                        
                        // Determine bookmark type
                        if (!empty($referencedTweets)) {
                            $firstRef = is_array($referencedTweets) ? $referencedTweets[0] : $referencedTweets[0];
                            $refType = is_object($firstRef) ? ($firstRef->type ?? '') : ($firstRef['type'] ?? '');
                            $bookmarkType = $refType === 'replied_to' ? 'reply' : ($refType === 'quoted' ? 'quote' : 'tweet');
                        }
                    @endphp
                    @if($bookmarkId)
                    <div class="bg-white rounded-xl shadow-2xl shadow-gray-200 p-6 hover:shadow-3xl transition-all duration-300 border border-gray-100">
                        <!-- Bookmark Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-2">
                                @if($bookmarkType === 'reply')
                                    <i class="bx bx-message text-blue-500"></i>
                                    <span class="text-xs text-blue-600 font-medium">Reply</span>
                                @elseif($bookmarkType === 'quote')
                                    <i class="bx bx-quote-left text-purple-500"></i>
                                    <span class="text-xs text-purple-600 font-medium">Quote Tweet</span>
                                @else
                                    <i class="bx bx-message-square text-gray-500"></i>
                                    <span class="text-xs text-gray-600 font-medium">Tweet</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="selectBookmark('{{ $bookmarkId }}')" 
                                        class="p-2 text-gray-400 hover:text-blue-500 transition-colors">
                                    <i class="bx bx-expand text-lg"></i>
                                </button>
                                <button wire:click="removeBookmark('{{ $bookmarkId }}')" 
                                        wire:confirm="Are you sure you want to remove this bookmark?"
                                        class="p-2 text-gray-400 hover:text-red-500 transition-colors">
                                    <i class="bx bx-trash text-lg"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Bookmark Content -->
                        <div class="mb-4">
                            <p class="text-gray-800 text-sm leading-relaxed line-clamp-4">
                                {{ $bookmarkText }}
                            </p>
                        </div>

                        <!-- Bookmark Metrics -->
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
                            <span>{{ \Carbon\Carbon::parse($bookmarkCreatedAt)->diffForHumans() }}</span>
                            <div class="flex items-center gap-4">
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

                        <!-- Bookmark Actions -->
                        <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                            <a href="https://twitter.com/i/web/status/{{ $bookmarkId }}" 
                               target="_blank"
                               class="flex-1 px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors text-center">
                                <i class="bx bx-external-link mr-1"></i>
                                View on Twitter
                            </a>
                            <button wire:click="selectBookmark('{{ $bookmarkId }}')" 
                                    class="px-3 py-2 text-xs font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <i class="bx bx-info-circle mr-1"></i>
                                Details
                            </button>
                        </div>
                    </div>
                    @else
                    <!-- Fallback for bookmarks without ID -->
                    <div class="border border-yellow-200 rounded-xl p-6 bg-yellow-50">
                        <div class="flex items-start justify-between mb-2">
                            <span class="text-xs text-yellow-600">Invalid Bookmark Data</span>
                            <i class="bx bx-error text-yellow-500"></i>
                        </div>
                        <p class="text-sm text-gray-800 mb-3">
                            {{ $bookmarkText }}
                        </p>
                        <div class="text-xs text-yellow-600">
                            This bookmark has missing or invalid data structure
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            <!-- Pagination -->
            @if(count($this->filteredBookmarks) > $perPage)
                <div class="flex items-center justify-between mt-8">
                    <div class="text-sm text-gray-600">
                        Page {{ $currentPage }} of {{ ceil(count($this->filteredBookmarks) / $perPage) }}
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="previousPage" 
                                @disabled($currentPage <= 1)
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="bx bx-chevron-left mr-1"></i>
                            Previous
                        </button>
                        <button wire:click="nextPage" 
                                @disabled($currentPage >= ceil(count($this->filteredBookmarks) / $perPage))
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                            <i class="bx bx-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-12 text-gray-500">
                <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                    <i class="bx bx-bookmark text-6xl mb-6 text-gray-400"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">No Bookmarks Found</h3>
                    <p class="text-gray-600 mb-4">
                        @if(!empty($searchQuery))
                            No bookmarks match your search "{{ $searchQuery }}"
                        @elseif(strpos($errorMessage ?? '', 'OAuth 2.0') !== false)
                            Bookmarks feature requires OAuth 2.0 authorization which is not available with your current Twitter API access level.
                        @else
                            You haven't bookmarked any tweets yet. Start saving your favorite content!
                        @endif
                    </p>
                    @if(strpos($errorMessage ?? '', 'OAuth 2.0') === false)
                        <button wire:click="$set('showAddBookmarkModal', true)" 
                                class="px-6 py-3 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors">
                            <i class="bx bx-plus mr-2"></i>
                            Add Your First Bookmark
                        </button>
                    @else
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="text-sm text-yellow-800">
                                <strong>Upgrade Required:</strong> To use the Bookmarks feature, you need to upgrade your Twitter API access to include OAuth 2.0 support.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Add Bookmark Modal -->
    @if($showAddBookmarkModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="$set('showAddBookmarkModal', false)">
            <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add Bookmark</h3>
                    <button wire:click="$set('showAddBookmarkModal', false)" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="bx bx-x text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tweet URL
                        </label>
                        <input type="url" 
                               wire:model="newBookmarkUrl"
                               placeholder="https://twitter.com/username/status/1234567890"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            Paste the full Twitter URL of the tweet you want to bookmark
                        </p>
                    </div>
                    
                    <div class="flex gap-3">
                        <button wire:click="addBookmark" 
                                wire:loading.attr="disabled"
                                class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="addBookmark">Add Bookmark</span>
                            <span wire:loading wire:target="addBookmark">Adding...</span>
                        </button>
                        <button wire:click="$set('showAddBookmarkModal', false)" 
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
    .line-clamp-4 {
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    </style>
</div>
