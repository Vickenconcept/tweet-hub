<div class="p-6 bg-white rounded-lg shadow-md">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Queued Posts</h2>
        <button wire:click="loadQueuedPosts" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-100 rounded-lg hover:bg-blue-200 transition-colors">
            <i class="bx bx-refresh mr-1"></i> Refresh
        </button>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ $errorMessage }}
        </div>
    @endif

    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading queued posts...</p>
        </div>
    @elseif(count($queuedPosts) > 0)
        <!-- Queued Posts List -->
        <div class="space-y-4">
            @foreach($queuedPosts as $post)
                <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    Scheduled
                                </span>
                                <span class="text-sm text-gray-500">
                                    {{ $post->scheduled_at->format('M j, Y g:i A') }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-2">{{ $post->content }}</p>
                            @if($post->media && count($post->media) > 0)
                                <div class="flex items-center gap-1 text-sm text-gray-500">
                                    <i class="bx bx-image"></i>
                                    <span>{{ count($post->media) }} media attached</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="editPost({{ $post->id }})" 
                                    class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                <i class="bx bx-edit text-lg"></i>
                            </button>
                            <button wire:click="deletePost({{ $post->id }})" 
                                    class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors"
                                    onclick="return confirm('Are you sure you want to delete this post?')">
                                <i class="bx bx-trash text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-8 text-gray-500">
            <i class="bx bx-time text-4xl mb-4"></i>
            <p>No queued posts found</p>
            <p class="text-sm">Schedule posts from the compose tab to see them here</p>
        </div>
    @endif

    <!-- Edit Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Queued Post</h3>
                
                <div class="mb-4">
                    <label for="editContent" class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                    <textarea wire:model="editContent" id="editContent" rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="mb-6">
                    <label for="editScheduledAt" class="block text-sm font-medium text-gray-700 mb-2">Schedule Time</label>
                    <input wire:model="editScheduledAt" type="datetime-local" id="editScheduledAt"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="flex gap-3">
                    <button wire:click="updatePost" 
                            class="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        Update Post
                    </button>
                    <button wire:click="cancelEdit" 
                            class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div> 