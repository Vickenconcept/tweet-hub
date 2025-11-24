@php
    use Illuminate\Support\Str;
@endphp
<div class="flex flex-col h-full w-full overflow-y-auto" x-data="{
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
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-2 mx-4 mt-4">
        <div class="flex space-x-1">
            <button wire:click="setTab('compose')"
                class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'compose' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Compose</button>
            <button wire:click="setTab('drafts')"
                class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'drafts' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Drafts</button>
            <button wire:click="setTab('scheduled')"
                class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'scheduled' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Scheduled</button>
            <button wire:click="setTab('sent')"
                class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'sent' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Sent</button>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col px-6 py-4 h-full">
        @php
            $userTimezone = auth()->user()?->timezone ?? $timezone ?? config('app.timezone');
        @endphp
        @if ($activeTab === 'compose')
            @if ($successMessage)
                <div class="mb-4 bg-white rounded-3xl shadow-sm border border-green-200 p-4">
                    <div class="flex items-center text-green-700">
                        <i class="bx bx-check-circle mr-2 text-lg"></i>
                        <span>{{ $successMessage }}</span>
                    </div>
                </div>
            @endif
            @if ($errorMessage)
                <div class="mb-4 bg-white rounded-3xl shadow-sm border border-red-200 p-4">
                    <div class="flex items-center text-red-700">
                        <i class="bx bx-error-circle mr-2 text-lg"></i>
                        <span>{{ $errorMessage }}</span>
                    </div>
                </div>
            @endif
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Compose</p>
                    <span class="text-xl md:text-2xl font-semibold text-gray-900">Post Content</span>
                </div>
                <div class="flex justify-end items-center gap-2">
                    <button type="button" @click="$wire.endThread()" x-show="threadStarted" style="display: none;"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition-colors cursor-pointer">
                        <i class='bx bx-x-circle'></i> Exit Thread
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
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 text-center">
                            <p class="text-sm text-gray-500">No messages in thread yet.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($threadMessages as $index => $threadMessage)
                                <div
                                    class="relative group bg-white p-4 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                                    <div class="text-sm text-gray-800 leading-relaxed">
                                        {{ Str::limit($threadMessage, 100) }}</div>
                                    <div class="absolute top-2 right-2 hidden group-hover:flex gap-1">
                                        <button type="button"
                                            @click.prevent="$wire.editThreadMessage({{ $index }})"
                                            class="p-2 text-green-600 bg-green-50 border border-green-200 hover:bg-green-100 rounded-xl transition-colors cursor-pointer">
                                            <i class="bx bx-edit text-sm"></i>
                                        </button>
                                        <button type="button"
                                            @click.prevent="$wire.removeThreadMessage({{ $index }})"
                                            class="p-2 text-red-600 bg-red-50 border border-red-200 hover:bg-red-100 rounded-xl transition-colors cursor-pointer">
                                            <i class="bx bx-trash text-sm"></i>
                                        </button>
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 mt-2 bg-gray-50 border border-gray-200 px-2 py-1 rounded-lg inline-block font-semibold">
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
                                class="w-full rounded-2xl border border-gray-200 px-6 py-4 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none text-gray-800 bg-white shadow-sm text-sm"
                                @keyup="checkThread($event.target.value)"></textarea>
                            <div class="absolute bottom-3 right-6 text-sm bg-white px-3 py-1 rounded-xl shadow-sm border border-gray-200"
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
                                class="p-3 text-gray-600 bg-gray-50 border border-gray-200 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="$refs.assetInput.click()" wire:loading.attr="disabled"
                                wire:target="assetUpload">
                                <div wire:loading.remove wire:target="assetUpload">
                                    <i class="bx bx-image text-lg"></i>
                                </div>
                                <div wire:loading wire:target="assetUpload" class="flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-green-600"></div>
                                </div>
                            </button>

                            <!-- AI Image Generation Button -->
                            <button type="button"
                                class="p-3 text-purple-600 bg-purple-50 border border-purple-200 hover:bg-purple-100 rounded-xl transition-colors cursor-pointer"
                                @click="$wire.toggleImageGenerator()" title="Generate AI Image with DALL-E">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>

                            </button>


                            <template x-if="$wire.message && $wire.message.match(/\[(img|vid|gif):([a-zA-Z0-9]+)\]/)">
                                <span
                                    class="text-sm text-green-600 bg-green-50 border border-green-200 px-3 py-1 rounded-xl flex items-center gap-2">
                                    <i class="bx bx-image"></i>
                                    Media attached
                                </span>
                            </template>
                            <div class="relative">
                                <button type="button"
                                    id="emoji-button"
                                    class="p-3 text-gray-600 bg-gray-50 border border-gray-200 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer">
                                    <i class="bx bx-smile text-lg"></i>
                                </button>
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
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-sm"
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
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 border border-gray-200 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer">Select
                                from Asset</button>
                            <div x-show="assetPickerOpen"
                                class="absolute z-50 mt-2 w-64 bg-white border border-gray-200 rounded-2xl shadow-lg max-h-48 overflow-y-auto"
                                @click.away="assetPickerOpen = false">
                                @forelse ($userAssets as $asset)
                                    <button type="button"
                                        @click.prevent="$wire.selectAsset('{{ $asset->code }}'); assetPickerOpen = false;"
                                        class="flex items-center w-full px-4 py-3 hover:bg-green-50 rounded-xl transition-colors">
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
                                class="text-sm flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 disabled:opacity-50 transition-colors cursor-pointer shadow-sm"
                                :disabled="!canTweet" wire:loading.attr="disabled" wire:target="savePost">
                                <span wire:loading.remove wire:target="savePost" class="text-nowrap">Tweet now</span>
                                <span wire:loading wire:target="savePost">
                                    <i class='bx bx-loader-alt bx-spin'></i>
                                    Posting...
                                </span>
                            </button>
                            <div class="relative flex-1">
                                <button type="button" @click="showSchedulePicker = !showSchedulePicker"
                                    class="text-sm inline-flex items-center justify-between gap-2 px-6 py-3 bg-gray-100 border border-gray-200 text-gray-700 w-full font-semibold rounded-xl hover:bg-gray-200 transition-colors cursor-pointer text-left"
                                    :class="{ 'bg-gray-200': showSchedulePicker }" :disabled="!canTweet">
                                    <div>
                                        <span class="block text-sm text-nowrap">Add to Queue</span>
                                        <span class="text-xs text-gray-500 text-nowrap">
                                            {{ $scheduledDateTime ? \Carbon\Carbon::parse($scheduledDateTime, $userTimezone)->format('M jS, Y, g:i A') : '' }}
                                            ({{ $userTimezone }})
                                        </span>
                                    </div>
                                    <i class="bx bx-chevron-down text-lg ml-2 transition-transform duration-200"
                                        :class="{ 'rotate-180': showSchedulePicker }"></i>
                                </button>
                                <!-- Schedule picker dropdown -->
                                <div x-show="showSchedulePicker" @click.away="showSchedulePicker = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 bg-white rounded-2xl shadow-lg border border-gray-200 p-6 z-50 w-80">
                                    <div class="mb-6">
                                        <label class="block text-sm font-semibold text-gray-800 mb-3">Schedule
                                            for ({{ $userTimezone }})</label>
                                        <input type="datetime-local" wire:model.live="scheduledDateTime"
                                            class="w-full rounded-2xl border-gray-200 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 px-4 py-3 text-sm"
                                            min="{{ now($userTimezone)->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="schedulePost"
                                            class="text-sm inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition-colors cursor-pointer shadow-sm"
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
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 mt-2 mb-6 flex flex-col gap-3 md:flex-col ">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-gray-400 mb-1">Scheduling Timezone</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $userTimezone }}</p>
                    <p class="text-xs text-gray-500">All queued posts use this timezone.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center w-full md:w-auto">
                    <select wire:model="timezone"
                        class="flex-1 rounded-2xl border border-gray-300 bg-white px-4 py-2.5 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 text-sm">
                        @foreach ($timezoneOptions as $zone => $label)
                            <option value="{{ $zone }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="updateTimezone"
                        class="text-sm inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 transition-colors cursor-pointer">
                        <i class="bx bx-save"></i>
                        Save
                    </button>
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
            <div class="mb-6">
                <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Scheduled</p>
                <h2 class="text-xl md:text-2xl font-semibold text-gray-900 mt-1">Scheduled Posts</h2>
            </div>
            @if (count($scheduledPosts) > 0)
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($scheduledPosts as $post)
                        <div
                            class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (array_filter(explode(' ', $post->content), fn($part) => preg_match('/\[(img|vid|gif):/', $part)) as $assetCode)
                                                @php
                                                    preg_match(
                                                        '/\[(img|vid|gif):([a-zA-Z0-9]+)\]/',
                                                        $assetCode,
                                                        $matches,
                                                    );
                                                    $code = $matches[2] ?? '';
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if ($asset)
                                                    <img src="{{ $asset->path }}" alt="Post image"
                                                        class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="editScheduledPost({{ $post->id }})"
                                        class="p-2.5 text-green-600 bg-green-50 border border-green-200 hover:bg-green-100 rounded-xl transition-colors cursor-pointer">
                                        <i class="bx bx-edit text-lg"></i>
                                    </button>
                                    <button wire:click="deleteScheduledPost({{ $post->id }})"
                                        class="p-2.5 text-red-600 bg-red-50 border border-red-200 hover:bg-red-100 rounded-xl transition-colors cursor-pointer">
                                        <i class="bx bx-trash text-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1 rounded-xl text-xs">
                                        <i class="bx bx-calendar text-sm"></i>
                                        {{ $post->scheduled_at->timezone($userTimezone)->format('M j, Y') }}
                                    </div>
                                    <div
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1 rounded-xl text-xs">
                                        <i class="bx bx-time text-sm"></i>
                                        {{ $post->scheduled_at->timezone($userTimezone)->format('g:i A') }}
                                    </div>
                                </div>
                                <div
                                    class="px-3 py-1 rounded-xl font-semibold bg-green-50 border border-green-200 text-green-600 text-xs">
                                    Scheduled
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <i class="bx bx-calendar text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Scheduled Posts</h3>
                    <p class="text-gray-600 mb-1">Your scheduled posts will appear here</p>
                    <p class="text-sm text-gray-500">Use the "Add to Queue" button to schedule a post</p>
                </div>
            @endif
        @elseif ($activeTab === 'drafts')
            <div class="mb-6">
                <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Drafts</p>
                <h2 class="text-xl md:text-2xl font-semibold text-gray-900 mt-1">Saved Drafts</h2>
            </div>
            @if (count($drafts) > 0)
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($drafts as $draft)
                        <button wire:click="continueDraft({{ $draft->id }})" type="button"
                            class="w-full text-left bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
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
                                                    <img src="{{ $asset->path }}" alt="Draft image"
                                                        class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1 rounded-xl text-xs">
                                        <i class="bx bx-edit text-sm"></i>
                                        Last edited {{ $draft->updated_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div
                                    class="px-3 py-1 rounded-xl font-semibold bg-yellow-50 border border-yellow-200 text-yellow-600 text-xs">
                                    Draft
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <i class="bx bx-file-blank text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Drafts Yet</h3>
                    <p class="text-gray-600 mb-1">Your saved drafts will appear here</p>
                    <p class="text-sm text-gray-500">Start typing and we'll automatically save your draft</p>
                </div>
            @endif
        @elseif ($activeTab === 'sent')
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-gray-400">History</p>
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-900 mt-1">Sent Posts</h2>
                </div>
                @if (count($sentPosts ?? []) > 0)
                    <button wire:click="clearAllSentPosts"
                        wire:confirm="Are you sure you want to clear all sent posts? This cannot be undone."
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition-colors cursor-pointer">
                        <i class="bx bx-trash"></i>
                        Clear All
                    </button>
                @endif
            </div>
            @if (count($sentPosts ?? []) > 0)
                <div class="space-y-4 overflow-y-auto h-[80%]">
                    @foreach ($sentPosts as $post)
                        <div
                            class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-all duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 pr-4">
                                    <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                                    @if (Str::contains($post->content, '[img:'))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach (array_filter(explode(' ', $post->content), fn($part) => preg_match('/\[(img|vid|gif):/', $part)) as $assetCode)
                                                @php
                                                    preg_match(
                                                        '/\[(img|vid|gif):([a-zA-Z0-9]+)\]/',
                                                        $assetCode,
                                                        $matches,
                                                    );
                                                    $code = $matches[2] ?? '';
                                                    $asset = App\Models\Asset::where('code', $code)->first();
                                                @endphp
                                                @if ($asset)
                                                    <img src="{{ $asset->path }}" alt="Sent image"
                                                        class="h-16 w-16 object-cover rounded-md">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center">
                                    <button wire:click="deleteSentPost({{ $post->id }})"
                                        wire:confirm="Are you sure you want to delete this post?"
                                        class="p-2.5 text-red-600 bg-red-50 border border-red-200 hover:bg-red-100 rounded-xl transition-colors cursor-pointer">
                                        <i class="bx bx-trash text-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4 text-gray-600">
                                    <div
                                        class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1 rounded-xl text-xs">
                                        <i class="bx bx-check-circle text-sm"></i>
                                        Posted {{ $post->sent_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <div
                                    class="px-3 py-1 rounded-xl font-semibold bg-green-50 border border-green-200 text-green-600 text-xs">
                                    Sent
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <i class="bx bx-send text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Sent Posts</h3>
                    <p class="text-gray-600 mb-1">Your sent posts will appear here</p>
                    <p class="text-sm text-gray-500">Click "Tweet now" to post your first tweet</p>
                </div>
            @endif
        @endif
    </div>
    <div>
        @if ($assetUpload)
            <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4">
                <div class="flex items-center text-green-700">
                    <i class="bx bx-upload mr-2 text-lg"></i>
                    <span>Uploading {{ $assetUpload->getClientOriginalName() }}...</span>
                </div>
            </div>
        @endif
    </div>

    <!-- AI Image Generation Modal -->
    @if ($showImageGenerator)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click="$wire.toggleImageGenerator()">
            <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8 w-full max-w-lg" @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div
                            class="w-12 h-12 bg-purple-50 border border-purple-200 rounded-2xl flex items-center justify-center mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="size-6 text-purple-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                            </svg>

                        </div>
                        <div>
                            <p class="text-sm uppercase tracking-[0.2em] text-gray-400">AI Generator</p>
                            <h3 class="text-xl md:text-2xl font-semibold text-gray-900 mt-1">AI Image Generator</h3>
                            <p class="text-sm text-gray-600 mt-1">Powered by DALL-E 3</p>
                        </div>
                    </div>
                    <button @click="$wire.toggleImageGenerator()"
                        class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-xl transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>

                    </button>
                </div>

                @if ($errorMessage && str_contains(strtolower($errorMessage), 'image'))
                    <div class="mb-6 bg-white rounded-2xl shadow-sm border border-red-200 p-4">
                        <div class="flex items-center text-red-700">
                            <i class="bx bx-error-circle mr-2 text-lg"></i>
                            <span class="text-sm">{{ $errorMessage }}</span>
                        </div>
                    </div>
                @endif

                @if (!$generatingImage && !$generatedImageUrl)
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-800 mb-3">Describe the image you want to
                            generate</label>
                        <textarea wire:model="aiImagePrompt" rows="4"
                            placeholder="e.g., A futuristic city with flying cars at sunset, digital art style..."
                            class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm resize-none bg-white"></textarea>
                        <p class="text-xs text-gray-500 mt-2 flex items-start gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                            </svg>

                            <span><strong>Tip:</strong> Be specific and descriptive for best results. The more details
                                you provide, the better the AI can create your image.</span>
                        </p>
                    </div>
                @endif

                @if ($generatingImage)
                    <div class="py-12 text-center">
                        <div
                            class="inline-flex items-center justify-center w-20 h-20 bg-purple-50 border border-purple-200 rounded-full mb-6">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-purple-600"></div>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-2">Generating Your Image...</h4>
                        <p class="text-gray-600">This usually takes 10-30 seconds</p>
                    </div>
                @endif

                @if ($generatedImageUrl && $generatedImageCode)
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-3">Generated Image</h4>
                        <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm cursor-pointer transition-all hover:shadow-md hover:border-purple-300"
                            @click="$wire.useGeneratedImage()">
                            <img src="{{ $generatedImageUrl }}" alt="Generated AI Image" class="w-full">
                        </div>
                        <p class="text-xs text-gray-500 mt-3 text-center flex items-center justify-center gap-1">
                            <i class="bx bx-info-circle"></i>
                            <span>Click the image to add it to your post</span>
                        </p>
                    </div>
                @endif

                <div class="flex gap-3 mt-6">
                    @if (!$generatingImage && !$generatedImageUrl)
                        <button wire:click="generateAIImage" wire:loading.attr="disabled"
                            :disabled="!$wire.aiImagePrompt || $wire.aiImagePrompt.length < 10"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-purple-600 text-white font-semibold rounded-xl hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-sm">
                            <div wire:loading.remove wire:target="generateAIImage" class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-white">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>

                                <span>Generate Image</span>
                            </div>
                            <div wire:loading wire:target="generateAIImage" class="flex items-center gap-2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                                <span>Generating...</span>
                            </div>
                        </button>
                    @endif

                    @if ($generatedImageUrl && $generatedImageCode)
                        <button wire:click="useGeneratedImage" wire:loading.attr="disabled"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition-colors cursor-pointer shadow-sm">
                            <i class="bx bx-check text-lg"></i>
                            Use This Image
                        </button>
                        <button
                            wire:click="$set('generatedImageUrl', ''); $set('generatedImageCode', ''); $set('aiImagePrompt', '');"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-50 border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-100 transition-colors cursor-pointer">
                            <i class="bx bx-refresh text-lg"></i>
                            Try Again
                        </button>
                    @endif

                    @if (!$generatingImage)
                        <button @click="$wire.toggleImageGenerator()"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-50 border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

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

        // Initialize emoji picker after DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for emoji-picker-element to load
            setTimeout(function() {
                const emojiPickers = document.querySelectorAll('emoji-picker');
                emojiPickers.forEach(function(picker) {
                    // Force re-render if needed
                    if (picker && !picker.shadowRoot) {
                        // The picker should initialize automatically
                        // But we can trigger a custom event if needed
                        picker.dispatchEvent(new Event('load'));
                    }
                });
            }, 500);
        });
    </script>

    <!-- Add simple emoji picker (vanilla JS, no modules, no IndexedDB) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.css">
    <script>
        console.log('Emoji picker script started');
        let emojiPickerInitialized = false;
        let emojiPicker = null;
        
        function initEmojiPicker() {
            console.log('initEmojiPicker called, initialized:', emojiPickerInitialized);
            
            if (emojiPickerInitialized) {
                console.log('Emoji picker already initialized');
                return;
            }
            
            const button = document.getElementById('emoji-button');
            console.log('Button found:', button);
            if (!button) {
                console.log('Button not found, will retry');
                return;
            }
            
            // Find textarea - try multiple selectors
            let textarea = document.querySelector('[x-ref="textarea"]');
            console.log('Textarea with x-ref:', textarea);
            if (!textarea) {
                textarea = document.querySelector('textarea[wire\\:model="message"]');
                console.log('Textarea with wire:model:', textarea);
            }
            if (!textarea) {
                // Find any textarea in the compose section
                const composeSection = document.querySelector('[x-data]');
                if (composeSection) {
                    textarea = composeSection.querySelector('textarea');
                    console.log('Textarea in x-data section:', textarea);
                }
            }
            if (!textarea) {
                textarea = document.querySelector('textarea');
                console.log('Any textarea:', textarea);
            }
            
            if (!textarea) {
                console.warn('Textarea not found for emoji picker');
                return;
            }
            
            console.log('Textarea found:', textarea);
            
            // Create a simple emoji picker popup
            let pickerOpen = false;
            let pickerDiv = null;
            
            function createEmojiPicker() {
                if (pickerDiv) return pickerDiv;
                
                pickerDiv = document.createElement('div');
                pickerDiv.id = 'emoji-picker-popup';
                pickerDiv.className = 'emoji-picker-popup';
                pickerDiv.style.cssText = 'position: absolute; background: white; border: 1px solid #e5e7eb; border-radius: 1rem; padding: 1rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 9999; width: 280px; max-height: 350px; overflow-y: auto; overflow-x: hidden; display: none;';
                
                // Common emojis
                const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾', '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];
                
                const grid = document.createElement('div');
                grid.style.cssText = 'display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem;';
                
                emojis.forEach(emoji => {
                    const btn = document.createElement('button');
                    btn.textContent = emoji;
                    btn.type = 'button';
                    btn.style.cssText = 'font-size: 1.5rem; background: transparent; border: none; cursor: pointer; padding: 0.25rem; border-radius: 0.5rem; transition: background 0.2s;';
                    btn.onmouseover = () => btn.style.backgroundColor = '#f0fdf4';
                    btn.onmouseout = () => btn.style.backgroundColor = 'transparent';
                    btn.onclick = () => {
                        const start = textarea.selectionStart || 0;
                        const end = textarea.selectionEnd || 0;
                        const val = textarea.value || '';
                        textarea.value = val.slice(0, start) + emoji + val.slice(end);
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.focus();
                        const newPos = start + emoji.length;
                        textarea.setSelectionRange(newPos, newPos);
                        pickerDiv.style.display = 'none';
                        pickerOpen = false;
                    };
                    grid.appendChild(btn);
                });
                
                pickerDiv.appendChild(grid);
                document.body.appendChild(pickerDiv);
                return pickerDiv;
            }
            
            try {
                console.log('Creating simple emoji picker...');
                createEmojiPicker();
                
                button.addEventListener('click', function(e) {
                    console.log('Emoji button clicked!', e);
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!pickerOpen) {
                        const rect = button.getBoundingClientRect();
                        pickerDiv.style.display = 'block';
                        pickerDiv.style.top = (rect.bottom + window.scrollY + 10) + 'px';
                        pickerDiv.style.left = (rect.left + window.scrollX) + 'px';
                        pickerOpen = true;
                        console.log('Picker opened');
                    } else {
                        pickerDiv.style.display = 'none';
                        pickerOpen = false;
                        console.log('Picker closed');
                    }
                });
                
                // Close on outside click
                document.addEventListener('click', function(e) {
                    if (pickerOpen && pickerDiv && !pickerDiv.contains(e.target) && !button.contains(e.target)) {
                        pickerDiv.style.display = 'none';
                        pickerOpen = false;
                    }
                });
                
                console.log('Emoji picker initialized successfully');
                emojiPickerInitialized = true;
            } catch (error) {
                console.error('Error initializing emoji picker:', error);
                console.error('Error stack:', error.stack);
            }
        }
        
        // Try multiple times to ensure everything is loaded
        if (document.readyState === 'loading') {
            console.log('Document still loading, adding DOMContentLoaded listener');
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded fired');
                setTimeout(initEmojiPicker, 100);
                setTimeout(initEmojiPicker, 500);
                setTimeout(initEmojiPicker, 1000);
                setTimeout(initEmojiPicker, 2000);
            });
        } else {
            console.log('Document already loaded');
            setTimeout(initEmojiPicker, 100);
            setTimeout(initEmojiPicker, 500);
            setTimeout(initEmojiPicker, 1000);
            setTimeout(initEmojiPicker, 2000);
        }
        
        // Also listen for Livewire updates
        document.addEventListener('livewire:load', function() {
            console.log('Livewire loaded');
            setTimeout(initEmojiPicker, 100);
        });
        document.addEventListener('livewire:update', function() {
            console.log('Livewire updated');
            if (!emojiPickerInitialized) {
                setTimeout(initEmojiPicker, 100);
            }
        });
    </script>

    <style>
        /* Style Picmo picker to match new UI */
        .picmo-popup {
            border: 1px solid #e5e7eb !important;
            border-radius: 1rem !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .picmo-picker .emojiButton:hover {
            background-color: #f0fdf4 !important;
            border-radius: 0.5rem !important;
        }
        
        .picmo-picker .categoryButton.active {
            background-color: #f0fdf4 !important;
            color: #0b8a3d !important;
        }
        
        .picmo-picker input[type="search"] {
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
        }
        
        .picmo-picker input[type="search"]:focus {
            border-color: #0b8a3d !important;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 138, 61, 0.1) !important;
        }
    </style>
</div>
