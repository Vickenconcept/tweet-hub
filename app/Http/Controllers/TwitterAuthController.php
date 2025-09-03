<?php

namespace App\Http\Controllers;

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
        Log::info('Twitter callback route hit');
        $oauthToken = $request->session()->get('oauth_token');
        $oauthTokenSecret = $request->session()->get('oauth_token_secret');
        $oauthVerifier = $request->get('oauth_verifier');

        if (!$oauthToken || !$oauthTokenSecret || !$oauthVerifier) {
            Log::error('Twitter callback missing tokens', compact('oauthToken', 'oauthTokenSecret', 'oauthVerifier'));
            return redirect('/')->with('error', 'Twitter connection failed: missing tokens.');
        }

        $twitter = new TwitterOAuth(
            config('services.twitter.api_key'),
            config('services.twitter.api_key_secret'),
            $oauthToken,
            $oauthTokenSecret
        );
        $accessToken = $twitter->oauth('oauth/access_token', ['oauth_verifier' => $oauthVerifier]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        Log::info('Twitter callback user', ['user' => $user, 'accessToken' => $accessToken]);
        if (!$user) {
            Log::error('No authenticated user during Twitter callback');
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
        $user->twitter_account_connected = true;
        $user->twitter_account_id = $accessToken['user_id'] ?? null;
        $user->twitter_access_token = $accessToken['oauth_token'] ?? null;
        $user->twitter_access_token_secret = $accessToken['oauth_token_secret'] ?? null;
        
        // Now fetch profile information using the same v2 API that the Update Profile button uses
        $this->fetchAndSaveProfileDataV2($user);
        $saved = $user->save();
        Log::info('User after save', ['user' => $user, 'saved' => $saved]);
        if (!$saved) {
            Log::error('Failed to save user after Twitter connect', ['user' => $user]);
            return redirect('/')->with('error', 'Failed to save Twitter connection.');
        }

        // Clean up session
        $request->session()->forget(['oauth_token', 'oauth_token_secret']);

        // If profile data is missing, try to fetch it one more time using v2 API
        if (!$user->twitter_username && $user->twitter_access_token) {
            Log::info('Profile data missing, attempting to fetch after connection using v2 API');
            $this->fetchAndSaveProfileDataV2($user);
        }

        return redirect('/')->with('success', 'Twitter account connected!');
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
}
