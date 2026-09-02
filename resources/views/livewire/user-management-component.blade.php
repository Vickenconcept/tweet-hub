<div class="p-6 bg-white rounded-lg shadow-md">
    <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-2xl font-bold text-gray-900">Follower Insights</h2>
            <button wire:click="refreshData"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50">
                <i class="bx bx-sync mr-2"></i>
                <span wire:loading.remove wire:target="refreshData">Refresh</span>
                <span wire:loading wire:target="refreshData">Syncing...</span>
            </button>
        </div>
        <p class="text-gray-500 text-sm">
            @if($lastRefresh)
                <i class="bx bx-check-circle mr-1 text-green-600"></i>
                Last updated: {{ $lastRefresh }}
            @else
                <i class="bx bx-info-circle mr-1 text-blue-600"></i>
                Follower growth and follow/unfollow actions
            @endif
        </p>
    </div>

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <i class="bx bx-error-circle text-red-500 mr-2"></i>
                <p class="text-red-700 flex-1">{{ $errorMessage }}</p>
                <button wire:click="clearMessages" class="ml-auto text-red-400 hover:text-red-600">
                    <i class="bx bx-x"></i>
                </button>
            </div>
        </div>
    @endif

    @if($successMessage)
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
                <i class="bx bx-check-circle text-green-500 mr-2"></i>
                <span class="text-green-700 flex-1">{{ $successMessage }}</span>
                <button wire:click="clearMessages" class="ml-auto text-green-400 hover:text-green-600">
                    <i class="bx bx-x"></i>
                </button>
            </div>
        </div>
    @endif

    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-x-8">
            <button wire:click="switchTab('overview')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Overview
            </button>
            <button wire:click="switchTab('follow')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'follow' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Follow / Unfollow
            </button>
        </nav>
    </div>

    @if($loading)
        <div class="text-center py-12 text-gray-500">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mb-4"></div>
            <p>Loading follower data...</p>
        </div>
    @elseif($activeTab === 'overview')
        @if($profile || $accountSummary)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Current followers</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($accountSummary['currentFollowers'] ?? 0) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Growth</p>
                    <p class="text-2xl font-bold {{ ($accountSummary['growth'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                        {{ ($accountSummary['growth'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($accountSummary['growth'] ?? 0) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Growth %</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($accountSummary['growthPercentage'] ?? 0, 2) }}%
                    </p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Data points</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($accountSummary['dataPoints'] ?? count($statsHistory)) }}
                    </p>
                </div>
            </div>

            @if($profile)
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center gap-4">
                        @if($profile->profile_image_url ?? null)
                            <img src="{{ $profile->profile_image_url }}" alt="" class="w-14 h-14 rounded-full">
                        @endif
                        <div>
                            <p class="font-semibold text-green-800">{{ $profile->name ?? 'Unknown' }}</p>
                            <p class="text-sm text-green-700">{{ '@' . ltrim($profile->username ?? ($accountSummary['username'] ?? 'unknown'), '@') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Date range</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="text-xs text-gray-600">From</label>
                        <input type="date" wire:model="fromDate" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">To</label>
                        <input type="date" wire:model="toDate" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Granularity</label>
                        <select wire:model="granularity" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <button wire:click="applyDateFilter" wire:loading.attr="disabled"
                                class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                            Apply
                        </button>
                    </div>
                </div>
            </div>

            @if(count($statsHistory) > 0)
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Followers</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php $prev = null; @endphp
                            @foreach($statsHistory as $point)
                                @php
                                    $followers = $point['followers'] ?? 0;
                                    $change = $prev !== null ? $followers - $prev : null;
                                    $prev = $followers;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-700">{{ $point['date'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900">{{ number_format($followers) }}</td>
                                    <td class="px-4 py-3 text-right {{ $change === null ? 'text-gray-400' : ($change >= 0 ? 'text-green-600' : 'text-red-600') }}">
                                        @if($change === null)
                                            —
                                        @else
                                            {{ $change >= 0 ? '+' : '' }}{{ number_format($change) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-10 text-gray-500">
                    <i class="bx bx-line-chart text-4xl mb-3"></i>
                    <p>No follower history available for this date range yet.</p>
                    <p class="text-xs mt-2">Follower counts are refreshed once per day.</p>
                </div>
            @endif
        @else
            <div class="text-center py-12 text-gray-500">
                <i class="bx bx-user text-4xl mb-4"></i>
                <p>No follower data available. Connect your X account and try refreshing.</p>
            </div>
        @endif
    @elseif($activeTab === 'follow')
        <div class="max-w-xl">
            <p class="text-sm text-gray-600 mb-4">
                Enter an <strong>@handle</strong>, profile URL (<code class="text-xs bg-gray-100 px-1 rounded">x.com/username</code>), or numeric user ID.
            </p>
            <p class="text-sm text-gray-500 mb-4">
                Works even if the account has not posted recently. Example: <code class="text-xs bg-gray-100 px-1 rounded">@tapswapai</code>
            </p>

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">@handle or user ID</label>
                    <input type="text"
                           wire:model="targetUserId"
                           placeholder="e.g. @tapswapai or https://x.com/tapswapai"
                           class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="flex gap-3">
                    <button wire:click="followUser"
                            wire:loading.attr="disabled"
                            wire:target="followUser"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="followUser">Follow</span>
                        <span wire:loading wire:target="followUser">Following...</span>
                    </button>
                    <button wire:click="unfollowUser"
                            wire:loading.attr="disabled"
                            wire:target="unfollowUser"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="unfollowUser">Unfollow</span>
                        <span wire:loading wire:target="unfollowUser">Unfollowing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
