<div class="space-y-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Auto Direct Messages</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Create a DM template, search for users, and send them personalized messages automatically.
                </p>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-green-200 p-4" x-data="{ show: true }"
                x-show="show" x-init="setTimeout(() => { show = false }, 4000)">
                <div class="flex items-center text-green-700 text-sm">
                    <i class="bx bx-check-circle mr-2 text-lg"></i>
                    <span>{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-red-200 p-4" x-data="{ show: true }"
                x-show="show" x-init="setTimeout(() => { show = false }, 8000)">
                <div class="flex items-start text-sm">
                    <i class="bx bx-error-circle mr-2 text-lg text-red-600"></i>
                    <p class="text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Campaign name</label>
                <input type="text" wire:model.defer="campaignName"
                    class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400"
                    placeholder="e.g. Welcome Campaign, Product Launch...">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700">DM Template</label>
                    <button type="button" wire:click="generateDmTemplate" wire:loading.attr="disabled"
                        wire:target="generateDmTemplate"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-300 rounded-xl hover:bg-green-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <div wire:loading.remove wire:target="generateDmTemplate" class="flex">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                            </svg>
                            <span>Generate with AI</span>
                        </div>
                        <div wire:loading wire:target="generateDmTemplate" class="flex items-center gap-1.5">
                            <span>
                                <svg class="animate-spin h-3 w-3 text-green-700" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </span>
                            <span> Generating...</span>
                        </div>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mb-2">This message will be sent to all selected users.</p>

                @if ($generatingTemplate)
                    <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-700">AI is generating your DM template...</p>
                    </div>
                @endif

                <div class="mb-2">
                    <input type="text" wire:model.defer="aiPrompt"
                        class="w-full rounded-xl border-2 border-gray-400 px-3 py-2 bg-white focus:border-green-500 focus:ring-green-500 text-xs placeholder-gray-400"
                        placeholder="Optional: Describe what you want the DM to say (e.g., 'welcome new followers interested in Laravel')">
                </div>

                <textarea rows="5" wire:model.defer="dmTemplate"
                    class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm resize-none placeholder-gray-400"
                    placeholder="Hey there! Thanks for connecting. I'd love to chat about..."></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Daily DM limit</label>
                <input type="number" min="1" max="200" wire:model.defer="dailyLimit"
                    class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400">
                <p class="text-xs text-gray-400 mt-1">Maximum number of DMs to send per day.</p>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button wire:click="triggerTestCampaign" wire:loading.attr="disabled" wire:target="triggerTestCampaign"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="triggerTestCampaign">Send test DM to yourself</span>
                <span wire:loading wire:target="triggerTestCampaign" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Sending...
                </span>
            </button>
            <p class="text-xs text-gray-400">
                Test your DM template by sending it to yourself first.
            </p>
        </div>

        <div class="mt-8 border-t border-dashed border-gray-100 pt-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Find users to message</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Search for users by keyword, then select who you want to send your DM template to.
                    </p>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1">
                    <input type="text" wire:model.defer="searchQuery"
                        class="w-full rounded-2xl border-2 border-gray-400 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400"
                        placeholder="e.g. laravel, your brand name, product keyword...">
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="searchUsers" wire:loading.attr="disabled" wire:target="searchUsers"
                        @if ($searchRateLimited) disabled @endif
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-white border border-gray-200 text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="searchUsers">
                            @if ($searchRateLimited)
                                Rate limited, wait...
                            @else
                                Search users
                            @endif
                        </span>
                        <span wire:loading wire:target="searchUsers" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Searching...
                        </span>
                    </button>
                </div>
            </div>

            @if ($searchRateLimited && $searchRateLimitMessage)
                <p class="text-xs text-amber-600 mt-1">
                    {{ $searchRateLimitMessage }}
                </p>
            @endif

            @if (!empty($searchResults))
                <div class="mt-4 border border-gray-100 rounded-2xl overflow-hidden ">
                    <table class="w-full divide-y divide-gray-100 text-sm ">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                                    <input type="checkbox"
                                        onclick="const checked = this.checked; @this.set('selectedUserIds', checked ? @js(collect($searchResults)->pluck('id')->map(fn($id) => (string) $id)->all()) : []);"
                                        @if (count($selectedUserIds) === count($searchResults) && count($searchResults) > 0) checked @endif wire:ignore>
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                    Last matching tweet
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-50">
                            @foreach ($searchResults as $result)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-2 align-top">
                                        <input type="checkbox" value="{{ $result['id'] }}"
                                            wire:model.live="selectedUserIds"
                                            class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <a href="https://twitter.com/{{ $result['username'] ?? '' }}" target="_blank"
                                            rel="noopener noreferrer" class="flex items-center gap-3 group">
                                            @if (!empty($result['profile_image_url']))
                                                <img src="{{ $result['profile_image_url'] }}"
                                                    alt="{{ $result['username'] }}"
                                                    class="w-8 h-8 rounded-full ring-1 ring-transparent group-hover:ring-green-200 transition">
                                            @else
                                                <div
                                                    class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                    <i class="bx bx-user text-gray-400 text-base"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <div
                                                    class="text-sm font-medium text-gray-900 group-hover:text-green-700">
                                                    {{ $result['name'] ?? 'Unknown' }}
                                                </div>
                                                <div class="text-xs text-gray-500 flex items-center gap-1">
                                                    <span> <span>@</span>{{ $result['username'] ?? '' }}</span>
                                                    <span
                                                        class="text-[10px] text-gray-400 group-hover:text-green-600 underline-offset-2 group-hover:underline">
                                                        View profile
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500 align-top hidden md:table-cell">
                                        {{ \Illuminate\Support\Str::limit($result['last_tweet_text'] ?? '', 120) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center gap-3 mt-3">
                    <button wire:click="queueDmsForSelected" wire:loading.attr="disabled"
                        wire:target="queueDmsForSelected"
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="queueDmsForSelected">Send DM to
                            {{ count($selectedUserIds) }} selected user(s)</span>
                        <span wire:loading wire:target="queueDmsForSelected" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Sending...
                        </span>
                    </button>
                    <p class="text-xs text-gray-400">
                        Your DM template will be sent to all selected users. (Max {{ $dailyLimit }} per day)
                    </p>
                </div>
            @endif

        </div>
    </div>
</div>
