@php
    use Illuminate\Support\Str;
@endphp
<div class="flex flex-col h-full w-full bg-white rounded-lg shadow-md" x-data="{
    threadStarted: @entangle('threadStarted').live,
    assetPickerOpen: false,
    showSchedulePicker: false,
    canTweet: false,
    checkThread(value) {
        if (!this.threadStarted && /\n{3,}/.test(value)) {
            $wire.startThread();
        }
    },
    init() {
        this.$watch('$wire.message', value => {
            this.canTweet = value && (value.trim().length > 0 || /\[img:([a-zA-Z0-9]+)\]/.test(value)) || $wire.threadMessages.length > 0;
        });
        
        this.$watch('$wire.threadMessages', messages => {
            if (messages && messages.length > 0) {
                this.threadStarted = true;
            }
        });
    }
}"
    x-init="
        $watch('$wire.message', value => {
            canTweet = value && (value.trim().length > 0 || /\[img:([a-zA-Z0-9]+)\]/.test(value)) || $wire.threadMessages.length > 0;
        });
        Livewire.on('tweet-posted', () => { threadStarted = false; });
        Livewire.on('thread-message-added', () => { 
            canTweet = true;
            threadStarted = true;
        });
        Livewire.on('tweet-asset-uploaded', () => {
            if (threadStarted) {
                $wire.startThread();
            }
        });
        Livewire.on('post-scheduled', () => {
            showSchedulePicker = false;
        });
    "
>
    <!-- Tabs -->
    <div class="flex items-center border-b px-4 pt-4">
        <button wire:click="setTab('compose')" class="px-4 py-2 font-semibold focus:outline-none {{ $activeTab === 'compose' ? 'text-blue-700 border-b-2 border-blue-700' : 'text-gray-500 hover:text-blue-700' }}">Compose</button>
        <button wire:click="setTab('drafts')" class="px-4 py-2 focus:outline-none {{ $activeTab === 'drafts' ? 'text-blue-700 border-b-2 border-blue-700' : 'text-gray-500 hover:text-blue-700' }}">Drafts</button>
        <button wire:click="setTab('scheduled')" class="px-4 py-2 focus:outline-none {{ $activeTab === 'scheduled' ? 'text-blue-700 border-b-2 border-blue-700' : 'text-gray-500 hover:text-blue-700' }}">Scheduled</button>
        <button wire:click="setTab('sent')" class="px-4 py-2 focus:outline-none {{ $activeTab === 'sent' ? 'text-blue-700 border-b-2 border-blue-700' : 'text-gray-500 hover:text-blue-700' }}">Sent</button>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col px-6 py-4">
        @if ($activeTab === 'compose')
            @if ($successMessage)
                <div class="mb-2 p-2 bg-green-100 text-green-700 rounded">{{ $successMessage }}</div>
            @endif
            @if ($errorMessage)
                <div class="mb-2 p-2 bg-red-100 text-red-700 rounded">{{ $errorMessage }}</div>
            @endif
                <div class="flex items-center justify-between mb-2">
                    <span class="font-semibold text-gray-700">Your content</span>
                <div class="flex justify-end items-center gap-2">
                    <a href="#" class="text-blue-600 text-sm hover:underline">+ New draft</a>
                </div>
            </div>
            <div class="flex gap-4">
                <!-- Thread Messages Panel -->
                <div class="w-48" x-show="threadStarted">
                    <div class=" mb-2">
                        <div class="font-semibold text-gray-700">Thread Messages</div>
                        <button type="button" @click="$wire.endThread()" class="text-xs text-red-600 hover:text-red-800">
                            <i class='bx bx-x-circle'></i> Exit Thread
                        </button>
                    </div>
                    @if (empty($threadMessages))
                        <div class="text-sm text-gray-500">No messages in thread yet.</div>
                    @else
                        <div class="space-y-2">
                            @foreach ($threadMessages as $index => $threadMessage)
                                <div class="relative group bg-gray-50 p-2 rounded">
                                    <div class="text-sm text-gray-800">{{ Str::limit($threadMessage, 100) }}</div>
                                    <div class="absolute top-1 right-1 hidden group-hover:flex gap-1">
                                        <button type="button" @click.prevent="$wire.editThreadMessage({{ $index }})" class="text-blue-600 hover:text-blue-800 p-1">
                                            <i class='bx bx-edit-alt'></i>
                                        </button>
                                        <button type="button" @click.prevent="$wire.removeThreadMessage({{ $index }})" class="text-red-600 hover:text-red-800 p-1">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">Tweet {{ $index + 1 }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <div class="flex-1 ">
                    <form wire:submit.prevent="savePost">
                <div class="relative mb-2">
                    <textarea
                        wire:model="message"
                        x-ref="textarea"
                        rows="4"
                        maxlength="2800"
                        placeholder="Write here.\n\nSkip 3 lines to start a thread."
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none text-gray-800 bg-white shadow-sm"
                                @keyup="checkThread($event.target.value)"
                    ></textarea>
                    <div class="absolute bottom-2 right-4 text-xs text-gray-400">
                        {{ strlen($message ?? '') }} / 2800 saved <span class="text-green-500">&#10003;</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2 mb-4">
                    <input type="file" class="hidden" x-ref="assetInput" wire:model="assetUpload" accept="image/*">
                    <button type="button" class="p-2 text-gray-500 hover:text-blue-600" @click="$refs.assetInput.click()">
                        <i class='bx bx-image text-xl'></i>
                    </button>
                    @if ($assetUpload)
                        <button type="button" wire:click="uploadAsset" class="text-xs bg-blue-100 text-blue-700 rounded px-2 py-1">Add to Message</button>
                    @endif
                    <template x-if="$wire.message && $wire.message.match(/\[img:([a-zA-Z0-9]+)\]/)">
                        <span class="text-xs text-blue-600 ml-2">Image code inserted</span>
                    </template>
                    <div class="relative" x-data="{ showEmoji: false }">
                        <button type="button" class="p-2 text-gray-500 hover:text-blue-600"
                            @click="showEmoji = !showEmoji">
                            <i class='bx bx-smile text-xl'></i>
                        </button>
                        <div x-show="showEmoji" @click.away="showEmoji = false" class="absolute z-50 mt-2">
                            <emoji-picker @emoji-click="
                                showEmoji = false;
                                const textarea = $refs.textarea;
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                const val = textarea.value;
                                const emoji = $event.detail.unicode;
                                textarea.value = val.slice(0, start) + emoji + val.slice(end);
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                textarea.focus();
                                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                            "></emoji-picker>
                        </div>
                    </div>
                    <button class="p-2 text-gray-500 hover:text-blue-600" type="button"><i class='bx bx-gif text-xl'></i></button>
                    <button class="p-2 text-gray-500 hover:text-blue-600" type="button"><i class='bx bx-font text-xl'></i></button>

                            <template x-if="threadStarted">
                                <button type="button" wire:click="addToThread" class="bg-blue-600 text-white rounded-lg px-3 py-1 text-sm font-semibold shadow hover:bg-blue-700 disabled:opacity-50"
                                    :disabled="!canTweet">
                                    <span wire:loading.remove wire:target="addToThread">Add to Thread</span>
                                    <span wire:loading wire:target="addToThread"><i class='bx bx-loader-alt bx-spin'></i></span>
                                </button>
                            </template>
                </div>
                <div class="mb-4 relative">
                    <button type="button" @click="if (!assetPickerOpen) { $wire.loadUserAssets(); } assetPickerOpen = !assetPickerOpen" class="text-xs bg-gray-100 text-gray-700 rounded px-2 py-1 border border-gray-300 hover:bg-blue-50">Select from Asset</button>
                    <div x-show="assetPickerOpen" class="absolute z-50 mt-2 w-64 bg-white border border-gray-300 rounded shadow-lg max-h-48 overflow-y-auto" @click.away="assetPickerOpen = false">
                        @forelse ($userAssets as $asset)
                            <button type="button" @click.prevent="$wire.selectAsset('{{ $asset->code }}'); assetPickerOpen = false;" class="flex items-center w-full px-2 py-2 hover:bg-blue-50">
                                <img src="{{ asset('storage/' . $asset->path) }}" alt="asset" class="w-10 h-10 object-cover rounded mr-2">
                                <span class="text-xs text-gray-600 truncate">{{ $asset->original_name }}</span>
                            </button>
                        @empty
                            <div class="p-2 text-xs text-gray-400">No assets found.</div>
                        @endforelse
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="submit" class="bg-white border border-blue-600 text-blue-600 rounded-lg px-4 py-2 font-semibold shadow hover:bg-blue-50 disabled:opacity-50"
                        :disabled="!canTweet"
                        wire:loading.attr="disabled" wire:target="savePost">
                        <span wire:loading.remove wire:target="savePost">Tweet now</span>
                        <span wire:loading wire:target="savePost"><i class='bx bx-loader-alt bx-spin'></i> Posting...</span>
                    </button>
                    <div class="relative">
                                <button type="button" 
                                    @click="showSchedulePicker = !showSchedulePicker" 
                                    class="bg-blue-900 text-white rounded-lg px-4 py-2 font-semibold shadow flex items-center hover:bg-blue-800"
                                    :class="{'bg-blue-800': showSchedulePicker}"
                                    :disabled="!canTweet">
                            Add to Queue
                                    <span class="ml-2 text-xs">{{ $scheduledDateTime ? \Carbon\Carbon::parse($scheduledDateTime)->format('M jS, Y, g:i A') : 'Select time' }}</span>
                                    <i class='bx bx-chevron-down ml-1' :class="{'transform rotate-180': showSchedulePicker}"></i>
                                </button>
                                <!-- Schedule picker dropdown -->
                                <div x-show="showSchedulePicker" 
                                    @click.away="showSchedulePicker = false" 
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 bg-white rounded-lg shadow-lg p-4 z-50 w-72">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule for</label>
                                        <input type="datetime-local" 
                                            wire:model.live="scheduledDateTime"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            min="{{ now()->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" 
                                            wire:click="schedulePost" 
                                            class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-semibold shadow hover:bg-blue-700"
                                            :disabled="!canTweet">
                                            <span wire:loading.remove wire:target="schedulePost">Schedule Post</span>
                                            <span wire:loading wire:target="schedulePost"><i class='bx bx-loader-alt bx-spin'></i></span>
                        </button>
                                    </div>
                                </div>
                            </div>
                    </div>
                    </form>
                </div>
                </div>
                <!-- Advanced Options -->
                <div class="mt-4">
                    <button type="button" class="flex items-center text-gray-600 hover:text-blue-700 focus:outline-none" onclick="document.getElementById('adv-options').classList.toggle('hidden')">
                        <span class="mr-2">Advanced Options</span>
                        <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">enabled: auto-retweet, auto-plug</span>
                        <i class='bx bx-chevron-right ml-2'></i>
                    </button>
                    <div id="adv-options" class="hidden mt-2 text-sm text-gray-500">
                        <ul class="list-disc ml-6">
                            <li>Auto-retweet: Enabled</li>
                            <li>Auto-plug: Enabled</li>
                            <!-- Add more advanced options here -->
                        </ul>
                    </div>
                </div>
        @elseif ($activeTab === 'scheduled')
            @if (count($scheduledPosts) > 0)
                <div class="space-y-4">
                    @foreach ($scheduledPosts as $post)
                        <div class="bg-gray-50 rounded-lg shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach(array_filter(explode(' ', $post->content), fn($part) => Str::startsWith($part, '[img:')) as $imgCode)
                                                @php
                                                    $code = Str::between($imgCode, '[img:', ']');
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if($asset)
                                                    <img src="{{ asset('storage/' . $asset->path) }}" alt="Post image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <button wire:click="editScheduledPost({{ $post->id }})" 
                                        class="p-1.5 text-gray-500 hover:text-blue-600 rounded-full hover:bg-blue-50 transition-colors duration-200">
                                        <i class='bx bx-edit-alt text-xl'></i>
                                    </button>
                                    <button wire:click="deleteScheduledPost({{ $post->id }})" 
                                        class="p-1.5 text-gray-500 hover:text-red-600 rounded-full hover:bg-red-50 transition-colors duration-200">
                                        <i class='bx bx-trash text-xl'></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-3 text-gray-500">
                                    <div class="flex items-center">
                                        <i class='bx bx-calendar mr-1'></i>
                                        {{ $post->scheduled_at->format('M j, Y') }}
                                    </div>
                                    <div class="flex items-center">
                                        <i class='bx bx-time mr-1'></i>
                                        {{ $post->scheduled_at->format('g:i A') }}
                                    </div>
                                </div>
                                <div class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                    Scheduled
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <i class='bx bx-calendar text-5xl mb-2'></i>
                    <span class="text-lg font-semibold">No scheduled posts</span>
                    <p class="text-sm text-gray-400 text-center mt-1">
                        Your scheduled posts will appear here.<br>
                        Use the "Add to Queue" button to schedule a post.
                    </p>
                </div>
            @endif
        @elseif ($activeTab === 'drafts')
            @if (count($drafts) > 0)
                <div class="space-y-4">
                    @foreach ($drafts as $draft)
                        <button wire:click="continueDraft({{ $draft->id }})" type="button" 
                            class="w-full text-left bg-gray-50 rounded-lg shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $draft->content }}</div>
                                    @if (Str::contains($draft->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach(array_filter(explode(' ', $draft->content), fn($part) => Str::startsWith($part, '[img:')) as $imgCode)
                                                @php
                                                    $code = Str::between($imgCode, '[img:', ']');
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if($asset)
                                                    <img src="{{ asset('storage/' . $asset->path) }}" alt="Draft image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-3 text-gray-500">
                                    <div class="flex items-center">
                                        <i class='bx bx-pencil mr-1'></i>
                                        Last edited
                                    </div>
                                    <div>
                                        {{ $draft->updated_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-50 text-yellow-600">
                                    Draft
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <i class='bx bx-file text-5xl mb-2'></i>
                    <span class="text-lg font-semibold">No drafts yet</span>
                    <p class="text-sm text-gray-400 text-center mt-1">
                        Your saved drafts will appear here.<br>
                        Start typing and we'll automatically save your draft.
                    </p>
                </div>
            @endif
        @elseif ($activeTab === 'sent')
            @if (count($sentPosts ?? []) > 0)
                <div class="mb-4 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-700">Sent Posts</h2>
                    <button wire:click="clearAllSentPosts" 
                        wire:confirm="Are you sure you want to clear all sent posts? This cannot be undone."
                        class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100 transition-colors duration-200">
                        Clear All
                    </button>
                </div>
                <div class="space-y-4">
                    @foreach ($sentPosts as $post)
                        <div class="bg-gray-50 rounded-lg shadow-sm border border-gray-100 p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach(array_filter(explode(' ', $post->content), fn($part) => Str::startsWith($part, '[img:')) as $imgCode)
                                                @php
                                                    $code = Str::between($imgCode, '[img:', ']');
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if($asset)
                                                    <img src="{{ asset('storage/' . $asset->path) }}" alt="Sent image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center">
                                    <button wire:click="deleteSentPost({{ $post->id }})"
                                        wire:confirm="Are you sure you want to delete this post?"
                                        class="p-1.5 text-gray-500 hover:text-red-600 rounded-full hover:bg-red-50 transition-colors duration-200">
                                        <i class='bx bx-trash text-xl'></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-3 text-gray-500">
                                    <div class="flex items-center">
                                        <i class='bx bx-check-circle mr-1'></i>
                                        Posted
                                    </div>
                                    <div>
                                        {{ $post->sent_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600">
                                    Sent
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                <i class='bx bx-send text-5xl mb-2'></i>
                    <span class="text-lg font-semibold">No sent posts</span>
                    <p class="text-sm text-gray-400 text-center mt-1">
                        Your sent posts will appear here.<br>
                        Click "Tweet now" to post your first tweet.
                    </p>
            </div>
            @endif
        @endif
    </div>
    <div>
        @if ($assetUpload)
            <div class="p-2 bg-green-100 text-green-700">File selected: {{ $assetUpload->getClientOriginalName() }}</div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('update-alpine-message', function(e) {
        if (window.chatComponent && typeof e.detail.message !== 'undefined') {
            window.chatComponent.message = e.detail.message;
            const textarea = document.querySelector('[x-ref="textarea"]');
            if (textarea) {
                textarea.value = e.detail.message;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }
            // Force Alpine to flush updates if available
            if (window.Alpine && typeof window.Alpine.flush === 'function') {
                window.Alpine.flush();
            }
        }
    });
</script>

<!-- Add emoji picker script -->
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
