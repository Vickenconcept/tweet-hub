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
                    <p class="text-sm text-gray-500 uppercase tracking-[0.2em]">Password Recovery</p>
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

                    <p class="text-sm font-semibold text-[#0b8a3d] uppercase tracking-[0.3em]">Reset Password</p>
                    <h1 class="mt-2 text-3xl sm:text-[2.5rem] font-semibold text-gray-900 leading-snug">
                        Recover your account access.
                    </h1>
                    <p class="mt-3 text-gray-500 text-sm">
                        Enter your email address and we'll send you a link to reset your password and regain access to your xengager dashboard.
                    </p>

                    <x-session-msg class="mt-4" />

                    @if (session('status'))
                        <div class="mt-4 bg-green-50 border border-green-200 rounded-2xl p-4">
                            <div class="flex items-center text-green-700">
                                <i class="bx bx-check-circle mr-2 text-lg"></i>
                                <span class="text-sm">{{ session('status') }}</span>
                            </div>
                        </div>
                    @endif

                    <form class="mt-8 space-y-6" method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div>
                            <label for="email" class="text-sm font-semibold text-gray-800">Email Address</label>
                            <div class="mt-2 relative">
                                <span class="absolute left-4 inset-y-0 flex items-center text-[#0b8a3d]">
                                    <i class='bx bx-envelope text-lg'></i>
                                </span>
                                <input id="email" name="email" type="email" autocomplete="email" required
                                    class="w-full rounded-2xl border border-gray-200 pl-12 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0b8a3d] focus:border-transparent placeholder:text-gray-400 @error('email') border-red-300 focus:ring-red-500 @enderror"
                                    placeholder="you@xengager.com" value="{{ old('email') }}">
                            </div>
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-[#0b8a3d] text-white text-sm font-semibold py-3 rounded-2xl shadow-lg shadow-[#0b8a3d]/30 transition hover:bg-[#0a7c36]">
                            <span id="hiddenText" class="hidden">
                                <i class='bx bx-loader-alt animate-spin text-lg'></i>
                            </span>
                            <span>Send Reset Link</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7-7 7" />
                            </svg>
                        </button>

                        <div class="text-center">
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#0b8a3d] hover:text-[#087630] transition-colors">
                                <i class='bx bx-arrow-back text-lg'></i>
                                <span>Back to login</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right column hero -->
            <div class="flex-1 relative bg-gradient-to-br from-[#0d5f3f] via-[#0da85a] to-[#0dca6c] rounded-[40px] overflow-hidden min-h-[480px]">
                <div class="absolute inset-0 opacity-20 bg-[url('data:image/svg+xml,%3Csvg width=%27120%27 height=%27120%27 viewBox=%270 0 120 120%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Crect width=%27120%27 height=%27120%27 fill=%27none%27 stroke=%27%23000000%27 stroke-opacity=%270.1%27 stroke-width=%270.5%27/%3E%3C/svg%3E')]"></div>

                <div class="absolute -top-10 left-12 bg-white/10 border border-white/30 rounded-2xl px-5 py-3 text-white backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.4em]">SECURITY</p>
                    <p class="text-2xl font-semibold">100%</p>
                    <p class="text-sm opacity-80">Secure password reset</p>
                </div>

                <div class="absolute top-32 right-8 bg-white/10 border border-white/30 rounded-2xl px-5 py-3 text-white backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.4em]">SUPPORT</p>
                    <p class="text-2xl font-semibold">24/7</p>
                    <p class="text-sm opacity-80">Account recovery</p>
                </div>

                <div class="absolute bottom-20 left-16 bg-white/10 border border-white/30 rounded-2xl px-5 py-3 text-white backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.4em]">TRUSTED</p>
                    <p class="text-2xl font-semibold">10k+</p>
                    <p class="text-sm opacity-80">Users recovered</p>
                </div>

                <div class="absolute bottom-12 right-16 text-white">
                    <p class="text-sm opacity-90 mb-2">Quick & secure recovery</p>
                    <p class="text-3xl font-semibold leading-tight">
                        Get back to managing<br>
                        your X presence
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
