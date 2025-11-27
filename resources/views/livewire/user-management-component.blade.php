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
                <p class="font-medium mb-1">Twitter Analytics & User Management</p>
                <p>Analyze your followers, following, and discover mutual connections. Use advanced filters to find specific users and export data.</p>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div><strong>Mutual:</strong> Users who follow you and you follow back</div>
                    <div><strong>Not Following Back:</strong> You follow them, they don't follow you</div>
                    <div><strong>Not Following:</strong> They follow you, you don't follow them</div>
                    <div><strong>Advanced Search:</strong> Filter by bio, followers count, verification, location</div>
                </div>
                <p class="mt-2 text-xs text-blue-600"><strong>Note:</strong> Some features require Elevated Access to Twitter API v2.</p>
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

    <!-- Advanced Search Bar -->
    <div class="mb-6 space-y-4">
        <!-- Main Search -->
        <div class="relative">
            <i class="bx bx-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input wire:model.live.debounce.300ms="searchQuery" 
                   type="text" 
                   placeholder="Search users by name, username, or bio..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <!-- Advanced Filters -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center space-x-2">
                <input type="checkbox" wire:model.live="searchInBio" id="searchInBio" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="searchInBio" class="text-sm text-gray-700">Search in bio</label>
            </div>
            
            <div class="flex items-center space-x-2">
                <input type="checkbox" wire:model.live="verifiedOnly" id="verifiedOnly" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="verifiedOnly" class="text-sm text-gray-700">Verified only</label>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs text-gray-600">Min Followers</label>
                <input wire:model.live.debounce.500ms="minFollowers" 
                       type="number" 
                       placeholder="0"
                       class="w-full px-3 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="space-y-1">
                <label class="text-xs text-gray-600">Max Followers</label>
                <input wire:model.live.debounce.500ms="maxFollowers" 
                       type="number" 
                       placeholder="∞"
                       class="w-full px-3 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="space-y-1">
                <label class="text-xs text-gray-600">Location</label>
                <input wire:model.live.debounce.500ms="locationFilter" 
                       type="text" 
                       placeholder="e.g. New York"
                       class="w-full px-3 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="flex items-end">
                <button wire:click="clearFilters" 
                        class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded transition-colors">
                    <i class="bx bx-x mr-1"></i>Clear Filters
                </button>
            </div>
            
            <div class="flex items-end">
                <button wire:click="exportData" 
                        class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded transition-colors">
                    <i class="bx bx-download mr-1"></i>Export CSV
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex flex-wrap gap-x-8 gap-y-2">
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
            
            <!-- Mutual Analysis Tabs -->
            <button wire:click="switchTab('mutual_analysis')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'mutual_analysis' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="bx bx-group mr-1"></i>Mutual
                <span class="ml-2 bg-green-100 text-green-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($mutualAnalysis['mutual_followers']) }}
                </span>
            </button>
            <button wire:click="switchTab('following_not_followers')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'following_not_followers' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="bx bx-user-minus mr-1"></i>Not Following Back
                <span class="ml-2 bg-orange-100 text-orange-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($mutualAnalysis['following_not_followers']) }}
                </span>
            </button>
            <button wire:click="switchTab('followers_not_following')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'followers_not_following' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="bx bx-user-plus mr-1"></i>Not Following
                <span class="ml-2 bg-purple-100 text-purple-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($mutualAnalysis['followers_not_following']) }}
                </span>
            </button>
            
            <button wire:click="switchTab('blocked')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'blocked' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Blocked
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium">
                    {{ count($blockedUsers) }}
                </span>
            </button>
            <button wire:click="switchTab('muted')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'muted' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Muted
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
                        @elseif($activeTab === 'mutual_analysis')
                            No mutual followers found. These are users who follow you and you follow back.
                        @elseif($activeTab === 'following_not_followers')
                            Great! All the people you follow also follow you back.
                        @elseif($activeTab === 'followers_not_following')
                            You're following all your followers back.
                        @endif
                    </p>
                @endif
            </div>
        @endif
    @endif

</div>
