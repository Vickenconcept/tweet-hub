<!-- User Management Component -->
<div class="p-6 bg-white rounded-lg shadow-md">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">User Management</h2>
        <div class="flex space-x-2">
            <button wire:click="checkApiAccess" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors">
                <i class="bx bx-shield-check mr-2"></i>
                <span wire:loading.remove>Check API</span>
                <span wire:loading>Checking...</span>
            </button>
            <button wire:click="refreshData" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                <i class="bx bx-refresh mr-2"></i>
                <span wire:loading.remove>Refresh Data</span>
                <span wire:loading>Refreshing...</span>
            </button>
        </div>
    </div>

    <!-- Info Box -->
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-start">
            <i class="bx bx-info-circle text-blue-500 mr-2 mt-0.5"></i>
            <div class="text-sm text-blue-700">
                <p class="font-medium mb-1">Your Twitter Account Data</p>
                <p>View your followers, following, blocked, and muted users. Click "Check API" to test access, then "Refresh Data" to load your data.</p>
                <p class="mt-2 text-xs text-blue-600"><strong>Note:</strong> Some features require Elevated Access to Twitter API v2. If data shows as 0, you may need to apply for elevated access in your Twitter Developer Portal.</p>
            </div>
        </div>
    </div>

    <!-- Basic User Info Display -->
    @if($basicUserInfo)
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-start">
                <i class="bx bx-user-check text-green-500 mr-2 mt-0.5"></i>
                <div class="text-sm text-green-700">
                    <p class="font-medium mb-2">Your Twitter Profile</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="font-medium">Name:</span> {{ $basicUserInfo->name }}
                        </div>
                        <div>
                            <span class="font-medium">Username:</span> @{{ $basicUserInfo->username }}
                        </div>
                        @if(isset($basicUserInfo->description))
                            <div class="col-span-2">
                                <span class="font-medium">Bio:</span> {{ $basicUserInfo->description }}
                            </div>
                        @endif
                        @if(isset($basicUserInfo->location))
                            <div>
                                <span class="font-medium">Location:</span> {{ $basicUserInfo->location }}
                            </div>
                        @endif
                        @if(isset($basicUserInfo->created_at))
                            <div>
                                <span class="font-medium">Joined:</span> {{ \Carbon\Carbon::parse($basicUserInfo->created_at)->format('M Y') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Messages -->
    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg w-[40%] overflow-x-auto">
            <div class="flex items-center">
                <i class="bx bx-error-circle text-red-500 mr-2"></i>
                <span class="text-red-700">{{ $errorMessage }}</span>
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
                <span class="text-green-700">{{ $successMessage }}</span>
                <button wire:click="clearMessages" class="ml-auto text-green-400 hover:text-green-600">
                    <i class="bx bx-x"></i>
                </button>
            </div>
        </div>
    @endif

    <!-- Search Bar -->
    <div class="mb-6">
        <div class="relative">
            <i class="bx bx-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input wire:model.live.debounce.300ms="searchQuery" 
                   type="text" 
                   placeholder="Search users by name or username..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button wire:click="switchTab('followers')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'followers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Followers
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($followers) }}
                </span>
            </button>
            <button wire:click="switchTab('following')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'following' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Following
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($following) }}
                </span>
            </button>
            <button wire:click="switchTab('blocked')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'blocked' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Blocked Users
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($blockedUsers) }}
                </span>
            </button>
            <button wire:click="switchTab('muted')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'muted' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Muted Users
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($mutedUsers) }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-12 text-gray-500">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-6"></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Loading User Data...</h3>
                <p class="text-gray-600 mb-4">Fetching your followers, following, blocked, and muted users</p>
                <p class="text-xs text-gray-500">This may take a few moments...</p>
            </div>
        </div>
    @else
        <!-- Content -->
        @if(count($this->getFilteredData()) > 0)
            <div class="space-y-4">
                @foreach($this->getPaginatedData() as $user)
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <!-- User Info -->
                            <div class="flex items-center space-x-3">
                                <img src="{{ $user->profile_image_url ?? 'https://via.placeholder.com/40x40' }}" 
                                     alt="{{ $user->name ?? 'User' }}" 
                                     class="w-12 h-12 rounded-full">
                                <div>
                                    <h3 class="font-medium text-gray-900">{{ $user->name ?? 'Unknown User' }}</h3>
                                    <p class="text-sm text-gray-500">@{{ $user->username ?? 'unknown' }}</p>
                                    @if(isset($user->description))
                                        <p class="text-sm text-gray-600 mt-1">{{ Str::limit($user->description, 120) }}</p>
                                    @endif
                                    @if(isset($user->public_metrics))
                                        <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                            <span><i class="bx bx-user mr-1"></i>{{ $user->public_metrics->followers_count ?? 0 }} followers</span>
                                            <span><i class="bx bx-user-plus mr-1"></i>{{ $user->public_metrics->following_count ?? 0 }} following</span>
                                            <span><i class="bx bx-message mr-1"></i>{{ $user->public_metrics->tweet_count ?? 0 }} tweets</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- View Profile Link -->
                            <div class="flex items-center space-x-2">
                                <a href="https://twitter.com/{{ $user->username ?? 'unknown' }}" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-100 rounded-lg transition-colors flex items-center">
                                    <i class="bx bx-external-link mr-1"></i> View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($this->getTotalPages() > 1)
                <div class="mt-6 flex items-center justify-center">
                    <div class="flex items-center space-x-2">
                        <!-- Previous Page -->
                        <button wire:click="previousPage" 
                                wire:loading.attr="disabled"
                                @if($page <= 1) disabled @endif
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <i class="bx bx-chevron-left mr-1"></i>
                            Previous
                        </button>

                        <!-- Page Numbers -->
                        <div class="flex items-center space-x-1">
                            @for($pageNum = 1; $pageNum <= $this->getTotalPages(); $pageNum++)
                                @if($pageNum == 1 || $pageNum == $this->getTotalPages() || ($pageNum >= $page - 1 && $pageNum <= $page + 1))
                                    <button wire:click="goToPage({{ $pageNum }})" 
                                            @if($pageNum == $page) disabled @endif
                                            class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $pageNum == $page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50' }}">
                                        {{ $pageNum }}
                                    </button>
                                @elseif($pageNum == $page - 2 || $pageNum == $page + 2)
                                    <span class="px-2 py-2 text-gray-400">...</span>
                                @endif
                            @endfor
                        </div>

                        <!-- Next Page -->
                        <button wire:click="nextPage" 
                                wire:loading.attr="disabled"
                                @if($page >= $this->getTotalPages()) disabled @endif
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            Next
                            <i class="bx bx-chevron-right ml-1"></i>
                        </button>
                    </div>

                    <!-- Page Info -->
                    <div class="ml-4 text-sm text-gray-500">
                        Page {{ $page }} of {{ $this->getTotalPages() }}
                        <span class="text-gray-400">•</span>
                        Showing {{ count($this->getPaginatedData()) }} of {{ count($this->getFilteredData()) }} users
                    </div>
                </div>
            @endif

        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                @if(!empty($searchQuery))
                    <i class="bx bx-search text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                    <p class="text-gray-500">No users match your search "{{ $searchQuery }}"</p>
                @else
                    <i class="bx bx-user text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                    <p class="text-gray-500">
                        @if($activeTab === 'followers')
                            You don't have any followers yet.
                        @elseif($activeTab === 'following')
                            You're not following anyone yet.
                        @elseif($activeTab === 'blocked')
                            You haven't blocked any users.
                        @elseif($activeTab === 'muted')
                            You haven't muted any users.
                        @endif
                    </p>
                @endif
            </div>
        @endif
    @endif

</div>
