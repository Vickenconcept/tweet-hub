@php
    use Illuminate\Support\Str;
@endphp
<div class="flex flex-col h-full w-full" x-data="{
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
            this.canTweet = value && (value.trim().length > 0 || /\[(img|vid|gif):([a-zA-Z0-9]+)\]/.test(value)) || $wire.threadMessages.length > 0;
        });
        
        this.$watch('$wire.threadMessages', messages => {
            if (messages && messages.length > 0) {
                this.threadStarted = true;
            }
        });
    }
}" x-init="$watch('$wire.message', value => {
            canTweet = value && (value.trim().length > 0 || /\[(img|vid|gif):([a-zA-Z0-9]+)\]/.test(value)) || $wire.threadMessages.length > 0;
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
});">
    <!-- Tabs -->
    <div class="flex items-center bg-gray-100 p-1 rounded-xl mx-4 mt-4">
        <button wire:click="setTab('compose')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-xl transition-colors cursor-pointer {{ $activeTab === 'compose' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">Compose</button>
        <button wire:click="setTab('drafts')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-xl transition-colors cursor-pointer {{ $activeTab === 'drafts' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">Drafts</button>
        <button wire:click="setTab('scheduled')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-xl transition-colors cursor-pointer {{ $activeTab === 'scheduled' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">Scheduled</button>
        <button wire:click="setTab('sent')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-xl transition-colors cursor-pointer {{ $activeTab === 'sent' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">Sent</button>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col px-6 py-4 h-full">
        @if ($activeTab === 'compose')
            @if ($successMessage)
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
                    {{ $successMessage }}</div>
            @endif
            @if ($errorMessage)
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">{{ $errorMessage }}</div>
            @endif
            <div class="flex items-center justify-between mb-6">
                <span class="text-lg font-bold text-gray-900">Post Content</span>
                <div class="flex justify-end items-center gap-2">
                    <button type="button" @click="$wire.endThread()" x-show="threadStarted" style="display: none;"
                        class="px-3 py-1 text-xs font-medium text-red-600 bg-gradient-to-r from-red-100 to-red-200 rounded-xl hover:bg-red-200 transition-colors cursor-pointer">
                        <i class='bx bx-x-circle mr-1'></i> Exit Thread
                    </button>
                </div>
            </div>
            <div class=" gap-4 grid grid-cols-6">
                <!-- Thread Messages Panel -->
                <div class="col-span-2" x-show="threadStarted">
                    <div class="mb-4">
                        {{-- <div class="text-lg font-bold text-gray-900 mb-2">Thread Messages</div> --}}
                        {{-- <button type="button" @click="$wire.endThread()"
                            class="px-3 py-1 text-xs font-medium text-red-600 bg-gradient-to-r from-red-100 to-red-200 rounded-xl hover:bg-red-200 transition-colors cursor-pointer">
                            <i class='bx bx-x-circle mr-1'></i> Exit Thread
                        </button> --}}
                    </div>
                    @if (empty($threadMessages))
                        <div class="text-sm text-gray-500">No messages in thread yet.</div>
                    @else
                        <div class="space-y-3">
                            @foreach ($threadMessages as $index => $threadMessage)
                                <div
                                    class="relative group bg-white p-4 rounded-2xl shadow-2xl shadow-gray-200 border border-gray-100">
                                    <div class="text-sm text-gray-800 leading-relaxed">
                                        {{ Str::limit($threadMessage, 100) }}</div>
                                    <div class="absolute top-2 right-2 hidden group-hover:flex gap-1">
                                        <button type="button"
                                            @click.prevent="$wire.editThreadMessage({{ $index }})"
                                            class="p-2 text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 hover:bg-blue-200 rounded-xl transition-colors cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                            @click.prevent="$wire.removeThreadMessage({{ $index }})"
                                            class="p-2 text-red-600 bg-gradient-to-r from-red-100 to-red-200 hover:bg-red-200 rounded-xl transition-colors cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 mt-2 bg-gray-100 px-2 py-1 rounded-lg inline-block">
                                        Tweet {{ $index + 1 }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <div class="col-span-4" :class="{ 'col-span-4': threadStarted, 'col-span-6': !threadStarted }">
                    <form wire:submit.prevent="savePost">
                        <div class="relative mb-4">
                            <textarea wire:model="message" x-ref="textarea" rows="4" maxlength="280"
                                placeholder="Write here. Press enter key 3 times to start a thread."
                                class="w-full rounded-2xl border border-gray-300 px-6 py-4 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none text-gray-800 bg-white shadow-2xl shadow-gray-200 text-lg"
                                @keyup="checkThread($event.target.value)"></textarea>
                            <div class="absolute bottom-3 right-6 text-sm bg-white px-3 py-1 rounded-xl shadow-lg"
                         :class="{
                             'text-gray-400': $wire.message && $wire.message.length <= 260,
                                    'text-yellow-500': $wire.message && $wire.message.length > 260 && $wire.message
                                        .length <= 279,
                             'text-red-500': $wire.message && $wire.message.length > 279
                         }">
                        <span x-text="$wire.message ? $wire.message.length : 0"></span> / 279
                                <span x-show="$wire.message && $wire.message.length <= 280"
                                    class="text-green-500 ml-1">&#10003;</span>
                                <span x-show="$wire.message && $wire.message.length > 280"
                                    class="text-red-500 ml-1">&#10005;</span>
                    </div>
                </div>
                        <div class="flex items-center space-x-3 mb-6">
                                                         <input type="file" class="hidden" x-ref="assetInput" wire:model.live="assetUpload"
                                 accept="image/*">
                            <button type="button"
                                class="p-3 text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 hover:bg-gray-200 rounded-xl transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="$refs.assetInput.click()"
                                wire:loading.attr="disabled"
                                wire:target="assetUpload">
                                <div wire:loading.remove wire:target="assetUpload">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </div>
                                <div wire:loading wire:target="assetUpload" class="flex items-center justify-center">
                                    <svg class="animate-spin size-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                    </button>

                            
                    <template x-if="$wire.message && $wire.message.match(/\[(img|vid|gif):([a-zA-Z0-9]+)\]/)">
                                <span class="text-sm text-blue-600 bg-blue-100 px-3 py-1 rounded-xl flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    Media attached
                                </span>
                    </template>
                    <div class="relative" x-data="{ showEmoji: false }">
                                <button type="button"
                                    class="p-3 text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 hover:bg-gray-200 rounded-xl transition-colors cursor-pointer"
                            @click="showEmoji = !showEmoji">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                                    </svg>
                        </button>
                        <div x-show="showEmoji" @click.away="showEmoji = false" class="absolute z-50 mt-2">
                                    <emoji-picker
                                        @emoji-click="
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
                            {{-- <button class="p-3 text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 hover:bg-gray-200 rounded-xl transition-colors cursor-pointer" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                        </svg>
                    </button>
                    <button class="p-3 text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 hover:bg-gray-200 rounded-xl transition-colors cursor-pointer" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                    </button> --}}

                            <template x-if="threadStarted">
                                <button type="button" wire:click="addToThread"
                                    class="px-6 py-3 bg-gradient-to-r  from-blue-400 to-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors cursor-pointer shadow-2xl shadow-gray-200"
                                    :disabled="!canTweet">
                                    <span wire:loading.remove wire:target="addToThread">Add to Thread</span>
                                    <span wire:loading wire:target="addToThread"><i
                                            class='bx bx-loader-alt bx-spin'></i></span>
                                </button>
                            </template>
                </div>
                        <div class="mb-6 relative">
                            <button type="button"
                                @click="if (!assetPickerOpen) { $wire.loadUserAssets(); } assetPickerOpen = !assetPickerOpen"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 transition-colors cursor-pointer border border-gray-500">Select
                                from Asset</button>
                            <div x-show="assetPickerOpen"
                                class="absolute z-50 mt-2 w-64 bg-white border border-gray-300 rounded-2xl shadow-2xl shadow-gray-200 max-h-48 overflow-y-auto"
                                @click.away="assetPickerOpen = false">
                        @forelse ($userAssets as $asset)
                                    <button type="button"
                                        @click.prevent="$wire.selectAsset('{{ $asset->code }}'); assetPickerOpen = false;"
                                        class="flex items-center w-full px-4 py-3 hover:bg-blue-50 rounded-xl transition-colors">
                                        <img src="{{ $asset->path }}" alt="asset"
                                            class="w-12 h-12 object-cover rounded-xl mr-3">
                                        <span
                                            class="text-sm text-gray-600 truncate">{{ $asset->original_name }}</span>
                            </button>
                        @empty
                                    <div class="p-4 text-sm text-gray-400">No assets found.</div>
                        @endforelse
                    </div>
                </div>
                        <div class="flex items-center space-x-2 space-y-2 flex-wrap">
                            <button type="submit"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-green-400 to-green-600 text-white font-medium rounded-xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-2xl shadow-gray-200"
                                :disabled="!canTweet" wire:loading.attr="disabled" wire:target="savePost">
                                <span wire:loading.remove wire:target="savePost" class="text-white text-nowrap">Tweet
                                    now</span>
                                <span wire:loading wire:target="savePost">
                                    <i class='bx bx-loader-alt bx-spin'></i>
                                    Posting...
                                </span>
                    </button>
                            <div class="relative flex-1">
                                <button type="button" @click="showSchedulePicker = !showSchedulePicker"
                                    class="px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white w-full font-medium rounded-xl hover:bg-blue-700 transition-colors cursor-pointer shadow-2xl shadow-gray-200 flex items-center text-nowrap"
                                    :class="{ 'bg-blue-700': showSchedulePicker }" :disabled="!canTweet">
                            Add to Queue
                                    <span
                                        class="ml-2 text-sm">{{ $scheduledDateTime ? \Carbon\Carbon::parse($scheduledDateTime)->format('M jS, Y, g:i A') : 'Select time' }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor"
                                        class="size-4 ml-1 transition-transform duration-200"
                                        :class="{ 'rotate-180': showSchedulePicker }">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <!-- Schedule picker dropdown -->
                                <div x-show="showSchedulePicker" @click.away="showSchedulePicker = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 bg-white rounded-2xl shadow-2xl shadow-gray-200 p-6 z-50 w-80">
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-3">Schedule
                                            for</label>
                                        <input type="datetime-local" wire:model.live="scheduledDateTime"
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 px-4 py-3 text-lg"
                                            min="{{ now()->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="schedulePost"
                                            class="px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition-colors cursor-pointer shadow-2xl shadow-gray-200"
                                            :disabled="!canTweet">
                                            <span wire:loading.remove wire:target="schedulePost">Schedule Post</span>
                                            <span wire:loading wire:target="schedulePost"><i
                                                    class='bx bx-loader-alt bx-spin'></i></span>
                        </button>
                                    </div>
                                </div>
                            </div>
                    </div>
                    </form>
                </div>
                </div>
                <!-- Advanced Options -->
            {{-- <div class="mt-4">
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
                </div> --}}
        @elseif ($activeTab === 'scheduled')
            @if (count($scheduledPosts) > 0)
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($scheduledPosts as $post)
                        <div
                            class="bg-white rounded-2xl shadow-2xl shadow-gray-200 p-6 hover:shadow-3xl hover:shadow-blue-200 transition-all duration-300 border border-gray-100">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (array_filter(explode(' ', $post->content), fn($part) => preg_match('/\[(img|vid|gif):/', $part)) as $assetCode)
                                                @php
                                                    preg_match('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', $assetCode, $matches);
                                                    $code = $matches[2] ?? '';
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if ($asset)
                                                    <img src="{{ $asset->path }}"
                                                        alt="Post image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="editScheduledPost({{ $post->id }})" 
                                        class="p-3 text-blue-600 bg-gradient-to-r from-blue-100 to-blue-200 hover:bg-blue-200 rounded-xl transition-colors cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteScheduledPost({{ $post->id }})" 
                                        class="p-3 text-red-600 bg-gradient-to-r from-red-100 to-red-200 hover:bg-red-200 rounded-xl transition-colors cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div class="flex items-center bg-gray-100 px-3 py-1 rounded-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        {{ $post->scheduled_at->format('M j, Y') }}
                                    </div>
                                    <div class="flex items-center bg-gray-100 px-3 py-1 rounded-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $post->scheduled_at->format('g:i A') }}
                                    </div>
                                </div>
                                <div
                                    class="px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-blue-100 to-blue-200 text-blue-600">
                                    Scheduled
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-16 text-blue-500 mx-auto mb-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-700 mb-4">No Scheduled Posts</h3>
                        <p class="text-gray-600 mb-4">Your scheduled posts will appear here</p>
                        <p class="text-xs text-gray-500 text-center">
                            Use the "Add to Queue" button to schedule a post
                        </p>
                    </div>
                </div>
            @endif
        @elseif ($activeTab === 'drafts')
            @if (count($drafts) > 0)
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($drafts as $draft)
                        <button wire:click="continueDraft({{ $draft->id }})" type="button" 
                            class="w-full text-left bg-white rounded-2xl shadow-2xl shadow-gray-200 p-6 hover:shadow-3xl hover:shadow-yellow-200 transition-all duration-300 border border-gray-100">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $draft->content }}</div>
                                    @if (Str::contains($draft->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (array_filter(explode(' ', $draft->content), fn($part) => Str::startsWith($part, '[img:')) as $imgCode)
                                                @php
                                                    $code = Str::between($imgCode, '[img:', ']');
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if ($asset)
                                                    <img src="{{ $asset->path }}"
                                                        alt="Draft image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div class="flex items-center bg-gray-100 px-3 py-1 rounded-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                        Last edited {{ $draft->updated_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div
                                    class="px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-600">
                                    Draft
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-16 text-yellow-500 mx-auto mb-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-700 mb-4">No Drafts Yet</h3>
                        <p class="text-gray-600 mb-4">Your saved drafts will appear here</p>
                        <p class="text-xs text-gray-500 text-center">
                            Start typing and we'll automatically save your draft
                        </p>
                    </div>
                </div>
            @endif
        @elseif ($activeTab === 'sent')
            @if (count($sentPosts ?? []) > 0)
                <div class="mb-6 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900">Sent Posts</h2>
                    <button wire:click="clearAllSentPosts" 
                        wire:confirm="Are you sure you want to clear all sent posts? This cannot be undone."
                        class="px-4 py-2 text-sm font-medium text-red-600 bg-gradient-to-r from-red-100 to-red-200 rounded-xl hover:bg-red-200 transition-colors cursor-pointer">
                        Clear All
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($sentPosts as $post)
                        <div class="bg-white rounded-2xl shadow-2xl shadow-gray-200 p-6 border border-gray-100">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (array_filter(explode(' ', $post->content), fn($part) => preg_match('/\[(img|vid|gif):/', $part)) as $assetCode)
                                                @php
                                                    preg_match('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', $assetCode, $matches);
                                                    $code = $matches[2] ?? '';
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if ($asset)
                                                    <img src="{{ $asset->path }}"
                                                        alt="Sent image" class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center">
                                    <button wire:click="deleteSentPost({{ $post->id }})"
                                        wire:confirm="Are you sure you want to delete this post?"
                                        class="p-3 text-red-600 bg-gradient-to-r from-red-100 to-red-200 hover:bg-red-200 rounded-xl transition-colors cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div class="flex items-center bg-gray-100 px-3 py-1 rounded-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        Posted {{ $post->sent_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <div
                                    class="px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-green-100 to-green-200 text-green-600">
                                    Sent
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-16 text-green-500 mx-auto mb-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-700 mb-4">No Sent Posts</h3>
                        <p class="text-gray-600 mb-4">Your sent posts will appear here</p>
                        <p class="text-xs text-gray-500 text-center">
                            Click "Tweet now" to post your first tweet
                        </p>
                    </div>
            </div>
            @endif
        @endif
    </div>
    <div>
        @if ($assetUpload)
            <div class="p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded-xl">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-4.5-4.5V7.5a4.5 4.5 0 014.5-4.5h10.5a4.5 4.5 0 014.5 4.5v7.5a4.5 4.5 0 01-4.5 4.5H6.75z" />
                    </svg>
                    Uploading {{ $assetUpload->getClientOriginalName() }}...
                </div>
            </div>
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
                textarea.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
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
