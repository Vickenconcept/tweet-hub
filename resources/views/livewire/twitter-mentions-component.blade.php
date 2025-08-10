<div class="p-6 bg-white rounded-lg shadow-md ">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Twitter Mentions</h2>
        <div class="flex items-center gap-3">
            @if($lastRefresh)
                <span class="text-sm text-gray-500">Last updated: {{ $lastRefresh }}</span>
            @endif
            <button wire:click="loadMentions" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-100 rounded-lg hover:bg-blue-200 transition-colors">
                <i class="bx bx-refresh mr-1"></i>
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Loading...</span>
            </button>
            <button wire:click="refreshMentions" 
                    class="px-4 py-2 text-sm font-medium text-green-600 bg-green-100 rounded-lg hover:bg-green-200 transition-colors">
                <i class="bx bx-sync mr-1"></i>
                Sync
            </button>

        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg shadow-sm" 
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
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg shadow-sm" 
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
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading mentions...</p>
        </div>
    @elseif(count($mentions) > 0)
        <!-- Mentions List -->
        <div class="space-y-4">
            @foreach($this->getPaginatedMentions() as $mention)
                <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="bx bx-user text-blue-600"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-medium text-gray-900">{{ $mention->author_id ?? 'Unknown User' }}</span>
                                <span class="text-sm text-gray-500">
                                    {{ isset($mention->created_at) ? \Carbon\Carbon::parse($mention->created_at)->diffForHumans() : '' }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-3">{{ $mention->text ?? 'No content' }}</p>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2">
                                <button wire:click="replyToMention('{{ $mention->id }}')" 
                                        class="px-3 py-1 text-sm text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                    <i class="bx bx-reply mr-1"></i> Reply
                                </button>
                                <button wire:click="likeMention('{{ $mention->id }}')" 
                                        class="px-3 py-1 text-sm text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                                    <i class="bx bx-heart mr-1"></i> Like
                                </button>
                                <button wire:click="retweetMention('{{ $mention->id }}')" 
                                        class="px-3 py-1 text-sm text-green-600 hover:bg-green-100 rounded-lg transition-colors">
                                    <i class="bx bx-repost mr-1"></i> Retweet
                                </button>
                                <a href="https://twitter.com/i/status/{{ $mention->id }}" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="px-3 py-1 text-sm text-purple-600 hover:bg-purple-100 rounded-lg transition-colors inline-flex items-center">
                                    <i class="bx bx-external-link mr-1"></i> View Tweet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination Controls -->
        @if($this->getTotalPages() > 1)
            <div class="mt-6 flex items-center justify-center">
                <div class="flex items-center space-x-2">
                    <!-- Previous Page Button -->
                    <button wire:click="previousPage" 
                            wire:loading.attr="disabled"
                            @if($currentPage <= 1) disabled @endif
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <i class="bx bx-chevron-left mr-1"></i>
                        Previous
                    </button>

                    <!-- Page Numbers -->
                    <div class="flex items-center space-x-1">
                        @for($page = 1; $page <= $this->getTotalPages(); $page++)
                            @if($page == 1 || $page == $this->getTotalPages() || ($page >= $currentPage - 1 && $page <= $currentPage + 1))
                                <button wire:click="goToPage({{ $page }})" 
                                        @if($page == $currentPage) disabled @endif
                                        class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $page == $currentPage ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50' }}">
                                    {{ $page }}
                                </button>
                            @elseif($page == $currentPage - 2 || $page == $currentPage + 2)
                                <span class="px-2 py-2 text-gray-400">...</span>
                            @endif
                        @endfor
                    </div>

                    <!-- Next Page Button -->
                    <button wire:click="nextPage" 
                            wire:loading.attr="disabled"
                            @if($currentPage >= $this->getTotalPages()) disabled @endif
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Next
                        <i class="bx bx-chevron-right ml-1"></i>
                    </button>
                </div>

                <!-- Page Info -->
                <div class="ml-4 text-sm text-gray-500">
                    Page {{ $currentPage }} of {{ $this->getTotalPages() }}
                    <span class="text-gray-400">•</span>
                    Showing {{ count($this->getPaginatedMentions()) }} of {{ count($mentions) }} mentions
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="text-center py-8 text-gray-500">
            <i class="bx bx-at text-4xl mb-4"></i>
            <p>No mentions found</p>
            <p class="text-sm">Mentions will appear here when someone mentions your account</p>
        </div>
    @endif

    <!-- Reply Modal -->
    @if($showReplyModal && $selectedMention)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reply to Mention</h3>
                
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Replying to:</p>
                    <p class="text-gray-800">{{ $selectedMention->text ?? 'No content' }}</p>
                </div>

                <div class="mb-6">
                    <label for="replyContent" class="block text-sm font-medium text-gray-700 mb-2">Your Reply</label>
                    <textarea wire:model="replyContent" id="replyContent" rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Type your reply..."></textarea>
                    <div class="text-right mt-1">
                        <span class="text-sm text-gray-500">{{ strlen($replyContent) }}/280</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="sendReply" 
                            wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                        <span wire:loading.remove>Send Reply</span>
                        <span wire:loading>Sending...</span>
                    </button>
                    <button wire:click="cancelReply" 
                            class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div> 