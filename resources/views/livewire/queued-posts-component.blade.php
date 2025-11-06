<div class="">
    <div class="flex items-center justify-between mb-6">  
        <h2 class="text-2xl font-bold text-gray-900">Queued Posts</h2>
        <button wire:click="loadQueuedPosts" class="px-4 py-2 text-sm font-medium text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 rounded-xl hover:bg-blue-200 transition-colors cursor-pointer">
            <i class="bx bx-refresh mr-1"></i> Refresh
        </button>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">
            {{ $errorMessage }}
        </div>
    @endif

    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-12">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-6"></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading Queued Posts...</h3>
                <p class="text-gray-600">Fetching your scheduled posts</p>
            </div>
        </div>
    @elseif(count($queuedPosts) > 0)
        <!-- Queued Posts List -->
        <div class="space-y-4">
            @foreach($queuedPosts as $post)
                <div class="p-6 rounded-2xl bg-white   shadow-2xl shadow-gray-200 hover:shadow-blue-100 transition-all duration-500 ease-in-out">
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
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                  </svg>
                                  
                            </button>
                            <button wire:click="deletePost({{ $post->id }})" 
                                    class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors"
                                    onclick="return confirm('Are you sure you want to delete this post?')">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                  </svg>
                                  
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-12 text-gray-500">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <i class="bx bx-time text-6xl mb-6 text-blue-500"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">No Queued Posts</h3>
                <p class="text-gray-600 mb-4">You don't have any scheduled posts yet</p>
                <p class="text-xs text-gray-500">Schedule posts from the compose tab to see them here</p>
            </div>
        </div>
    @endif

    <!-- Edit Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl p-8 w-full max-w-lg mx-4 shadow-2xl shadow-gray-200">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-blue-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Edit Queued Post</h3>
                </div>
                
                @if($errorMessage)
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-xl text-sm">
                        {{ $errorMessage }}
                    </div>
                @endif
                
                @error('editContent')
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-xl text-sm">
                        {{ $message }}
                    </div>
                @enderror
                
                <div class="mb-6">
                    <label for="editContent" class="block text-sm font-medium text-gray-700 mb-3">Content</label>
                    <textarea wire:model="editContent" id="editContent" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg @error('editContent') border-red-500 @enderror"></textarea>
                </div>

                @error('editScheduledAt')
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-xl text-sm">
                        {{ $message }}
                    </div>
                @enderror

                <div class="mb-8">
                    <label for="editScheduledAt" class="block text-sm font-medium text-gray-700 mb-3">Schedule Time</label>
                    <input wire:model="editScheduledAt" type="datetime-local" id="editScheduledAt"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg @error('editScheduledAt') border-red-500 @enderror">
                </div>

                <div class="flex gap-4">
                    <button wire:click="updatePost" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition-colors cursor-pointer shadow-2xl shadow-gray-200">
                        <i class="bx bx-save mr-2"></i>
                        Update Post
                    </button>
                    <button wire:click="cancelEdit" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-gray-400 to-gray-600 text-white font-medium rounded-xl hover:bg-gray-600 transition-colors cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div> 