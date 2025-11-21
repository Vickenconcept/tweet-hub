<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full bg-white shadow-lg sm:translate-x-0 p-0 border-r border-gray-100"
    aria-label="Sidebar">
    <div class="h-full flex flex-col overflow-hidden relative bg-[#f5f7fb]">
        <!-- Branding/Header -->
        <div class="relative flex items-center justify-between px-6 py-5 bg-white border-b border-gray-100">
            <a href="/home" class="flex items-center group">
                <div class="w-12 h-12 bg-gradient-to-br from-[#0b8a3d] to-[#0dca6c] rounded-2xl flex items-center justify-center mr-3 shadow-sm group-hover:shadow-md transition-all duration-300">
                    <i class='bx bx-at text-2xl text-white'></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-[0.2em]">Tweet-Hunt</p>
                    <p class="font-semibold text-base text-gray-900">Studio</p>
                </div>
            </a>
            <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar" aria-controls="logo-sidebar"
                type="button"
                class="inline-flex items-center p-2 text-gray-600 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500/50 transition-all duration-300">
                <span class="sr-only">Close sidebar</span>
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <!-- Navigation - Scrollable Area -->
        <div class="flex-1 overflow-y-auto py-4 px-3 min-h-0">
            <ul class="font-medium flex flex-col gap-1.5">
                <li>
                    <a href="javascript:void(0)" id="chat-menu-item" onclick="if(window.toggleChatAndCloseSidebar) window.toggleChatAndCloseSidebar(); return false;"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group bg-green-50 text-green-700 font-semibold shadow-sm">
                        <div id="chat-menu-icon" class="p-1.5 rounded-xl bg-green-100 text-green-600 transition-all duration-200">
                            <i class="bx bx-message-rounded-dots text-lg"></i>
                        </div>
                        <span class="text-sm">Chat</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('home') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('home') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('home') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-grid-alt text-lg"></i>
                        </div>
                        <span class="text-sm">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('daily-post-ideas') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('daily-post-ideas') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('daily-post-ideas') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-calendar-check text-lg"></i>
                        </div>
                        <span class="text-sm">Daily Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('generate-post-ideas') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('generate-post-ideas') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('generate-post-ideas') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-magic-wand text-lg"></i>
                        </div>
                        <span class="text-sm">Generate Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('queued-posts') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('queued-posts') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('queued-posts') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-timer text-lg"></i>
                        </div>
                        <span class="text-sm">Queued Posts</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('twitter-mentions') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('twitter-mentions') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('twitter-mentions') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-at text-lg"></i>
                        </div>
                        <span class="text-sm">Mentions</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('keyword-monitoring') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('keyword-monitoring') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('keyword-monitoring') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-search text-lg"></i>
                        </div>
                        <span class="text-sm">Keyword Monitor</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('tweet-analytics') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('tweet-analytics') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('tweet-analytics') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-bar-chart text-lg"></i>
                        </div>
                        <span class="text-sm">Tweet Analytics</span>
                    </a>
                </li>
                {{-- <li>
                    <a href="{{ route('bookmarks-management') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group {{ request()->routeIs('bookmarks-management') ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 font-semibold' : 'text-gray-300 hover:bg-gray-800/50 hover:text-white hover:shadow-md' }}">
                        <div class="p-2 rounded-lg {{ request()->routeIs('bookmarks-management') ? 'bg-white/20' : 'bg-gray-700/50 group-hover:bg-gray-600/50' }} transition-all duration-300">
                            <i class="bx bx-bookmark text-lg {{ request()->routeIs('bookmarks-management') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">Bookmarks</span>
                    </a>
                </li> --}}
                {{-- <li>
                    <a href="{{ route('user-management') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('user-management') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-user-circle text-xl {{ request()->routeIs('user-management') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>User Management</span>
                    </a>
                </li> --}}
                <li>
                    <a href="{{ route('assets') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group {{ request()->routeIs('assets') ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
                        <div class="p-1.5 rounded-xl {{ request()->routeIs('assets') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200' }} transition-all duration-200">
                            <i class="bx bx-image text-lg"></i>
                        </div>
                        <span class="text-sm">My Assets</span>
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
        <div class="border-t border-gray-200 bg-white">
            <div class="py-4 px-3">
                @auth
                    @if(auth()->user()->twitter_account_connected && auth()->user()->twitter_username)
                        <!-- Twitter Connected User Profile -->
                        <div class="mb-3">
                            <div class="flex items-center space-x-3 p-4 bg-white rounded-2xl hover:bg-gray-50 transition-all duration-200 cursor-pointer shadow-sm border border-gray-100"
                                 onclick="window.open('https://twitter.com/{{ auth()->user()->twitter_username }}', '_blank')">
                                <div class="flex-shrink-0 relative">
                                    @if(auth()->user()->twitter_profile_image_url)
                                        <img src="{{ auth()->user()->twitter_profile_image_url }}" 
                                             alt="{{ auth()->user()->twitter_username }}" 
                                             class="w-12 h-12 rounded-2xl border-2 border-green-200 shadow-sm">
                                        <!-- Twitter Verified Badge -->
                                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center border-2 border-white">
                                            <i class='bx bxl-twitter text-white text-xs'></i>
                                        </div>
                                    @else
                                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-sm">
                                            <i class='bx bx-user text-white text-lg'></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        <span class="text-gray-500">@</span>{{ auth()->user()->twitter_username }}
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                        <p class="text-xs text-green-600 font-medium">Connected</p>
                                    </div>
                                </div>
                                <!-- Disconnect Button -->
                                <button onclick="disconnectTwitter(); event.stopPropagation();" 
                                        class="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors flex-shrink-0" 
                                        title="Disconnect Twitter">
                                    <i class='bx bx-log-out text-lg'></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Disconnect Confirmation Modal -->
                        <div id="disconnectModal" class="fixed inset-0 bg-black/50 z-50 items-center justify-center" style="display: none;">
                            <div class="bg-white rounded-3xl p-6 max-w-md w-full mx-4 border border-gray-200 shadow-xl">
                                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-50 rounded-2xl">
                                    <i class='bx bx-log-out text-red-500 text-2xl'></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900 text-center mb-2">Disconnect Twitter?</h3>
                                <p class="text-gray-500 text-center mb-6 text-sm">
                                    This will remove your Twitter connection and you'll need to reconnect to use Twitter features.
                                </p>
                                <div class="flex gap-3">
                                    <button onclick="closeDisconnectModal()" 
                                            class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl font-medium transition-colors text-sm">
                                        Cancel
                                    </button>
                                    <form action="{{ route('twitter.disconnect') }}" method="POST" class="flex-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="w-full px-4 py-3 bg-red-500 hover:bg-red-600 text-white rounded-2xl font-medium transition-colors text-sm">
                                            Disconnect
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            function disconnectTwitter() {
                                document.getElementById('disconnectModal').style.display = 'flex';
                            }
                            
                            function closeDisconnectModal() {
                                document.getElementById('disconnectModal').style.display = 'none';
                            }
                        </script>
                    @elseif(auth()->user()->twitter_account_connected)
                        <!-- Twitter Connected but Profile Loading -->
                        <div class="mb-3">
                            <div class="flex items-center space-x-3 p-4 bg-amber-50 rounded-2xl border border-amber-200">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center">
                                        <i class='bx bx-loader-alt text-amber-600 text-lg animate-spin'></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ auth()->user()->name ?? 'User' }}
                                    </p>
                                    <p class="text-xs text-amber-600">
                                        Syncing Twitter profile...
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-amber-500 rounded-full mr-2 animate-pulse"></div>
                                        <p class="text-xs text-amber-600 font-medium">Connecting</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Twitter Not Connected -->
                        <div class="mb-3">
                            <div class="flex items-center space-x-3 p-4 bg-white rounded-2xl border border-gray-200">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center">
                                        <i class='bx bx-user text-gray-500 text-lg'></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ auth()->user()->name ?? 'User' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Connect your Twitter account
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                        <p class="text-xs text-gray-500 font-medium">Disconnected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Logout Button -->
                    <div class="mt-2">
                        <a href="{{ route('auth.logout') }}"
                           class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group text-red-600 hover:bg-red-50 border border-red-200 hover:border-red-300">
                            <div class="p-1.5 bg-red-50 rounded-xl group-hover:bg-red-100 transition-colors">
                                <i class='bx bx-log-out text-lg text-red-600'></i>
                            </div>
                            <span class="text-sm font-medium">Sign Out</span>
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</aside>

