<div class="space-y-6">
    @php
        $userTimezone = auth()->user()?->timezone ?? config('app.timezone');
    @endphp
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">Scheduled Posts</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    Queued Posts
                </h1>
                <p class="text-gray-500 mt-2 text-sm md:text-base">
                    <i class="bx bx-time mr-1 text-green-600"></i>
                    Manage your scheduled X posts
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <button wire:click="loadQueuedPosts" 
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-colors">
                    <i class="bx bx-refresh text-lg"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4">
            <div class="flex items-center text-green-700">
                <i class="bx bx-check-circle mr-2"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    @if($errorMessage && !$showEditModal)
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4">
            <div class="flex items-center text-red-700">
                <i class="bx bx-error-circle mr-2"></i>
                <span>{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Loading State -->
    @if($loading)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-6"></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading Queued Posts...</h3>
            <p class="text-gray-600">Fetching your scheduled posts</p>
        </div>
    @elseif(count($queuedPosts) > 0)
        <!-- Queued Posts List -->
        <div class="space-y-4">
            @foreach($queuedPosts as $post)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="px-3 py-1 text-xs font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full">
                                    Scheduled
                                </span>
                                <span class="text-sm text-gray-500 flex items-center gap-1">
                                    <i class="bx bx-time text-base"></i>
                                    {{ $post->scheduled_at->timezone($userTimezone)->format('M j, Y g:i A') }}
                                </span>
                            </div>
                            <p class="text-gray-800 mb-3 leading-relaxed">{{ $post->content }}</p>
                            @if($post->media && count($post->media) > 0)
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <i class="bx bx-image text-lg"></i>
                                    <span>{{ count($post->media) }} media attached</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="editPost({{ $post->id }})" 
                                    class="p-2.5 text-green-600 hover:bg-green-50 rounded-xl transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </button>
                            <button wire:click="deletePost({{ $post->id }})" 
                                    class="p-2.5 text-red-600 hover:bg-red-50 rounded-xl transition-colors"
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
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-time text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Queued Posts</h3>
            <p class="text-gray-600 mb-1">You don't have any scheduled posts yet</p>
            <p class="text-sm text-gray-500">Schedule posts from the compose tab to see them here</p>
        </div>
    @endif

    <!-- Edit Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-3xl p-8 w-full max-w-lg shadow-xl">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-green-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Edit Post</p>
                        <h3 class="text-2xl font-semibold text-gray-900">Update Scheduled Post</h3>
                    </div>
                </div>
                
                @if($errorMessage)
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm">
                        <div class="flex items-center">
                            <i class="bx bx-error-circle mr-2 text-lg"></i>
                            <span>{{ $errorMessage }}</span>
                        </div>
                    </div>
                @endif
                
                @error('editContent')
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm">
                        <div class="flex items-center">
                            <i class="bx bx-error-circle mr-2 text-lg"></i>
                            <span>{{ $message }}</span>
                        </div>
                    </div>
                @enderror
                
                <div class="mb-6">
                    <label for="editContent" class="block text-sm font-semibold text-gray-800 mb-2">Content</label>
                    <textarea wire:model="editContent" id="editContent" rows="5"
                              class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm @error('editContent') border-red-300 @enderror"
                              placeholder="Enter your post content..."></textarea>
                </div>

                @error('editScheduledAt')
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm">
                        <div class="flex items-center">
                            <i class="bx bx-error-circle mr-2 text-lg"></i>
                            <span>{{ $message }}</span>
                        </div>
                    </div>
                @enderror

                <div class="mb-8">
                    <label for="editScheduledAt" class="block text-sm font-semibold text-gray-800 mb-2">Schedule Time ({{ $userTimezone }})</label>
                    <input wire:model="editScheduledAt" type="datetime-local" id="editScheduledAt"
                           class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm @error('editScheduledAt') border-red-300 @enderror"
                           min="{{ now($userTimezone)->format('Y-m-d\TH:i') }}">
                </div>

                <div class="flex gap-3">
                    <button wire:click="updatePost" 
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 transition-colors cursor-pointer shadow-sm">
                        <i class="bx bx-save text-lg"></i>
                        Update Post
                    </button>
                    <button wire:click="cancelEdit" 
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-2xl hover:bg-gray-200 transition-colors cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div> 