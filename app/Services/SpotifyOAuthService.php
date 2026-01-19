<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpotifyOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUrl = 'https://accounts.spotify.com/authorize';
    private string $tokenUrl = 'https://accounts.spotify.com/api/token';

    public function __construct()
    {
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
        $this->redirectUri = config('services.spotify.redirect_uri') ?: url('/spotify/callback');

        if (!$this->clientId || !$this->clientSecret) {
            throw new \RuntimeException('Spotify OAuth credentials not configured');
        }
    }

    public function getAuthorizationUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => 'playlist-modify-public playlist-modify-private',
            'show_dialog' => 'false',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, ?string $redirectUri = null): array
    {
        $redirectUri = $redirectUri ?? $this->redirectUri;

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Spotify OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $errorBody,
                'redirect_uri' => $redirectUri,
            ]);
            
            if (str_contains($errorBody, 'INVALID_CLIENT') || str_contains($errorBody, 'redirect_uri')) {
                throw new \RuntimeException(
                    'Spotify OAuth error: Invalid redirect URI. ' .
                    'Please ensure the redirect URI "' . $redirectUri . '" is added to your Spotify app settings at https://developer.spotify.com/dashboard. ' .
                    'The URI must match exactly (including protocol, domain, port, and path).'
                );
            }
            
            throw new \RuntimeException('Failed to exchange authorization code for token: ' . $errorBody);
        }

        return $response->json();
    }

    public function storeToken(string $identifier, array $tokenData): void
    {
        $expiresAt = $tokenData['expires_at'] ?? (now()->timestamp + ($tokenData['expires_in'] ?? 3600));

        Cache::put(
            "spotify_token:{$identifier}",
            $tokenData,
            now()->addSeconds($expiresAt - now()->timestamp)
        );
    }

    public function getValidAccessToken(string $identifier): ?string
    {
        $tokenData = Cache::get("spotify_token:{$identifier}");

        if (!$tokenData) {
            return null;
        }

        $expiresAt = $tokenData['expires_at'] ?? null;

        if ($expiresAt && now()->timestamp >= $expiresAt) {
            if (isset($tokenData['refresh_token'])) {
                return $this->refreshAccessToken($identifier, $tokenData['refresh_token']);
            }

            Cache::forget("spotify_token:{$identifier}");
            return null;
        }

        return $tokenData['access_token'] ?? null;
    }

    private function refreshAccessToken(string $identifier, string $refreshToken): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            Log::error('Spotify OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            Cache::forget("spotify_token:{$identifier}");
            return null;
        }

        $newTokenData = $response->json();
        $existingTokenData = Cache::get("spotify_token:{$identifier}", []);
        $mergedTokenData = array_merge($existingTokenData, $newTokenData);
        $mergedTokenData['expires_at'] = now()->timestamp + ($newTokenData['expires_in'] ?? 3600);

        $this->storeToken($identifier, $mergedTokenData);

        return $newTokenData['access_token'] ?? null;
    }

    public function clearToken(string $identifier): void
    {
        Cache::forget("spotify_token:{$identifier}");
    }
}
