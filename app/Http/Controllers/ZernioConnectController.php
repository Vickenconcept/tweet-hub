<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ZernioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ZernioConnectController extends Controller
{
    public function connectTwitter(Request $request, ZernioService $zernio)
    {
        if (! $zernio->hasApiKey()) {
            return redirect('/home')->with('error', 'X connection is not configured. Please contact support.');
        }

        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }

        try {
            $profileId = $user->ensureZernioProfile($zernio);
            $callbackUrl = route('twitter.callback', [], absolute: true);

            $connect = $zernio->getTwitterConnectUrl($profileId, $callbackUrl);
            $authUrl = $connect['authUrl'] ?? null;

            if (! $authUrl) {
                throw new \RuntimeException('Zernio did not return an authorization URL.');
            }

            $request->session()->put('zernio_connect_profile_id', $profileId);

            Log::info('Zernio Twitter connect redirect', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'callback_url' => $callbackUrl,
            ]);

            return redirect()->away($authUrl);
        } catch (\Throwable $e) {
            Log::error('Zernio Twitter connect failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect('/home')->with('error', 'Could not start X connection. Please try again.');
        }
    }

    public function handleTwitterCallback(Request $request, ZernioService $zernio)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return redirect('/login')->with('error', 'You must be logged in to connect your X account.');
        }

        $profileId = $request->session()->pull('zernio_connect_profile_id')
            ?? $request->query('profileId')
            ?? $user->zernio_profile_id;

        Log::info('Zernio Twitter callback', [
            'user_id' => $user->id,
            'profile_id' => $profileId,
            'query' => $request->query(),
        ]);

        if ($request->query('error')) {
            return redirect('/home')->with('error', 'X connection was cancelled or denied.');
        }

        try {
            if ($profileId && ! $user->zernio_profile_id) {
                $user->update(['zernio_profile_id' => $profileId]);
            }

            $accountId = $request->query('accountId');
            if ($accountId) {
                $user->syncZernioTwitterAccountById($zernio, (string) $accountId, $profileId, [
                    'username' => $request->query('username'),
                ]);
            } else {
                $user->syncZernioTwitterAccount($zernio, $profileId);
            }

            return redirect('/home')->with('success', 'X account connected! You can post, schedule, and manage engagement from XEngager.');
        } catch (\Throwable $e) {
            Log::error('Zernio Twitter callback sync failed', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'error' => $e->getMessage(),
            ]);

            return redirect('/home')->with('error', 'X authorization succeeded but we could not finish linking your account. Please try connecting again.');
        }
    }

    public function disconnectTwitter(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }

        Log::info('Disconnecting Zernio X account', [
            'user_id' => $user->id,
            'username' => $user->twitter_username,
        ]);

        $user->disconnectZernioTwitter();

        return redirect()->back()->with('success', 'X account disconnected. Reconnect anytime from settings.');
    }
}
