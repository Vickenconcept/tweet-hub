<div class="space-y-6" x-data="{ init() { setTimeout(() => $wire.loadMentions(false), 3000); } }">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">X Mentions</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    Twitter Mentions
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
                        Monitor mentions of your X account
                    @endif
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <button type="button"
                        wire:click="refreshMentions" 
                        wire:loading.attr="disabled"
                        @if($isRateLimited) disabled style="pointer-events: none;" onclick="return false;" @endif
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold transition-colors
                               @if($isRateLimited)
                                   text-gray-400 bg-gray-100 cursor-not-allowed opacity-50
                               @else
                                   text-gray-700 hover:border-gray-300
                               @endif">
                    <i class="bx bx-sync text-lg"></i>
                    <span wire:loading.remove wire:target="refreshMentions">
                        @if($isRateLimited)
                            Rate Limited
                        @else
                            Sync Fresh Data
                        @endif
                    </span>
                    <span wire:loading wire:target="refreshMentions">Syncing...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearSuccessMessage(); }, 4000)">
            <div class="flex items-center text-green-700">
                <i class="bx bx-check-circle mr-2 text-lg"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearErrorMessage(); }, 10000)">
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



    <!-- Loading State -->
    @if($loading)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-6"></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading Mentions...</h3>
            <p class="text-gray-600 mb-4">Fetching your latest Twitter mentions from cache or API</p>
            <p class="text-xs text-gray-500">This uses cached data when available to avoid rate limits</p>
        </div>
    @elseif(count($mentions) > 0)
        <!-- Mentions List -->
        <div class="space-y-4">
            @foreach($this->getPaginatedMentions() as $mention)
                @php
                    $mentionAuthorId = is_object($mention) ? ($mention->author_id ?? null) : ($mention['author_id'] ?? null);
                    $mentionText = is_object($mention) ? ($mention->text ?? 'No content') : ($mention['text'] ?? 'No content');
                    $mentionCreatedAt = is_object($mention) ? ($mention->created_at ?? null) : ($mention['created_at'] ?? null);
                    $mentionId = is_object($mention) ? ($mention->id ?? null) : ($mention['id'] ?? null);
                    
                    // Get user information
                    $user = $this->getUserByAuthorId($mentionAuthorId);
                    $userName = $user ? (is_object($user) ? ($user->name ?? 'Unknown User') : ($user['name'] ?? 'Unknown User')) : 'Unknown User';
                    $userUsername = $user ? (is_object($user) ? ($user->username ?? null) : ($user['username'] ?? null)) : null;
                    $userProfileImage = $user ? (is_object($user) ? ($user->profile_image_url ?? null) : ($user['profile_image_url'] ?? null)) : null;
                    $userDescription = $user ? (is_object($user) ? ($user->description ?? null) : ($user['description'] ?? null)) : null;
                    $userMetrics = $user ? (is_object($user) ? ($user->public_metrics ?? null) : ($user['public_metrics'] ?? null)) : null;
                    $followersCount = $userMetrics ? (is_object($userMetrics) ? ($userMetrics->followers_count ?? 0) : ($userMetrics['followers_count'] ?? 0)) : 0;
                @endphp
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            @if($userProfileImage)
                                <img src="{{ $userProfileImage }}" 
                                     alt="{{ $userName }}" 
                                     class="w-12 h-12 rounded-full">
                            @else
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-gray-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <div>
                                    <span class="font-semibold text-gray-900 text-md block">{{ $userName }}</span>
                                    @if($userUsername)
                                        <span class="text-sm text-gray-500"><span>@</span>{{ $userUsername }}</span>
                                    @endif
                                    @if($followersCount > 0)
                                        <span class="text-xs text-gray-400 ml-2">• {{ number_format($followersCount) }} followers</span>
                                    @endif
                                </div>
                                <span class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded-lg ml-auto">
                                    {{ $mentionCreatedAt ? \Carbon\Carbon::parse($mentionCreatedAt)->diffForHumans() : '' }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-4 text-md leading-relaxed">{{ $mentionText }}</p>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <button wire:click="replyToMention('{{ $mentionId }}')" 
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer border border-gray-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                    Reply
                                </button>
                                <button wire:click="likeMention('{{ $mentionId }}')" 
                                        class="like-button inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-all duration-300 cursor-pointer relative overflow-hidden group border border-red-200"
                                        x-data="{ 
                                            liked: false, 
                                            animating: false,
                                            likeMention() {
                                                if (this.animating) return;
                                                this.animating = true;
                                                this.liked = true;
                                                $wire.likeMention('{{ $mentionId }}');
                                                setTimeout(() => {
                                                    this.liked = false;
                                                    this.animating = false;
                                                }, 2000);
                                            }
                                        }"
                                        @click="likeMention()"
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
                                <button wire:click="retweetMention('{{ $mentionId }}')" 
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded-xl transition-all duration-300 cursor-pointer relative overflow-hidden border border-green-200"
                                        x-data="{ 
                                            retweeted: false, 
                                            animating: false,
                                            retweetMention() {
                                                if (this.animating) return;
                                                this.animating = true;
                                                this.retweeted = true;
                                                $wire.retweetMention('{{ $mentionId }}');
                                                setTimeout(() => {
                                                    this.retweeted = false;
                                                    this.animating = false;
                                                }, 2000);
                                            }
                                        }"
                                        @click="retweetMention()"
                                        :class="{ 'animate-pulse': animating, 'scale-105': retweeted }">
                                    <!-- Retweet Icon with Animation -->
                                    <div class="relative inline-flex items-center gap-1">
                                        <!-- Default Retweet Icon -->
                                        <svg x-show="!retweeted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                        </svg>
                                        
                                        <!-- Animated Retweet Icon -->
                                        <svg x-show="retweeted" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-all duration-300 animate-spin text-green-600">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
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
                                <a href="https://twitter.com/i/status/{{ $mentionId }}" 
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
        @if($this->getTotalPages() > 1)
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
                        @for($page = 1; $page <= $this->getTotalPages(); $page++)
                            @if($page == 1 || $page == $this->getTotalPages() || ($page >= $currentPage - 1 && $page <= $currentPage + 1))
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
                            @if($currentPage >= $this->getTotalPages()) disabled @endif
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-xl hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer border border-gray-200">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>

                <!-- Page Info -->
                <div class="text-center text-sm text-gray-600">
                    Page {{ $currentPage }} of {{ $this->getTotalPages() }}
                    <span class="text-gray-400 mx-2">•</span>
                    Showing {{ count($this->getPaginatedMentions()) }} of {{ count($mentions) }} mentions
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-at text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Mentions Found</h3>
            <p class="text-gray-600 mb-1">You don't have any mentions yet</p>
            <p class="text-sm text-gray-500">Mentions will appear here when someone mentions your account</p>
        </div>
    @endif

    <!-- Reply Modal -->
    @if($showReplyModal && $selectedMention)
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
                        <h3 class="text-2xl font-semibold text-gray-900">Reply to Mention</h3>
                    </div>
                </div>
                
                <div class="mb-6 p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <p class="text-sm text-gray-600 mb-2 font-semibold">Replying to:</p>
                    <p class="text-gray-800 text-sm leading-relaxed">{{ $selectedMention->text ?? 'No content' }}</p>
                </div>

                <div class="mb-8">
                    <label for="replyContent" class="block text-sm font-semibold text-gray-800 mb-2">Your Reply</label>
                    <textarea wire:model="replyContent" id="replyContent" rows="5"
                              class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
                              placeholder="Type your reply..."></textarea>
                    <div class="text-right mt-2">
                        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-xl">{{ strlen($replyContent) }}/280</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="sendReply" 
                            wire:loading.attr="disabled"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-sm">
                        <span wire:loading.remove>Send Reply</span>
                        <span wire:loading>Sending...</span>
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
