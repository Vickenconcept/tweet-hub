<div class="p-6 bg-white rounded-lg shadow-md">
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
            <button wire:click="forceRefreshMentions" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-orange-600 bg-orange-100 rounded-lg hover:bg-orange-200 transition-colors">
                <i class="bx bx-sync mr-1"></i>
                <span wire:loading.remove>Force Refresh</span>
                <span wire:loading>Loading...</span>
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <div class="flex items-start">
                <i class="bx bx-error-circle text-xl mr-2 mt-0.5"></i>
                <div>
                    <p class="font-medium">{{ $errorMessage }}</p>
                    @if(str_contains($errorMessage, 'Rate limit exceeded'))
                        <p class="text-sm mt-1">Twitter API has rate limits. Try again in a few minutes or use the Force Refresh button to bypass cache.</p>
                    @endif
                </div>
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
            @foreach($mentions as $mention)
                <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="bx bx-user text-blue-600"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-medium text-gray-900">{{ $mention['author_id'] ?? 'Unknown User' }}</span>
                                <span class="text-sm text-gray-500">
                                    {{ isset($mention['created_at']) ? \Carbon\Carbon::parse($mention['created_at'])->diffForHumans() : '' }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-3">{{ $mention['text'] ?? 'No content' }}</p>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2">
                                <button wire:click="replyToMention('{{ $mention['id'] }}')" 
                                        class="px-3 py-1 text-sm text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                    <i class="bx bx-reply mr-1"></i> Reply
                                </button>
                                <button wire:click="likeMention('{{ $mention['id'] }}')" 
                                        class="px-3 py-1 text-sm text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                                    <i class="bx bx-heart mr-1"></i> Like
                                </button>
                                <button wire:click="retweetMention('{{ $mention['id'] }}')" 
                                        class="px-3 py-1 text-sm text-green-600 hover:bg-green-100 rounded-lg transition-colors">
                                    <i class="bx bx-repost mr-1"></i> Retweet
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
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
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reply to Mention</h3>
                
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Replying to:</p>
                    <p class="text-gray-800">{{ $selectedMention['text'] ?? 'No content' }}</p>
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