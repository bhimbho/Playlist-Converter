<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect');

        if (!$this->clientId || !$this->clientSecret) {
            throw new \RuntimeException('YouTube OAuth credentials not configured');
        }
    }

    public function client(): Client
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            YouTube::YOUTUBE,
            YouTube::YOUTUBE_FORCE_SSL
        ]);

        return $client;
    }

    public function getAuthorizationUrl(?string $state = null, ?string $redirectUri = null): string
    {
        $client = $this->client();
        
        if ($redirectUri) {
            $client->setRedirectUri($redirectUri);
        }
        
        if ($state) {
            $client->setState($state);
        }
        
        return $client->createAuthUrl();
    }

    public function exchangeCodeForToken(string $code, ?string $redirectUri = null): array
    {
        $redirectUri = $redirectUri ?? $this->redirectUri;
        
        Log::info('YouTube OAuth token exchange initiated', [
            'code_length' => strlen($code),
            'code_preview' => substr($code, 0, 10) . '...',
            'redirect_uri' => $redirectUri,
            'token_url' => $this->tokenUrl,
        ]);
        
        $response = Http::asForm()->post($this->tokenUrl, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('YouTube OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'code_length' => strlen($code),
                'redirect_uri' => $redirectUri,
            ]);
            throw new \RuntimeException('Failed to exchange authorization code for token: ' . $response->body());
        }

        $tokenData = $response->json();
        
        Log::info('YouTube OAuth token exchange successful', [
            'has_access_token' => isset($tokenData['access_token']),
            'has_refresh_token' => isset($tokenData['refresh_token']),
            'expires_in' => $tokenData['expires_in'] ?? null,
            'token_type' => $tokenData['token_type'] ?? null,
            'scope' => $tokenData['scope'] ?? null,
            'access_token_preview' => isset($tokenData['access_token']) ? substr($tokenData['access_token'], 0, 20) . '...' : null,
        ]);

        return $tokenData;
    }

    public function storeToken(string $identifier, array $tokenData): void
    {
        $expiresAt = $tokenData['expires_at'] ?? (now()->timestamp + ($tokenData['expires_in'] ?? 3600));
        
        Cache::put(
            "youtube_token:{$identifier}",
            $tokenData,
            now()->addSeconds($expiresAt - now()->timestamp)
        );
    }

    public function getValidAccessToken(string $identifier): ?string
    {
        $tokenData = Cache::get("youtube_token:{$identifier}");
        
        if (!$tokenData) {
            return null;
        }

        $expiresAt = $tokenData['expires_at'] ?? null;
        
        // Check if token is expired
        if ($expiresAt && now()->timestamp >= $expiresAt) {
            // Try to refresh if we have a refresh token
            if (isset($tokenData['refresh_token'])) {
                return $this->refreshAccessToken($identifier, $tokenData['refresh_token']);
            }
            
            // Token expired and no refresh token
            Cache::forget("youtube_token:{$identifier}");
            return null;
        }

        return $tokenData['access_token'] ?? null;
    }

    private function refreshAccessToken(string $identifier, string $refreshToken): ?string
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('YouTube OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            Cache::forget("youtube_token:{$identifier}");
            return null;
        }

        $newTokenData = $response->json();
        
        // Merge with existing token data to preserve refresh_token
        $existingTokenData = Cache::get("youtube_token:{$identifier}", []);
        $mergedTokenData = array_merge($existingTokenData, $newTokenData);
        $mergedTokenData['expires_at'] = now()->timestamp + ($newTokenData['expires_in'] ?? 3600);
        
        $this->storeToken($identifier, $mergedTokenData);
        
        return $newTokenData['access_token'] ?? null;
    }

    public function clearToken(string $identifier): void
    {
        Cache::forget("youtube_token:{$identifier}");
    }
}