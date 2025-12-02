<div class="space-y-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Auto Direct Messages</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Configure an auto DM campaign. This is a first version and uses a background job with safe limits.
                </p>
            </div>
        </div>

        @if (session('message'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-green-200 p-4"
                 x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => { show = false }, 4000)">
                <div class="flex items-center text-green-700 text-sm">
                    <i class="bx bx-check-circle mr-2 text-lg"></i>
                    <span>{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 bg-white rounded-3xl shadow-sm border border-red-200 p-4"
                 x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => { show = false }, 8000)">
                <div class="flex items-start text-sm">
                    <i class="bx bx-error-circle mr-2 text-lg text-red-600"></i>
                    <p class="text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Campaign name</label>
                    <input type="text" wire:model.defer="campaignName"
                        class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Daily DM limit</label>
                    <input type="number" min="1" max="200" wire:model.defer="dailyLimit"
                        class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400">
                    <p class="text-xs text-gray-400 mt-1">This will be used later when we wire real DM campaigns and rate limits.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">DM template</label>
                <textarea rows="5" wire:model.defer="dmTemplate"
                    class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm resize-none placeholder-gray-400"
                    placeholder="Write a short, friendly DM your brand will send automatically..."></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button wire:click="saveSettings"
                wire:loading.attr="disabled"
                wire:target="saveSettings"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="saveSettings">Save settings</span>
                <span wire:loading wire:target="saveSettings" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>

            <button wire:click="triggerTestCampaign"
                wire:loading.attr="disabled"
                wire:target="triggerTestCampaign"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="triggerTestCampaign">Run test (DM yourself)</span>
                <span wire:loading wire:target="triggerTestCampaign" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Queuing...
                </span>
            </button>

            <p class="text-xs text-gray-400 ml-2">
                Test uses your own Twitter account ID as recipient and runs via a queued job.
            </p>
        </div>

        <div class="mt-8 border-t border-dashed border-gray-100 pt-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Search people to DM</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Search tweets by keyword (topic, company, etc.), then select authors you want to DM with this template.
                    </p>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1">
                    <input type="text" wire:model.defer="searchQuery"
                        class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400"
                        placeholder="e.g. laravel, your brand name, product keyword...">
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="searchUsers"
                        wire:loading.attr="disabled"
                        wire:target="searchUsers"
                        @if($searchRateLimited) disabled @endif
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-white border border-gray-200 text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="searchUsers">
                            @if($searchRateLimited)
                                Rate limited, wait...
                            @else
                                Search users
                            @endif
                        </span>
                        <span wire:loading wire:target="searchUsers" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Searching...
                        </span>
                    </button>
                </div>
            </div>

            @if($searchRateLimited && $searchRateLimitMessage)
                <p class="text-xs text-amber-600 mt-1">
                    {{ $searchRateLimitMessage }}
                </p>
            @endif

            @if (!empty($searchResults))
                <div class="mt-4 border border-gray-100 rounded-2xl overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                                    <input type="checkbox"
                                        onclick="const checked = this.checked; @this.set('selectedUserIds', checked ? @js(collect($searchResults)->pluck('id')->map(fn($id) => (string) $id)->all()) : []);">
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                    Last matching tweet
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-50">
                            @foreach ($searchResults as $result)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-2 align-top">
                                        <input type="checkbox" value="{{ $result['id'] }}"
                                            wire:model="selectedUserIds"
                                            class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <a href="https://twitter.com/{{ $result['username'] ?? '' }}" target="_blank" rel="noopener noreferrer"
                                           class="flex items-center gap-3 group">
                                            @if (!empty($result['profile_image_url']))
                                                <img src="{{ $result['profile_image_url'] }}" alt="{{ $result['username'] }}"
                                                    class="w-8 h-8 rounded-full ring-1 ring-transparent group-hover:ring-green-200 transition">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                    <i class="bx bx-user text-gray-400 text-base"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 group-hover:text-green-700">
                                                    {{ $result['name'] ?? 'Unknown' }}
                                                </div>
                                                <div class="text-xs text-gray-500 flex items-center gap-1">
                                                    <span>@{{ $result['username'] ?? '' }}</span>
                                                    <span class="text-[10px] text-gray-400 group-hover:text-green-600 underline-offset-2 group-hover:underline">
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
                    <button wire:click="queueDmsForSelected"
                        wire:loading.attr="disabled"
                        wire:target="queueDmsForSelected"
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="queueDmsForSelected">Queue DMs to selected</span>
                        <span wire:loading wire:target="queueDmsForSelected" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Queuing...
                        </span>
                    </button>
                    <p class="text-xs text-gray-400">
                        Uses the DM template above. Limited to your configured daily limit ({{ $dailyLimit }}).
                    </p>
                </div>
            @endif

            <div class="pt-2 border-t border-dashed border-gray-100 mt-4">
                <p class="text-xs text-gray-400">
                    Note: Twitter/X Direct Messages require special API permissions. Right now this page wires the logic,
                    tracks DM attempts in the <code>auto_dms</code> table, and logs everything – the actual DM endpoint
                    can be plugged into <code>TwitterService::sendDirectMessage()</code> once your Twitter app is allowed
                    to send DMs.
                </p>
            </div>
        </div>
    </div>
</div>


