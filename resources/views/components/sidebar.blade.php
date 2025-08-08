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
           
                {{-- <li>
                    <a href="{{ route('reseller.index') }}"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors group {{ request()->routeIs('reseller.index') ? 'bg-white text-black shadow font-semibold' : 'text-white hover:bg-gray-500 hover:text-white' }}">
                        <i class='bx bx-store text-xl {{ request()->routeIs('reseller.index') ? 'text-black' : 'text-indigo-200 group-hover:text-white' }}'></i>
                        <span>Reseller</span>
                    </a>
                </li> --}}
            </ul>
            <div class="my-4 border-t border-black/40"></div>
            <ul class="font-medium flex flex-col gap-1">
                
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
