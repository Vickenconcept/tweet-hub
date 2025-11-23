@php

    use App\Models\Post;
    use Illuminate\Support\Facades\Cache;

    $user = auth()->user();

    // Count scheduled tweets from database
    $scheduledTweetsCount = Post::where('user_id', $user->id)->where('status', 'scheduled')->count();

    // Count X mentions from cache (last 24h)
    $mentionsCacheKey = "twitter_mentions_{$user->id}";
    $cachedMentions = Cache::get($mentionsCacheKey);
    $mentionsCount = 0;

    if ($cachedMentions && isset($cachedMentions['data'])) {
        // Filter mentions from last 24 hours
        $last24Hours = now()->subDay();
        $mentionsData = $cachedMentions['data'] ?? [];

        // First, try to count mentions from last 24 hours
        foreach ($mentionsData as $mention) {
            // Handle both object and array formats
            $createdAt = null;
            
            if (is_object($mention)) {
                // Try to access as object property
                $createdAt = $mention->created_at ?? null;
            } elseif (is_array($mention)) {
                // Try to access as array key
                $createdAt = $mention['created_at'] ?? null;
            }
            
            if ($createdAt) {
                try {
                    $mentionDate = \Carbon\Carbon::parse($createdAt);
                    // Check if mention is within last 24 hours
                    if ($mentionDate->isAfter($last24Hours)) {
                        $mentionsCount++;
                    }
                } catch (\Exception $e) {
                    // If date parsing fails, skip this mention for 24h count
                    continue;
                }
            }
        }
        
        // If no mentions found in last 24h but we have cached mentions,
        // show total count of all cached mentions
        if ($mentionsCount === 0 && !empty($mentionsData)) {
            $mentionsCount = count($mentionsData);
        }
    }

    // Count AI tweet ideas from cache (this week)
    // Daily ideas (today)
    $dailyIdeasCacheKey = "daily_ideas_{$user->id}_" . now()->format('Y-m-d');
    $dailyIdeas = Cache::get($dailyIdeasCacheKey, []);
    $dailyIdeasCount = is_array($dailyIdeas) ? count($dailyIdeas) : 0;

    // Generated ideas (cached for 7 days, but we'll count all)
$generatedIdeasCacheKey = "generated_ideas_{$user->id}";
$generatedIdeas = Cache::get($generatedIdeasCacheKey, []);
$generatedIdeasCount = is_array($generatedIdeas) ? count($generatedIdeas) : 0;

// Total AI ideas (this week - we'll count all cached ideas)
    $totalAIIdeasCount = $dailyIdeasCount + $generatedIdeasCount;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#f5f7fb]">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
            <!-- Welcome + actions -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.4em] text-green-500">Dashboard</p>
                        <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                            Welcome <span class="text-green-600">{{ auth()->user()->name }}</span>
                        </h1>
                        <p class="text-gray-500 mt-2 text-sm md:text-base">
                            Unlock live X insights, generate tweets and ideas with ease.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <a href="/daily-post-ideas"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300">
                            <i class='bx bx-bulb text-lg text-yellow-500'></i> Get Tweet Ideas
                        </a>
                        <a href="javascript:void(0)" onclick="toggleChatAndCloseSidebar(); return false;"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-green-600 text-white text-sm font-semibold shadow-lg shadow-green-200 hover:bg-green-500">
                            <i class='bx bx-plus text-lg'></i> Compose Tweet
                        </a>
                    </div>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <div
                    class="bg-white rounded-3xl border border-gray-100 p-6 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm text-gray-500">X account status</p>
                        <p class="text-2xl font-semibold mt-2">
                            @if (auth()->user()->twitter_account_connected)
                                <span class="text-green-600">Connected</span>
                            @else
                                <span class="text-red-500">Not linked</span>
                            @endif
                        </p>
                        <span class="text-xs text-gray-400 mt-1 inline-block">Account sync</span>
                    </div>
                    <span class="h-12 w-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-500">
                        <i class='bx bx-link text-2xl'></i>
                    </span>
                </div>

                <div
                    class="bg-white rounded-3xl border border-gray-100 p-6 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm text-gray-500">Scheduled tweets</p>
                        <p class="text-3xl font-semibold mt-2">{{ $scheduledTweetsCount }}</p>
                        <span class="text-xs text-gray-400 mt-1 inline-block">Ready for X</span>
                    </div>
                    <span class="h-12 w-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-500">
                        <i class='bx bx-calendar-star text-2xl'></i>
                    </span>
                </div>

                <div
                    class="bg-white rounded-3xl border border-gray-100 p-6 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm text-gray-500">X mentions</p>
                        <p class="text-3xl font-semibold mt-2">{{ $mentionsCount }}</p>
                        <span class="text-xs text-gray-400 mt-1 inline-block">Last 24h</span>
                    </div>
                    <span class="h-12 w-12 rounded-2xl bg-purple-50 flex items-center justify-center text-purple-500">
                        <i class='bx bx-at text-2xl'></i>
                    </span>
                </div>

                <div
                    class="bg-white rounded-3xl border border-gray-100 p-6 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm text-gray-500">AI tweet ideas</p>
                        <p class="text-3xl font-semibold mt-2">{{ $totalAIIdeasCount }}</p>
                        <span class="text-xs text-gray-400 mt-1 inline-block">This week</span>
                    </div>
                    <span class="h-12 w-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500">
                        <i class='bx bx-rocket text-2xl'></i>
                    </span>
                </div>
            </div>

            <!-- Insight cards -->
            {{-- <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-white rounded-3xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div>
                            <p class="text-sm uppercase tracking-[0.3em] text-gray-400">Competitor performance on X</p>
                            <h3 class="text-xl font-semibold text-gray-900 mt-2">No statistics yet</h3>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600">30
                                days</button>
                            <button
                                class="px-4 py-2 rounded-full border border-gray-200 bg-gray-900 text-sm font-medium text-white">60
                                days</button>
                            <button
                                class="px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600">12
                                months</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm text-gray-600">
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Tweet impressions</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Followers</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Posts / month</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Avg likes</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Avg replies</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4">
                            <p class="text-xs uppercase text-gray-400">Avg reposts</p>
                            <p class="text-lg font-semibold mt-1">—</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm">
                    <p class="text-sm uppercase tracking-[0.3em] text-gray-400">Top competitors on X</p>
                    <h3 class="text-xl font-semibold text-gray-900 mt-2">No posts yet</h3>
                    <p class="text-sm text-gray-500 mt-4">Connect or select competitors to see their top performing
                        tweets.</p>
                    <div class="mt-6 space-y-3">
                        <div class="rounded-2xl border border-dashed border-gray-200 p-4 flex items-center gap-3">
                            <span
                                class="h-12 w-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-500">
                                <i class='bx bx-play text-xl'></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">No data</p>
                                <p class="text-xs text-gray-400">Awaiting competitor selection</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div> --}}

            <!-- Feature shortcuts -->
            <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.4em] text-gray-400">Workspace</p>
                        <h3 class="text-2xl font-semibold text-gray-900 mt-1">Quick shortcuts</h3>
                    </div>
                    <p class="text-sm text-gray-500 max-w-xl">Jump straight into the X workflows you use daily.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <button onclick="window.location.href='/daily-post-ideas'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center">
                                <i class='bx bx-calendar text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">Daily Post Ideas</h4>
                        <p class="text-sm text-gray-500 mt-2">Browse saved inspiration and plan upcoming threads.</p>
                    </button>

                    <button onclick="window.location.href='/generate-post-ideas'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-purple-50 text-purple-500 flex items-center justify-center">
                                {{-- <i class='bx bx-magic-wand text-2xl'></i> --}}
                                <i class='bx bx-brain text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">AI Post Generator</h4>
                        <p class="text-sm text-gray-500 mt-2">Craft new scripts and threads with your tone presets.</p>
                    </button>

                    <button onclick="window.location.href='/queued-posts'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center">
                                <i class='bx bx-timer text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">Queued Posts</h4>
                        <p class="text-sm text-gray-500 mt-2">Manage approvals, timing, and drafts in one queue.</p>
                    </button>

                    <button onclick="window.location.href='/twitter-mentions'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-green-50 text-green-500 flex items-center justify-center">
                                <i class='bx bx-chat text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">Twitter Mentions</h4>
                        <p class="text-sm text-gray-500 mt-2">Reply faster and never miss a conversation.</p>
                    </button>

                    <button onclick="window.location.href='/user-management'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center">
                                <i class='bx bx-cog text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">Account Settings</h4>
                        <p class="text-sm text-gray-500 mt-2">Manage seats, permissions, and API credentials.</p>
                    </button>

                    <button onclick="window.location.href='/profile'"
                        class="group rounded-2xl border border-gray-200 p-5 text-left hover:border-gray-300 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span
                                class="h-12 w-12 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center">
                                <i class='bx bx-user-circle text-2xl'></i>
                            </span>
                            <i class='bx bx-chevron-right text-2xl text-gray-300 group-hover:text-gray-500'></i>
                        </div>
                        <h4 class="text-lg font-semibold mt-4">Profile</h4>
                        <p class="text-sm text-gray-500 mt-2">Update contact info, password, and preferences.</p>
                    </button>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 py-6">
                Built for creators • Powered by Tweet-Hunt
            </p>
        </div>
    </div>
</x-app-layout>
