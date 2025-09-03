<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full bg-black shadow-2xl rounded-r-2xl sm:translate-x-0 p-0 border-r border-indigo-200"
    aria-label="Sidebar">
    <div class="h-full flex flex-col rounded-r-2xl overflow-hidden">
        <!-- Branding/Header -->
        <div class="flex items-center justify-between px-5 py-4 bg-black border-b border-black/40">
            <a href="/home" class="flex items-center text-white">
                <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center mr-3">
                    <i class='bx bx-video text-2xl text-white'></i>
                </div>
                <span class="font-bold text-xl tracking-wide">VidEngager</span>
            </a>
            <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar" aria-controls="logo-sidebar"
                type="button"
                class="inline-flex items-center p-2 text-white rounded-lg sm:hidden hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-black/40 transition-colors">
                <span class="sr-only">Close sidebar</span>
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto py-4 px-2 bg-black">
            <ul class="font-medium flex flex-col gap-1">
                <li>
                    <a href="{{ route('home') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('home') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-grid-alt text-xl {{ request()->routeIs('home') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('daily-post-ideas') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('daily-post-ideas') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-lightbulb text-xl {{ request()->routeIs('daily-post-ideas') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>Daily Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('generate-post-ideas') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('generate-post-ideas') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-edit text-xl {{ request()->routeIs('generate-post-ideas') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>Generate Ideas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('queued-posts') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('queued-posts') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-time text-xl {{ request()->routeIs('queued-posts') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>Queued Posts</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('twitter-mentions') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('twitter-mentions') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-at text-xl {{ request()->routeIs('twitter-mentions') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>Mentions</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('user-management') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('user-management') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class="bx bx-user-circle text-xl {{ request()->routeIs('user-management') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}"></i>
                        <span>User Management</span>
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
            
            <!-- User Profile Section -->
           
            
            <div class="my-4 border-t border-black/40"></div>
            <ul class="font-medium flex flex-col gap-1">
                @auth
                @if(auth()->user()->twitter_account_connected && auth()->user()->twitter_username)
                    <div class="my-4 border-t border-black/40"></div>
                    <div class="px-4 py-3">
                        <div class="flex items-center space-x-3 p-3 bg-gray-800 rounded-lg hover:bg-gray-700 transition-colors cursor-pointer"
                             onclick="window.open('https://twitter.com/{{ auth()->user()->twitter_username }}', '_blank')">
                            <div class="flex-shrink-0">
                                @if(auth()->user()->twitter_profile_image_url)
                                    <img src="{{ auth()->user()->twitter_profile_image_url }}" 
                                         alt="{{ auth()->user()->twitter_username }}" 
                                         class="w-10 h-10 rounded-full border-2 border-gray-600">
                                @else
                                    <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                                        <i class='bx bx-user text-white text-lg'></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate">
                                    <span>@</span>{{ auth()->user()->twitter_username }}
                                </p>
                                @if(auth()->user()->twitter_name)
                                    <p class="text-xs text-gray-300 truncate">
                                        {{ auth()->user()->twitter_name }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex-shrink-0">
                                <i class='bx bx-external-link text-gray-400 text-sm'></i>
                            </div>
                        </div>
                    </div>
                @elseif(auth()->user()->twitter_account_connected)
                    <div class="my-4 border-t border-black/40"></div>
                    <div class="px-4 py-3">
                        <div class="flex items-center space-x-3 p-3 bg-gray-800 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                                    <i class='bx bx-user text-white text-lg'></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white">
                                    {{ auth()->user()->name ?? 'User' }}
                                </p>
                                <p class="text-xs text-gray-300">
                                    Twitter connected (profile updating...)
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="my-4 border-t border-black/40"></div>
                    <div class="px-4 py-3">
                        <div class="flex items-center space-x-3 p-3 bg-gray-800 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                                    <i class='bx bx-user text-white text-lg'></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white">
                                    {{ auth()->user()->name ?? 'User' }}
                                </p>
                                <p class="text-xs text-gray-300">
                                    Twitter not connected
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth
                
                <li>
                    <a href="{{ route('auth.logout') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group text-red-200 hover:bg-red-50 hover:text-red-700">
                        <i class='bx bx-log-out text-xl'></i>
                        <span class="text-sm capitalize">Log out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
