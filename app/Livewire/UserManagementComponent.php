<?php

namespace App\Livewire;

use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class UserManagementComponent extends Component
{
    public $activeTab = 'overview';

    public $loading = false;

    public $errorMessage = '';

    public $successMessage = '';

    public $lastRefresh = '';

    public $fromDate = '';

    public $toDate = '';

    public $granularity = 'daily';

    public $profile = null;

    public $accountSummary = null;

    public $statsHistory = [];

    public $dateRange = null;

    public $targetUserId = '';

    public $followActionLoading = false;

    protected $queryString = ['activeTab'];

    public function mount()
    {
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->loadData();
    }

    public function loadData(bool $forceRefresh = false)
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'User not authenticated.';
            $this->loading = false;

            return;
        }

        if (! $user->isTwitterConnected()) {
            $this->errorMessage = 'Please connect your X account first.';
            $this->loading = false;

            return;
        }

        $cacheKey = "zernio_follower_stats_{$user->id}_{$this->fromDate}_{$this->toDate}_{$this->granularity}";

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                $this->hydrateFromCache($cached);
                $this->successMessage = 'Data loaded from cache (updated '.\Carbon\Carbon::parse($this->lastRefresh)->diffForHumans().').';
                $this->loading = false;

                return;
            }
        }

        try {
            $twitterService = new TwitterService($user);

            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->profile = $me->data;
            }

            $stats = $twitterService->getFollowerStats($this->fromDate, $this->toDate, $this->granularity);
            $this->applyFollowerStats($stats);

            $this->lastRefresh = now()->format('M j, Y g:i A');

            Cache::put($cacheKey, [
                'profile' => $this->profile,
                'accountSummary' => $this->accountSummary,
                'statsHistory' => $this->statsHistory,
                'dateRange' => $this->dateRange,
                'timestamp' => $this->lastRefresh,
            ], 14400);

            $this->successMessage = 'Follower data refreshed successfully.';
        } catch (\Exception $e) {
            Log::error('Failed to load follower stats', ['error' => $e->getMessage()]);
            $this->errorMessage = 'Failed to load follower data: '.$e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function refreshData()
    {
        $user = Auth::user();
        if ($user) {
            Cache::forget("zernio_follower_stats_{$user->id}_{$this->fromDate}_{$this->toDate}_{$this->granularity}");
        }

        $this->loadData(true);
    }

    public function applyDateFilter()
    {
        $this->loadData(true);
    }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function followUser()
    {
        $this->performFollowAction('follow');
    }

    public function unfollowUser()
    {
        $this->performFollowAction('unfollow');
    }

    public function clearMessages()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    protected function performFollowAction(string $action)
    {
        $this->followActionLoading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (! $user || ! $user->isTwitterConnected()) {
            $this->errorMessage = 'Connect your X account first.';
            $this->followActionLoading = false;

            return;
        }

        $targetInput = trim($this->targetUserId);
        if ($targetInput === '') {
            $this->errorMessage = 'Enter an @handle or numeric user ID.';
            $this->followActionLoading = false;

            return;
        }

        try {
            $twitterService = new TwitterService($user);
            $targetUserId = $twitterService->resolveTwitterUserId($targetInput);
            $result = $action === 'follow'
                ? $twitterService->followUser($targetUserId)
                : $twitterService->unfollowUser($targetUserId);

            $following = $result->data->following ?? ($action === 'follow');
            $pending = $result->data->pending_follow ?? false;
            $label = str_starts_with($targetInput, '@') || ! preg_match('/^\d+$/', $targetInput)
                ? '@'.ltrim($targetInput, '@')
                : $targetUserId;

            if ($action === 'follow') {
                $this->successMessage = $pending
                    ? "Follow request sent to {$label} (pending approval)."
                    : "You are now following {$label}.";
            } else {
                $this->successMessage = "You unfollowed {$label}.";
            }

            $this->targetUserId = '';
        } catch (\Exception $e) {
            Log::warning("Follow action failed ({$action})", ['error' => $e->getMessage()]);
            $this->errorMessage = ucfirst($action).' failed: '.$e->getMessage();
        } finally {
            $this->followActionLoading = false;
        }
    }

    protected function hydrateFromCache(array $cached): void
    {
        $this->profile = $cached['profile'] ?? null;
        $this->accountSummary = $cached['accountSummary'] ?? null;
        $this->statsHistory = $cached['statsHistory'] ?? [];
        $this->dateRange = $cached['dateRange'] ?? null;
        $this->lastRefresh = $cached['timestamp'] ?? now()->format('M j, Y g:i A');
    }

    protected function applyFollowerStats(array $stats): void
    {
        $accounts = $stats['accounts'] ?? [];
        $this->accountSummary = $accounts[0] ?? null;

        $accountId = $this->accountSummary['_id'] ?? null;
        $this->statsHistory = $accountId ? ($stats['stats'][$accountId] ?? []) : [];
        $this->dateRange = $stats['dateRange'] ?? null;

        if ($this->granularity !== ($stats['granularity'] ?? $this->granularity)) {
            $this->granularity = $stats['granularity'] ?? $this->granularity;
        }
    }

    public function render()
    {
        return view('livewire.user-management-component');
    }
}
