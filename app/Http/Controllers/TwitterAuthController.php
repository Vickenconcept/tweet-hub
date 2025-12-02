<?php

namespace App\Http\Controllers;

use App\Models\TwitterAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Abraham\TwitterOAuth\TwitterOAuth;

class TwitterAuthController extends Controller
{
    public function redirectToTwitter(Request $request)
    {
        $twitter = new TwitterOAuth(
            config('services.twitter.api_key'),
            config('services.twitter.api_key_secret')
        );
        $callbackUrl = config('services.twitter.callback_url') ?? route('twitter.callback');
        $requestToken = $twitter->oauth('oauth/request_token', ['oauth_callback' => $callbackUrl]);

        $request->session()->put('oauth_token', $requestToken['oauth_token']);
        $request->session()->put('oauth_token_secret', $requestToken['oauth_token_secret']);

        $url = $twitter->url('oauth/authorize', ['oauth_token' => $requestToken['oauth_token']]);
        return redirect($url);
    }

    public function handleTwitterCallback(Request $request)
    {
        Log::info('🔵 Twitter OAuth callback route hit', [
            'timestamp' => now()->toDateTimeString(),
            'has_session_oauth_token' => $request->session()->has('oauth_token'),
            'has_session_oauth_token_secret' => $request->session()->has('oauth_token_secret'),
            'has_oauth_verifier' => $request->has('oauth_verifier'),
        ]);

        $oauthToken = $request->session()->get('oauth_token');
        $oauthTokenSecret = $request->session()->get('oauth_token_secret');
        $oauthVerifier = $request->get('oauth_verifier');

        if (!$oauthToken || !$oauthTokenSecret || !$oauthVerifier) {
            Log::error('❌ Twitter callback missing tokens', [
                'has_oauth_token' => !empty($oauthToken),
                'has_oauth_token_secret' => !empty($oauthTokenSecret),
                'has_oauth_verifier' => !empty($oauthVerifier),
            ]);
            return redirect('/home')->with('error', 'Twitter connection failed: missing tokens.');
        }

        Log::info('✅ Twitter callback - All required tokens present', [
            'oauth_token_length' => strlen($oauthToken ?? ''),
            'oauth_token_secret_length' => strlen($oauthTokenSecret ?? ''),
            'oauth_verifier_length' => strlen($oauthVerifier ?? ''),
        ]);

        $twitter = new TwitterOAuth(
            config('services.twitter.api_key'),
            config('services.twitter.api_key_secret'),
            $oauthToken,
            $oauthTokenSecret
        );
        $accessToken = $twitter->oauth('oauth/access_token', ['oauth_verifier' => $oauthVerifier]);

        Log::info('🔐 Twitter OAuth callback - Access token received', [
            'has_access_token' => !empty($accessToken['oauth_token']),
            'has_access_token_secret' => !empty($accessToken['oauth_token_secret']),
            'user_id_from_oauth' => $accessToken['user_id'] ?? null,
            'screen_name' => $accessToken['screen_name'] ?? null,
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        Log::info('👤 Twitter callback - Authenticated user', [
            'user_id' => $user->id ?? null,
            'user_email' => $user->email ?? null,
        ]);
        
        if (!$user) {
            Log::error('❌ Twitter callback - No authenticated user during Twitter callback');
            return redirect('/login')->with('error', 'You must be logged in to connect your Twitter account.');
        }

        // Create a new TwitterOAuth instance with the access tokens to fetch user info
        $twitterWithTokens = new TwitterOAuth(
            config('services.twitter.api_key'),
            config('services.twitter.api_key_secret'),
            $accessToken['oauth_token'],
            $accessToken['oauth_token_secret']
        );

        // Always save the connection and tokens first
        Log::info('💾 Saving Twitter connection to user model', [
            'user_id' => $user->id,
            'twitter_account_id' => $accessToken['user_id'] ?? null,
            'has_access_token' => !empty($accessToken['oauth_token']),
            'has_access_token_secret' => !empty($accessToken['oauth_token_secret']),
        ]);

        $user->twitter_account_connected = true;
        $user->twitter_account_id = $accessToken['user_id'] ?? null;
        $user->twitter_access_token = $accessToken['oauth_token'] ?? null;
        $user->twitter_access_token_secret = $accessToken['oauth_token_secret'] ?? null;
        
        // Now fetch profile information using the same v2 API that the Update Profile button uses
        $this->fetchAndSaveProfileDataV2($user);
        $saved = $user->save();
        
        Log::info('✅ User model saved after Twitter connect', [
            'user_id' => $user->id,
            'saved' => $saved,
            'twitter_account_connected' => $user->twitter_account_connected,
            'twitter_account_id' => $user->twitter_account_id,
            'twitter_username' => $user->twitter_username,
            'has_access_token' => !empty($user->twitter_access_token),
            'has_access_token_secret' => !empty($user->twitter_access_token_secret),
        ]);
        
        if (!$saved) {
            Log::error('❌ Failed to save user after Twitter connect', [
                'user_id' => $user->id,
                'user' => $user->toArray(),
            ]);
            return redirect('/home')->with('error', 'Failed to save Twitter connection.');
        }

        // Automatically create/update TwitterAccount with OAuth tokens
        // This allows auto-comment to work immediately without manual API key entry
        Log::info('🔑 Starting TwitterAccount auto-configuration from OAuth', [
            'user_id' => $user->id,
        ]);

        try {
            $twitterAccount = TwitterAccount::firstOrNew(['user_id' => $user->id]);
            $isNew = !$twitterAccount->exists;
            
            Log::info('📝 TwitterAccount record status', [
                'user_id' => $user->id,
                'is_new' => $isNew,
                'existing_account_id' => $twitterAccount->id ?? null,
            ]);

            // Set API credentials from app config (consumer key/secret are the same for all users)
            $apiKey = config('services.twitter.api_key');
            $apiSecret = config('services.twitter.api_key_secret');
            
            Log::info('🔐 Setting API credentials from config', [
                'user_id' => $user->id,
                'has_api_key' => !empty($apiKey),
                'has_api_secret' => !empty($apiSecret),
                'api_key_length' => strlen($apiKey ?? ''),
                'api_secret_length' => strlen($apiSecret ?? ''),
            ]);

            $twitterAccount->api_key = $apiKey;
            $twitterAccount->api_secret = $apiSecret;
            
            // Set user tokens from OAuth
            $oauthToken = $accessToken['oauth_token'] ?? null;
            $oauthTokenSecret = $accessToken['oauth_token_secret'] ?? null;
            
            Log::info('🎫 Setting OAuth tokens from callback', [
                'user_id' => $user->id,
                'has_oauth_token' => !empty($oauthToken),
                'has_oauth_token_secret' => !empty($oauthTokenSecret),
                'oauth_token_length' => strlen($oauthToken ?? ''),
                'oauth_token_secret_length' => strlen($oauthTokenSecret ?? ''),
            ]);

            $twitterAccount->access_token = $oauthToken;
            $twitterAccount->access_token_secret = $oauthTokenSecret;
            
            // Keep existing auto-comment settings if they exist, otherwise use defaults
            if (!$twitterAccount->exists) {
                $twitterAccount->auto_comment_enabled = false; // Default to off, user can enable
                $twitterAccount->daily_comment_limit = 20; // Default limit
                Log::info('🆕 New TwitterAccount - setting defaults', [
                    'user_id' => $user->id,
                    'auto_comment_enabled' => false,
                    'daily_comment_limit' => 20,
                ]);
            } else {
                Log::info('♻️ Existing TwitterAccount - preserving settings', [
                    'user_id' => $user->id,
                    'auto_comment_enabled' => $twitterAccount->auto_comment_enabled,
                    'daily_comment_limit' => $twitterAccount->daily_comment_limit,
                ]);
            }
            
            $saved = $twitterAccount->save();
            
            Log::info('✅ TwitterAccount saved successfully', [
                'user_id' => $user->id,
                'twitter_account_id' => $twitterAccount->id,
                'saved' => $saved,
                'is_configured' => $twitterAccount->isConfigured(),
                'is_auto_enabled' => $twitterAccount->isAutoEnabled(),
                'has_api_key' => !empty($twitterAccount->api_key),
                'has_api_secret' => !empty($twitterAccount->api_secret),
                'has_access_token' => !empty($twitterAccount->access_token),
                'has_access_token_secret' => !empty($twitterAccount->access_token_secret),
            ]);

            // Verify the credentials can be decrypted (test)
            try {
                $decryptedApiKey = $twitterAccount->getDecryptedApiKey();
                $decryptedApiSecret = $twitterAccount->getDecryptedApiSecret();
                $decryptedAccessToken = $twitterAccount->getDecryptedAccessToken();
                $decryptedAccessTokenSecret = $twitterAccount->getDecryptedAccessTokenSecret();
                
                Log::info('🔓 TwitterAccount credentials decryption test', [
                    'user_id' => $user->id,
                    'can_decrypt_api_key' => !empty($decryptedApiKey),
                    'can_decrypt_api_secret' => !empty($decryptedApiSecret),
                    'can_decrypt_access_token' => !empty($decryptedAccessToken),
                    'can_decrypt_access_token_secret' => !empty($decryptedAccessTokenSecret),
                    'decrypted_api_key_length' => strlen($decryptedApiKey ?? ''),
                    'decrypted_api_secret_length' => strlen($decryptedApiSecret ?? ''),
                    'decrypted_access_token_length' => strlen($decryptedAccessToken ?? ''),
                    'decrypted_access_token_secret_length' => strlen($decryptedAccessTokenSecret ?? ''),
                ]);
            } catch (\Exception $decryptError) {
                Log::error('❌ Failed to decrypt TwitterAccount credentials', [
                    'user_id' => $user->id,
                    'error' => $decryptError->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Failed to create/update TwitterAccount from OAuth', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't fail the OAuth connection if TwitterAccount creation fails
            // User can still manually configure it later
        }

        // Clean up session
        $request->session()->forget(['oauth_token', 'oauth_token_secret']);

        // If profile data is missing, try to fetch it one more time using v2 API
        if (!$user->twitter_username && $user->twitter_access_token) {
            Log::info('🔄 Profile data missing, attempting to fetch after connection using v2 API', [
                'user_id' => $user->id,
            ]);
            $this->fetchAndSaveProfileDataV2($user);
        }

        Log::info('🎉 Twitter OAuth connection completed successfully', [
            'user_id' => $user->id,
            'twitter_username' => $user->twitter_username,
            'twitter_account_id' => $user->twitter_account_id,
            'twitter_account_connected' => $user->twitter_account_connected,
        ]);

        return redirect('/home')->with('success', 'Twitter account connected! Your API credentials have been automatically configured. You can now enable auto-commenting in Twitter Settings.');
    }

    /**
     * Fetch and save Twitter profile data using v2 API (same as Update Profile button)
     */
    private function fetchAndSaveProfileDataV2(\App\Models\User $user)
    {
        try {
            // Use the same TwitterService that the Update Profile button uses
            $settings = [
                'account_id' => $user->twitter_account_id,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new \App\Services\TwitterService($settings);
            
            // Use the same findMe() method that the Update Profile button uses
            $me = $twitterService->findMe();
            
            if ($me && isset($me->data)) {
                $user->twitter_username = $me->data->username ?? $user->twitter_username;
                $user->twitter_name = $me->data->name ?? $user->twitter_name;
                $user->twitter_profile_image_url = $me->data->profile_image_url ?? $user->twitter_profile_image_url;
                $user->save();
                
                Log::info('Twitter profile data saved successfully using v2 API', [
                    'username' => $user->twitter_username,
                    'name' => $user->twitter_name,
                    'profile_image_url' => $user->twitter_profile_image_url
                ]);
            } else {
                Log::warning('Twitter v2 API returned no profile data');
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch profile data using v2 API', ['error' => $e->getMessage()]);
        }
    }

    public function disconnectTwitter(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (!$user) {
            return redirect('/login')->with('error', 'You must be logged in to disconnect your Twitter account.');
        }

        Log::info('Disconnecting Twitter account', ['user_id' => $user->id, 'username' => $user->twitter_username]);
        
        // Clear all Twitter-related fields
        $user->twitter_account_connected = false;
        $user->twitter_account_id = null;
        $user->twitter_access_token = null;
        $user->twitter_access_token_secret = null;
        $user->twitter_refresh_token = null;
        $user->twitter_username = null;
        $user->twitter_name = null;
        $user->twitter_profile_image_url = null;
        $user->save();

        // Also clear TwitterAccount credentials (but keep the record for settings)
        try {
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            if ($twitterAccount) {
                $twitterAccount->api_key = null;
                $twitterAccount->api_secret = null;
                $twitterAccount->access_token = null;
                $twitterAccount->access_token_secret = null;
                $twitterAccount->auto_comment_enabled = false; // Disable auto-comment
                $twitterAccount->save();
                
                Log::info('TwitterAccount credentials cleared on disconnect', [
                    'user_id' => $user->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear TwitterAccount on disconnect', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Twitter account disconnected successfully', ['user_id' => $user->id]);
        
        return redirect()->back()->with('success', 'Twitter account disconnected successfully. You can reconnect anytime.');
    }
}
