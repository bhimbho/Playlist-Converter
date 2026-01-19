<?php

namespace App\Services;

use App\DTOs\SpotifyPlaylistDTO;
use App\DTOs\SpotifyTrackDTO;
use App\Services\Contracts\SpotifyServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpotifyService implements SpotifyServiceInterface
{
    private PendingRequest $client;
    private string $baseUrl = 'https://api.spotify.com/v1';
    private SpotifyOAuthService $oauthService;

    public function __construct(SpotifyOAuthService $oauthService)
    {
        $this->client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
        $this->oauthService = $oauthService;
    }

    public function getPlaylist(string $playlistId): SpotifyPlaylistDTO
    {
        $accessToken = $this->getAccessToken();

        $response = $this->client
            ->withToken($accessToken)
            ->get("/playlists/{$playlistId}", [
                'fields' => 'id,name,description,tracks.items(track(id,name,artists(name),album(name),duration_ms))',
            ]);

        if (! $response->successful()) {
            Log::error('Spotify API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to fetch Spotify playlist: '.$response->body());
        }

        $data = $response->json();
        $tracks = $data['tracks']['items'] ?? [];
        $nextUrl = $data['tracks']['next'] ?? null;

        while ($nextUrl) {
            $nextResponse = $this->client
                ->withToken($accessToken)
                ->get($nextUrl);

            if ($nextResponse->successful()) {
                $nextData = $nextResponse->json();
                $tracks = array_merge($tracks, $nextData['items'] ?? []);
                $nextUrl = $nextData['next'] ?? null;
            } else {
                break;
            }
        }

        $data['tracks']['items'] = $tracks;

        return SpotifyPlaylistDTO::fromArray($data);
    }

    private function getAccessToken(): string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('Spotify credentials not configured');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::error('Spotify token error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to get Spotify access token');
        }

        return $response->json('access_token');
    }

    public function searchTrack(string $query): ?SpotifyTrackDTO
    {
        $accessToken = $this->getAccessToken();

        $response = $this->client
            ->withToken($accessToken)
            ->get('/search', [
                'q' => $query,
                'type' => 'track',
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            Log::error('Spotify search error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);
            return null;
        }

        $data = $response->json();
        $tracks = $data['tracks']['items'] ?? [];

        if (empty($tracks)) {
            return null;
        }

        return SpotifyTrackDTO::fromArray($tracks[0]);
    }

    public function createPlaylist(string $name, string $description, ?string $tokenIdentifier = null): SpotifyPlaylistDTO
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for playlist creation');
        }

        $userProfile = $this->getUserProfile($tokenIdentifier);
        $userId = $userProfile['id'] ?? null;

        if (! $userId) {
            throw new \RuntimeException('Failed to get Spotify user ID');
        }

        $accessToken = $this->getUserAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $response = $this->client
            ->withToken($accessToken)
            ->asJson()
            ->post("/users/{$userId}/playlists", [
                'name' => $name,
                'description' => $description,
                'public' => false,
            ]);

        if (! $response->successful()) {
            Log::error('Spotify playlist creation error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create Spotify playlist: '.$response->body());
        }

        $data = $response->json();

        return new SpotifyPlaylistDTO(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? '',
            tracks: []
        );
    }

    public function addTracksToPlaylist(string $playlistId, array $trackIds, ?string $tokenIdentifier = null): bool
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for adding tracks to playlist');
        }

        $accessToken = $this->getUserAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $chunks = array_chunk($trackIds, 100);

        foreach ($chunks as $chunk) {
            $uris = array_map(fn($id) => "spotify:track:{$id}", $chunk);

            $response = $this->client
                ->withToken($accessToken)
                ->asJson()
                ->post("/playlists/{$playlistId}/tracks", [
                    'uris' => $uris,
                ]);

            if (! $response->successful()) {
                Log::error('Spotify add tracks to playlist error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'playlist_id' => $playlistId,
                    'track_count' => count($chunk),
                ]);
                return false;
            }
        }

        return true;
    }

    public function extractPlaylistIdFromUrl(string $url): ?string
    {
        if (preg_match('/playlist\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/spotify:playlist:([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getUserAccessToken(string $tokenIdentifier): ?string
    {
        return $this->oauthService->getValidAccessToken($tokenIdentifier);
    }

    private function getUserProfile(string $tokenIdentifier): array
    {
        $accessToken = $this->getUserAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $response = $this->client
            ->withToken($accessToken)
            ->get('/me');

        if (! $response->successful()) {
            Log::error('Spotify get user profile error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to get Spotify user profile: '.$response->body());
        }

        return $response->json();
    }
}






