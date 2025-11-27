@seo([
    'title' => 'xengager for X',
    'description' => 'xengager for X',
    'image' => asset('images/meta-image.png'),
    'site_name' => config('app.name'),
    'favicon' => asset('favicon.ico'),
])

<x-guest-layout>
    <div class="min-h-screen bg-[#f2fff4] flex flex-col">
        <header class="flex items-center justify-between px-6 sm:px-12 py-6">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-[#0b8a3d] to-[#0d5] flex items-center justify-center text-white text-2xl font-semibold">
                    XE
                </div>
                <div>
                    <p class="text-sm text-gray-500 uppercase tracking-[0.2em]">Welcome Back</p>
                    <p class="text-xl font-semibold text-gray-900">xengager</p>
                </div>
            </div>
        </header>

        <div class="flex-1 flex flex-col lg:flex-row gap-10 px-6 sm:px-12 pb-10">
            <!-- Left column -->
            <div class="w-full lg:w-[480px] flex items-center">
                <div class="w-full bg-white rounded-[32px] shadow-xl shadow-[#0d5]/[0.08] p-8 sm:p-10 relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 h-32 w-32 rounded-full bg-[#ecffe5] blur-[60px]"></div>
                    <div class="absolute -bottom-8 -left-10 h-28 w-28 rounded-full bg-[#d1ffea] blur-[40px]"></div>

                    <p class="text-sm font-semibold text-[#0b8a3d] uppercase tracking-[0.3em]">Login</p>
                    <h1 class="mt-2 text-3xl sm:text-[2.5rem] font-semibold text-gray-900 leading-snug">
                        Rejoin your command center.
                    </h1>
                    {{-- <p class="mt-3 text-gray-500 text-sm">
                        Sign in to sync mentions, generate on-brand tweets, and keep competitor intelligence streaming into xengager.
                    </p> --}}
                    <x-session-msg class="mt-4" />

                    <form class="mt-8 space-y-6" action="{{ route('auth.login') }}" method="POST">
                        @csrf
                        <div>
                            <label for="email" class="text-sm font-semibold text-gray-800">Email</label>
                            <div class="mt-2 relative">
                                <span class="absolute left-4 inset-y-0 flex items-center text-[#0b8a3d]">
                                    <i class='bx bx-envelope text-lg'></i>
                                </span>
                                <input id="email" name="email" type="email" autocomplete="email" required
                                    class="w-full rounded-2xl border border-gray-200 pl-12 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0b8a3d] focus:border-transparent placeholder:text-gray-400"
                                    placeholder="you@xengager.com">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="text-sm font-semibold text-gray-800">Password</label>
                            <div class="mt-2 relative">
                                <span class="absolute left-4 inset-y-0 flex items-center text-[#0b8a3d]">
                                    <i class='bx bx-lock-alt text-lg'></i>
                                </span>
                                <input type="password" name="password" id="password"
                                    class="w-full rounded-2xl border border-gray-200 pl-12 pr-12 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0b8a3d] focus:border-transparent placeholder:text-gray-400"
                                    placeholder="****************">
                                <button type="button" onclick="showPassword()"
                                    class="absolute right-4 inset-y-0 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                                    <i class="bx bx-show-alt text-2xl" id="show-icon" style="display: block;"></i>
                                    <i class="bx bx-hide text-2xl" id="hide-icon" style="display: none;"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <label class="flex items-center gap-2 text-gray-600">
                                <input type="checkbox" name="remember-me" class="rounded border-gray-300 text-[#0b8a3d] focus:ring-[#0b8a3d]">
                                Keep me logged in on this device
                            </label>
                            <a href="{{ route('password.request') }}" class="font-semibold text-[#0b8a3d] hover:text-[#087630]">Forgot password?</a>
                        </div>

                        <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-[#0b8a3d] text-white text-sm font-semibold py-3 rounded-2xl shadow-lg shadow-[#0b8a3d]/30 transition hover:bg-[#0a7c36]">
                            <span id="hiddenText" class="hidden">
                                <i class='bx bx-loader-alt animate-spin text-lg'></i>
                            </span>
                            <span>Continue to dashboard</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7-7 7" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right column hero -->
            <div class="flex-1 relative bg-gradient-to-br from-[#0d5f3f] via-[#0da85a] to-[#0dca6c] rounded-[40px] overflow-hidden min-h-[480px]">
                <div class="absolute inset-0 opacity-20 bg-[url('data:image/svg+xml,%3Csvg width=%27120%27 height=%27120%27 viewBox=%270 0 120 120%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Crect width=%27120%27 height=%27120%27 fill=%27none%27 stroke=%27%23000000%27 stroke-opacity=%270.1%27 stroke-width=%270.5%27/%3E%3C/svg%3E')]"></div>

                <div class="absolute top-10 left-12 bg-white/10 border border-white/30 rounded-2xl px-5 py-3 text-white backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.4em]">MENTIONS</p>
                    <p class="text-2xl font-semibold">36k+</p>
                    <p class="text-sm opacity-80">Notifications on X</p>
                </div>

                <div class="absolute top-10 right-10 bg-white/90 rounded-[24px] p-4 shadow-2xl w-72">
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 rounded-2xl bg-[#ffeeda] flex items-center justify-center">
                            <span class="text-lg font-semibold text-[#ff9f1c]">$</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Trending hashtag</p>
                            <p class="text-xl font-semibold text-gray-900">#LaunchDay</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 uppercase tracking-[0.3em]">Insights</p>
                </div>

                <div class="absolute bottom-10 left-10 bg-white rounded-[28px] shadow-2xl w-80 p-5 flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <img src="https://i.pravatar.cc/80?img=23" alt="avatar" class="h-12 w-12 rounded-2xl object-cover">
                        <div>
                            <p class="text-base font-semibold text-gray-900">Creator Spotlight</p>
                            <p class="text-sm text-gray-500">Top thread this hour</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-600">
                        <span class="px-3 py-1 rounded-full bg-[#f2fff4] text-[#088c41]">mentions</span>
                        <span class="px-3 py-1 rounded-full bg-[#f2f8ff] text-[#1c7ed6]">DMs</span>
                        <span class="px-3 py-1 rounded-full bg-[#fff7e6] text-[#f08c00]">lists</span>
                    </div>
                </div>

                <div class="absolute bottom-6 right-8 bg-black/70 rounded-2xl px-5 py-4 text-white">
                    <p class="text-xs uppercase tracking-[0.4em] text-white/80">Analytics</p>
                    <p class="text-2xl font-semibold">+128%</p>
                    <p class="text-sm text-white/70">Engagement vs last week</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPassword() {
            const passwordField = document.getElementById('password');
            const showIcon = document.getElementById('show-icon');
            const hideIcon = document.getElementById('hide-icon');
            
            if (passwordField && showIcon && hideIcon) {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    showIcon.style.display = 'none';
                    hideIcon.style.display = 'block';
                } else {
                    passwordField.type = 'password';
                    showIcon.style.display = 'block';
                    hideIcon.style.display = 'none';
                }
            }
        }
    </script>
</x-guest-layout>
