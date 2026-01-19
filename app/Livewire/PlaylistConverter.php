<?php

namespace App\Livewire;

use App\Services\PlaylistConverterService;
use App\Services\SpotifyOAuthService;
use App\Services\YouTubeOAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PlaylistConverter extends Component
{
    public string $sourcePlatform = 'spotify';
    public string $destinationPlatform = 'youtube';
    
    public string $sourcePlaylistUrl = '';
    public ?string $destinationPlaylistUrl = null;
    
    public bool $isYouTubeAuthenticated = false;
    public bool $isSpotifyAuthenticated = false;
    public bool $isConverting = false;
    public ?array $conversionResult = null;
    public ?string $error = null;
    public ?string $success = null;

    protected function rules(): array
    {
        return [
            'sourcePlaylistUrl' => 'required|string',
        ];
    }

    public function mount(YouTubeOAuthService $youtubeOAuth, SpotifyOAuthService $spotifyOAuth)
    {
        $this->checkAuthentications($youtubeOAuth, $spotifyOAuth);
        
        if (session()->has('success')) {
            $this->success = session('success');
            $this->checkAuthentications($youtubeOAuth, $spotifyOAuth);
        }
        
        if (session()->has('error')) {
            $this->error = session('error');
        }
    }

    private function checkAuthentications(YouTubeOAuthService $youtubeOAuth, SpotifyOAuthService $spotifyOAuth): void
    {
        $userId = Auth::id();
        $sessionId = session()->getId();
        $userTokenIdentifier = $userId ? "user:{$userId}" : null;

        $youtubeToken = $userTokenIdentifier 
            ? $youtubeOAuth->getValidAccessToken($userTokenIdentifier) 
            : null;
        
        if (!$youtubeToken) {
            $youtubeToken = $youtubeOAuth->getValidAccessToken($sessionId);
        }
        
        $this->isYouTubeAuthenticated = session('youtube_authenticated', false);
        
        $spotifyToken = $userTokenIdentifier 
            ? $spotifyOAuth->getValidAccessToken($userTokenIdentifier) 
            : null;
        
        if (!$spotifyToken) {
            $spotifyToken = $spotifyOAuth->getValidAccessToken($sessionId);
        }
        
        $this->isSpotifyAuthenticated = $spotifyToken !== null || session('spotify_authenticated', false);
    }

    public function authenticateWithYouTube(YouTubeOAuthService $oauthService)
    {
        $state = bin2hex(random_bytes(16));

        session(['youtube_oauth_state' => $state]);
        cache()->put("youtube_oauth_state:{$state}", true, now()->addMinutes(10));

        $authUrl = $oauthService->getAuthorizationUrl($state);
        return redirect($authUrl);
    }

    public function authenticateWithSpotify(SpotifyOAuthService $oauthService)
    {
        $state = bin2hex(random_bytes(16));

        session(['spotify_oauth_state' => $state]);
        cache()->put("spotify_oauth_state:{$state}", true, now()->addMinutes(10));

        $authUrl = $oauthService->getAuthorizationUrl($state);
        return redirect($authUrl);
    }

    public function convert(
        PlaylistConverterService $converterService,
        YouTubeOAuthService $youtubeOAuth,
        SpotifyOAuthService $spotifyOAuth
    ) {
        $this->validate();
        
        $this->isConverting = true;
        $this->error = null;
        $this->success = null;
        $this->conversionResult = null;

        try {
            $userId = Auth::id();
            $userTokenIdentifier = $userId ? "user:{$userId}" : null;
            $sessionTokenIdentifier = session()->getId();
            $tokenIdentifier = $userTokenIdentifier ?: $sessionTokenIdentifier;

            if ($this->sourcePlatform === 'spotify' && $this->destinationPlatform === 'youtube') {
                $spotifyPlaylistId = $this->extractSpotifyPlaylistId($this->sourcePlaylistUrl);
                
                if (!$spotifyPlaylistId) {
                    throw new \RuntimeException('Invalid Spotify playlist URL');
                }

                $youtubeToken = $userTokenIdentifier 
                    ? $youtubeOAuth->getValidAccessToken($userTokenIdentifier) 
                    : $youtubeOAuth->getValidAccessToken($sessionTokenIdentifier);
                
                if (!$youtubeToken) {
                    $this->error = 'Please authenticate with YouTube first.';
                    $this->isConverting = false;
                    return;
                }

                $result = $converterService->convert(
                    spotifyPlaylistId: $spotifyPlaylistId,
                    youtubeTokenIdentifier: $tokenIdentifier,
                    youtubePlaylistUrl: $this->destinationPlaylistUrl
                );
            } elseif ($this->sourcePlatform === 'youtube' && $this->destinationPlatform === 'spotify') {
                $youtubePlaylistId = $this->extractYouTubePlaylistId($this->sourcePlaylistUrl);
                
                if (!$youtubePlaylistId) {
                    throw new \RuntimeException('Invalid YouTube playlist URL');
                }

                $spotifyToken = $userTokenIdentifier 
                    ? $spotifyOAuth->getValidAccessToken($userTokenIdentifier) 
                    : $spotifyOAuth->getValidAccessToken($sessionTokenIdentifier);
                
                if (!$spotifyToken) {
                    $this->error = 'Please authenticate with Spotify first.';
                    $this->isConverting = false;
                    return;
                }

                $youtubeToken = $userTokenIdentifier 
                    ? $youtubeOAuth->getValidAccessToken($userTokenIdentifier) 
                    : $youtubeOAuth->getValidAccessToken($sessionTokenIdentifier);
                
                if (!$youtubeToken) {
                    $this->error = 'Please authenticate with YouTube first to read the playlist.';
                    $this->isConverting = false;
                    return;
                }

                $result = $converterService->convertFromYouTube(
                    youtubePlaylistId: $youtubePlaylistId,
                    spotifyTokenIdentifier: $tokenIdentifier,
                    spotifyPlaylistUrl: $this->destinationPlaylistUrl,
                    youtubeTokenIdentifier: $tokenIdentifier
                );
            } else {
                throw new \RuntimeException('Unsupported conversion direction');
            }

            $this->conversionResult = $result->toArray();
            $this->success = 'Playlist converted successfully!';
            $this->sourcePlaylistUrl = '';
            $this->destinationPlaylistUrl = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->isConverting = false;
        }
    }

    private function extractSpotifyPlaylistId(string $url): ?string
    {
        if (preg_match('/playlist\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/spotify:playlist:([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    private function extractYouTubePlaylistId(string $url): ?string
    {
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public function render()
    {
        return view('livewire.playlist-converter');
    }
}
