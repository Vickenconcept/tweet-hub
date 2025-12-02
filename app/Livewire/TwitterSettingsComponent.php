<?php

namespace App\Livewire;

use App\Models\TwitterAccount;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class TwitterSettingsComponent extends Component
{
    public $autoCommentEnabled = false;
    public $dailyCommentLimit = 20;
    public $commentsPostedToday = 0;
    public $lastCommentAt = null;

    public $successMessage = '';
    public $errorMessage = '';
    public $testTweetText = '';
    public $testTweetLoading = false;
    public $testTweetSuccess = false;
    public $testTweetError = '';
    public $connectedUsername = '';

    protected $twitterAccount;

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        $this->twitterAccount = TwitterAccount::where('user_id', $user->id)->first();

        // Check if user has connected Twitter via OAuth
        if ($user->twitter_account_connected && $user->twitter_username) {
            $this->connectedUsername = $user->twitter_username;
        }

        // Load existing auto-comment settings
        if ($this->twitterAccount) {
            $this->autoCommentEnabled = $this->twitterAccount->auto_comment_enabled;
            $this->dailyCommentLimit = $this->twitterAccount->daily_comment_limit;
            $this->commentsPostedToday = $this->twitterAccount->comments_posted_today;
            $this->lastCommentAt = $this->twitterAccount->last_comment_at?->format('Y-m-d H:i:s');
        }
    }

    public function save()
    {
        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in.';
            return;
        }

        $this->resetMessages();

        // Validate that Twitter account is connected if auto-comment is enabled
        if ($this->autoCommentEnabled) {
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            if (!$twitterAccount || !$twitterAccount->isConfigured()) {
                $this->errorMessage = 'Please connect your Twitter account first to enable auto-commenting.';
                return;
            }
        }

        // Validate daily limit
        if ($this->dailyCommentLimit < 1 || $this->dailyCommentLimit > 1000) {
            $this->errorMessage = 'Daily comment limit must be between 1 and 1000.';
            return;
        }

        try {
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            
            if (!$twitterAccount || !$twitterAccount->isConfigured()) {
                $this->errorMessage = 'Please connect your Twitter account first.';
                return;
            }

            // Only update auto-comment settings (credentials are managed via OAuth)
            $twitterAccount->auto_comment_enabled = $this->autoCommentEnabled;
            $twitterAccount->daily_comment_limit = $this->dailyCommentLimit;

            // If auto-comment is disabled, reset the counter
            if (!$this->autoCommentEnabled) {
                $twitterAccount->comments_posted_today = 0;
            }

            $twitterAccount->save();

            // Reload to get updated values
            $this->twitterAccount = $twitterAccount;
            $this->commentsPostedToday = $twitterAccount->comments_posted_today;
            $this->lastCommentAt = $twitterAccount->last_comment_at?->format('Y-m-d H:i:s');

            $this->successMessage = 'Twitter settings saved successfully!';

            Log::info('Twitter settings saved', [
                'user_id' => $user->id,
                'auto_comment_enabled' => $this->autoCommentEnabled,
                'daily_limit' => $this->dailyCommentLimit,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save Twitter settings', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $this->errorMessage = 'Failed to save settings: ' . $e->getMessage();
        }
    }

    public function sendTestTweet()
    {
        $user = Auth::user();
        if (!$user) {
            $this->testTweetError = 'You must be logged in.';
            return;
        }

        $this->testTweetLoading = true;
        $this->testTweetError = '';
        $this->testTweetSuccess = false;

        if (empty(trim($this->testTweetText))) {
            $this->testTweetError = 'Please enter a test tweet text.';
            $this->testTweetLoading = false;
            return;
        }

        $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();

        if (!$twitterAccount || !$twitterAccount->isConfigured()) {
            $this->testTweetError = 'Please configure your Twitter API credentials first.';
            $this->testTweetLoading = false;
            return;
        }

        try {
            $settings = $twitterAccount->getTwitterServiceSettings();
            $twitterService = new TwitterService($settings);

            $response = $twitterService->createTweet($this->testTweetText);

            if ($response && isset($response->data)) {
                $this->testTweetSuccess = true;
                $this->testTweetText = '';
                $this->successMessage = 'Test tweet sent successfully! Tweet ID: ' . ($response->data->id ?? 'unknown');
            } else {
                $this->testTweetError = 'Failed to send test tweet. No response data.';
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : null;
            $body = $response ? $response->getBody()->getContents() : null;

            Log::error('Test tweet failed (ClientException)', [
                'user_id' => $user->id,
                'status_code' => $statusCode,
                'error_body' => $body,
            ]);

            if ($statusCode === 401 || $statusCode === 403) {
                $this->testTweetError = 'Authentication failed. Please check your API credentials.';
            } elseif ($statusCode === 429) {
                $this->testTweetError = 'Rate limit exceeded. Please try again later.';
            } else {
                $this->testTweetError = 'Failed to send test tweet. Status: ' . $statusCode;
            }
        } catch (\Exception $e) {
            Log::error('Test tweet failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $this->testTweetError = 'Failed to send test tweet: ' . $e->getMessage();
        } finally {
            $this->testTweetLoading = false;
        }
    }

    public function updatedAutoCommentEnabled($value)
    {
        // If disabling, clear error messages
        if (!$value) {
            $this->errorMessage = '';
        }
    }

    public function resetMessages()
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        $this->testTweetError = '';
        $this->testTweetSuccess = false;
    }

    public function getIsConfiguredProperty()
    {
        return $this->twitterAccount && $this->twitterAccount->isConfigured();
    }

    public function render()
    {
        return view('livewire.twitter-settings-component');
    }
}

