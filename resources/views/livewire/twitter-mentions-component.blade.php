<div class="">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Twitter Mentions</h2>
        <div class="flex items-center gap-3">
            @if($lastRefresh)
                <span class="text-sm text-gray-500">Last updated: {{ $lastRefresh }}</span>
            @endif
            <button wire:click="loadMentions" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 rounded-xl hover:bg-blue-200 transition-colors cursor-pointer">
                <i class="bx bx-refresh mr-1"></i>
                <span wire:loading.remove wire:target="loadMentions">Refresh</span>
                <span wire:loading wire:target="loadMentions">Loading...</span>
            </button>
            <button wire:click="refreshMentions" 
                    class="px-4 py-2 text-sm font-medium text-green-600 bg-gradient-to-r from-green-100 to-green-200 rounded-xl hover:bg-green-200 transition-colors cursor-pointer">
                <i class="bx bx-sync mr-1"></i>
                Sync
            </button>

        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl shadow-sm" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearSuccessMessage(); }, 4000)">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <i class="bx bx-check-circle text-xl mr-2 mt-0.5 text-green-600"></i>
                    <div>
                        <p class="font-medium text-lg">{{ $successMessage }}</p>
                        <p class="text-sm text-green-600 mt-1">Action completed successfully!</p>
                    </div>
                </div>
                <button @click="show = false; $wire.clearSuccessMessage();" class="text-green-600 hover:text-green-800 p-1">
                    <i class="bx bx-x text-lg"></i>
                </button>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl shadow-sm" 
             x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => { show = false; $wire.clearErrorMessage(); }, 8000)">
            <div class="flex items-start justify-between">
                <div class="flex items-start">
                    <i class="bx bx-error-circle text-xl mr-2 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-lg">{{ $errorMessage }}</p>
                        @if(str_contains($errorMessage, 'Rate limit exceeded'))
                            <p class="text-sm mt-1 text-red-600">Twitter API has rate limits. Try again in a few minutes.</p>
                        @endif
                    </div>
                </div>
                <button @click="show = false; $wire.clearErrorMessage();" class="text-red-600 hover:text-red-800 p-1">
                    <i class="bx bx-x text-lg"></i>
                </button>
            </div>
        </div>
    @endif



    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-12">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-6"></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading Mentions...</h3>
                <p class="text-gray-600">Fetching your Twitter mentions</p>
            </div>
        </div>
    @elseif(count($mentions) > 0)
        <!-- Mentions List -->
        <div class="space-y-4">
            @foreach($this->getPaginatedMentions() as $mention)
                <div class="p-6 rounded-2xl bg-white hover:shadow-blue-100 transition-all duration-500 ease-in-out shadow-2xl shadow-gray-200">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-100 to-blue-200 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="font-semibold text-gray-500 text-md">{{ $mention->author_id ?? 'Unknown User' }}</span>
                                <span class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded-lg">
                                    {{ isset($mention->created_at) ? \Carbon\Carbon::parse($mention->created_at)->diffForHumans() : '' }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-4 text-md leading-relaxed">{{ $mention->text ?? 'No content' }}</p>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-3">
                                <button wire:click="replyToMention('{{ $mention->id }}')" 
                                        class="px-4 py-2 text-sm text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 hover:bg-blue-200 rounded-xl transition-colors cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 inline mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                    Reply
                                </button>
                                <button wire:click="likeMention('{{ $mention->id }}')" 
                                        class="px-4 py-2 text-sm text-red-600 bg-gradient-to-r from-red-100 to-red-200 hover:bg-red-200 rounded-xl transition-colors cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 inline mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                    </svg>
                                    Like
                                </button>
                                <button wire:click="retweetMention('{{ $mention->id }}')" 
                                        class="px-4 py-2 text-sm text-green-600 bg-gradient-to-r from-green-100 to-green-200 hover:bg-green-200 rounded-xl transition-colors cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 inline mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                    </svg>
                                    Retweet
                                </button>
                                <a href="https://twitter.com/i/status/{{ $mention->id }}" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="px-4 py-2 text-sm text-purple-600 bg-gradient-to-r from-purple-100 to-purple-200 hover:bg-purple-200 rounded-xl transition-colors inline-flex items-center cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
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
            <div class="mt-8 flex items-center justify-center">
                <div class="bg-white rounded-2xl shadow-2xl shadow-gray-200 p-4">
                    <div class="flex items-center space-x-3">
                        <!-- Previous Page Button -->
                        <button wire:click="previousPage" 
                                wire:loading.attr="disabled"
                                @if($currentPage <= 1) disabled @endif
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 inline mr-1">
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
                                            class="px-4 py-2 text-sm font-medium rounded-xl transition-colors {{ $page == $currentPage ? 'bg-gradient-to-r from-blue-400 to-blue-600 text-white shadow-2xl shadow-gray-200' : 'text-gray-700 bg-gradient-to-r from-gray-100 to-gray-200 hover:bg-gray-200' }}">
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
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 inline ml-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>

                    <!-- Page Info -->
                    <div class="mt-3 text-center text-sm text-gray-600 bg-gray-50 rounded-xl px-4 py-2">
                        Page {{ $currentPage }} of {{ $this->getTotalPages() }}
                        <span class="text-gray-400 mx-2">•</span>
                        Showing {{ count($this->getPaginatedMentions()) }} of {{ count($mentions) }} mentions
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="text-center py-12 text-gray-500">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <i class="bx bx-at text-6xl mb-6 text-blue-500"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">No Mentions Found</h3>
                <p class="text-gray-600 mb-4">You don't have any mentions yet</p>
                <p class="text-xs text-gray-500">Mentions will appear here when someone mentions your account</p>
            </div>
        </div>
    @endif

    <!-- Reply Modal -->
    @if($showReplyModal && $selectedMention)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl p-8 w-full max-w-lg mx-4 shadow-2xl shadow-gray-200">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-blue-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Reply to Mention</h3>
                </div>
                
                <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-600 mb-2 font-medium">Replying to:</p>
                    <p class="text-gray-800 text-lg leading-relaxed">{{ $selectedMention->text ?? 'No content' }}</p>
                </div>

                <div class="mb-8">
                    <label for="replyContent" class="block text-sm font-medium text-gray-700 mb-3">Your Reply</label>
                    <textarea wire:model="replyContent" id="replyContent" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg"
                              placeholder="Type your reply..."></textarea>
                    <div class="text-right mt-2">
                        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">{{ strlen($replyContent) }}/280</span>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button wire:click="sendReply" 
                            wire:loading.attr="disabled"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors cursor-pointer shadow-2xl shadow-gray-200">
                        <span wire:loading.remove>Send Reply</span>
                        <span wire:loading>Sending...</span>
                    </button>
                    <button wire:click="cancelReply" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-gray-400 to-gray-600 text-white font-medium rounded-xl hover:bg-gray-600 transition-colors cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div> 