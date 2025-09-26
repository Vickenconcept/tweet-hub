<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full bg-gradient-to-b from-gray-900 via-black to-gray-900 shadow-2xl sm:translate-x-0 p-0 border-r border-gray-700/50 backdrop-blur-sm"
    aria-label="Sidebar">
    <div class="h-full flex flex-col overflow-hidden relative">
        <!-- Background Pattern -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900/10 via-transparent to-purple-900/10 pointer-events-none"></div>
        
        <!-- Branding/Header -->
        <div class="relative flex items-center justify-between px-6 py-5 bg-gradient-to-r from-gray-800/80 to-gray-900/80 border-b border-gray-700/50 backdrop-blur-sm">
            <a href="/home" class="flex items-center text-white group">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mr-3 shadow-lg group-hover:shadow-blue-500/25 transition-all duration-300">
                    <i class='bx bx-video text-2xl text-white'></i>
                </div>
                <span class="font-bold text-xl tracking-wide bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">VidEngager</span>
            </a>
            <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar" aria-controls="logo-sidebar"
                type="button"
                class="inline-flex items-center p-2 text-white rounded-lg sm:hidden hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all duration-300">
                <span class="sr-only">Close sidebar</span>
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <!-- Navigation - Scrollable Area -->
        <div class="flex-1 overflow-y-auto py-4 px-2 min-h-0">
            <ul class="font-medium flex flex-col gap-1">
                <li>
                    <a href="{{ route('home') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('home') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('home') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-grid-alt text-lg {{ request()->routeIs('home') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('daily-post-ideas') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('daily-post-ideas') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('daily-post-ideas') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-calendar-check text-lg {{ request()->routeIs('daily-post-ideas') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Daily Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('generate-post-ideas') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('generate-post-ideas') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('generate-post-ideas') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-edit text-lg {{ request()->routeIs('generate-post-ideas') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Generate Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('queued-posts') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('queued-posts') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('queued-posts') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-time text-lg {{ request()->routeIs('queued-posts') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Queued Posts</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('twitter-mentions') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('twitter-mentions') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('twitter-mentions') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-at text-lg {{ request()->routeIs('twitter-mentions') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Mentions</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('keyword-monitoring') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('keyword-monitoring') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('keyword-monitoring') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-search text-lg {{ request()->routeIs('keyword-monitoring') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Keyword Monitor</span>
                    </a>
                </li>
                {{-- <li>
                    <a href="{{ route('user-management') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('user-management') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-user-circle text-xl {{ request()->routeIs('user-management') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>User Management</span>
                    </a>
                </li> --}}
                <li>
                    <a href="{{ route('assets') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('assets') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('assets') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-image text-lg {{ request()->routeIs('assets') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">My Assets</span>
                    </a>
                </li>
           
                {{-- <li>
                    <a href="{{ route('reseller.index') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('reseller.index') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class='bx bx-store text-xl {{ request()->routeIs('reseller.index') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}'></i>
                        <span>Reseller</span>
                    </a>
                </li> --}}
            </ul>
        </div>
        
        <!-- User Profile Section - Fixed at Bottom -->
        <div class="border-t border-gray-700/50">
            <div class="py-4 px-2">
                @auth
                    @if(auth()->user()->twitter_account_connected && auth()->user()->twitter_username)
                        <!-- Twitter Connected User Profile -->
                        <div class=" py-3">
                            <div class="flex items-center space-x-3 p-4 bg-gradient-to-r from-gray-800 to-gray-700 rounded-xl hover:from-gray-700 hover:to-gray-600 transition-all duration-300 cursor-pointer shadow-lg border border-gray-600/30"
                                 onclick="window.open('https://twitter.com/{{ auth()->user()->twitter_username }}', '_blank')">
                                <div class="flex-shrink-0 relative">
                                    @if(auth()->user()->twitter_profile_image_url)
                                        <img src="{{ auth()->user()->twitter_profile_image_url }}" 
                                             alt="{{ auth()->user()->twitter_username }}" 
                                             class="w-12 h-12 rounded-full border-2 border-blue-400 shadow-lg">
                                        <!-- Twitter Verified Badge -->
                                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center">
                                            <i class='bx bxl-twitter text-white text-xs'></i>
                                        </div>
                                    @else
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                            <i class='bx bx-user text-white text-lg'></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-white truncate">
                                        <span class="">@</span>{{ auth()->user()->twitter_username }}
                                    </p>
                                    {{-- @if(auth()->user()->twitter_name)
                                        <p class="text-xs text-gray-300 truncate">
                                            {{ auth()->user()->twitter_name }}
                                        </p>
                                    @endif --}}
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                        <p class="text-xs text-green-300 font-medium">Connected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif(auth()->user()->twitter_account_connected)
                        <!-- Twitter Connected but Profile Loading -->
                        <div class=" py-3">
                            <div class="flex items-center space-x-3 p-4 bg-gradient-to-r from-yellow-800/50 to-orange-800/50 rounded-xl border border-yellow-600/30">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-full flex items-center justify-center shadow-lg">
                                        <i class='bx bx-loader-alt text-white text-lg animate-spin'></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-white">
                                        {{ auth()->user()->name ?? 'User' }}
                                    </p>
                                    <p class="text-xs text-yellow-300">
                                        Syncing Twitter profile...
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-yellow-400 rounded-full mr-2 animate-pulse"></div>
                                        <p class="text-xs text-yellow-300 font-medium">Connecting</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Twitter Not Connected -->
                        <div class=" py-3">
                            <div class="flex items-center space-x-3 p-4 bg-gradient-to-r from-gray-800 to-gray-700 rounded-xl border border-gray-600/30">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-gray-500 to-gray-600 rounded-full flex items-center justify-center shadow-lg">
                                        <i class='bx bx-user text-white text-lg'></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-white">
                                        {{ auth()->user()->name ?? 'User' }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        Connect your Twitter account
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-gray-500 rounded-full mr-2"></div>
                                        <p class="text-xs text-gray-400 font-medium">Disconnected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Logout Button -->
                    <div class=" py-2">
                        <a href="{{ route('auth.logout') }}"
                           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group text-red-300 hover:bg-red-500/20 hover:text-red-200 border border-red-500/30 hover:border-red-400/50">
                            <div class="p-1 bg-red-500/20 rounded-lg group-hover:bg-red-500/30 transition-colors">
                                <i class='bx bx-log-out text-lg'></i>
                            </div>
                            <span class="text-sm font-medium capitalize">Sign Out</span>
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</aside>
