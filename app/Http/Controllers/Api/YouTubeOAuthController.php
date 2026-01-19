<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YouTubeOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class YouTubeOAuthController extends Controller
{
    public function __construct(
        private readonly YouTubeOAuthService $oauthService
    ) {
    }

    public function authorize(Request $request): RedirectResponse
    {
        try {
            $state = bin2hex(random_bytes(16));

            session(['youtube_oauth_state' => $state]);
            cache()->put("youtube_oauth_state:{$state}", true, now()->addMinutes(10));

            $authUrl = $this->oauthService->getAuthorizationUrl($state);
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('YouTube OAuth authorization error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')->with('error', 'Failed to generate authorization URL: ' . $e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $state = $request->input('state');

            if (!$request->has('code')) {
                return redirect()->route('login')->with('error', 'Authorization failed: No code received. Please log in first.');
            }

            if ($state && !cache()->has("youtube_oauth_state:{$state}") && session('youtube_oauth_state') !== $state) {
                return redirect()->route('login')->with('error', 'Invalid state parameter. Please log in again.');
            }

            $tokenData = $this->oauthService->exchangeCodeForToken($request->code);
            $tokenData['expires_at'] = now()->timestamp + ($tokenData['expires_in'] ?? 3600);

            $userId = Auth::id();
            $tokenIdentifier = $userId ? "user:{$userId}" : session()->getId();
            $this->oauthService->storeToken($tokenIdentifier, $tokenData);

            if ($state) {
                cache()->forget("youtube_oauth_state:{$state}");
            }
            session()->forget('youtube_oauth_state');
            session()->put('youtube_authenticated', true);

            return redirect()->route('home')->with('success', 'YouTube account connected successfully!');
        } catch (\Exception $e) {
            Log::error('YouTube OAuth callback error', [
                'error' => $e->getMessage(),
                'auth_check' => Auth::check(),
                'auth_id' => Auth::id(),
            ]);

            if (Auth::check()) {
                return redirect()->route('home')->with('error', 'Failed to authenticate with YouTube: ' . $e->getMessage());
            }

            return redirect()->route('login')->with('error', 'Failed to authenticate with YouTube. Please log in and try again.');
        }
    }
}






