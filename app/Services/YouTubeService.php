<?php

namespace App\Services;

use App\DTOs\YouTubePlaylistDTO;
use App\DTOs\YouTubeVideoDTO;
use App\Services\Contracts\YouTubeServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService implements YouTubeServiceInterface
{
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';
    private string $apiKey;
    private YouTubeOAuthService $oauthService;

    public function __construct(YouTubeOAuthService $oauthService)
    {
        $this->apiKey = config('services.youtube.api_key');
        $this->oauthService = $oauthService;

        if (! $this->apiKey) {
            throw new \RuntimeException('YouTube API key not configured');
        }
    }

    public function searchVideo(string $query): ?YouTubeVideoDTO
    {
        $response = Http::get("{$this->baseUrl}/search", [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'maxResults' => 1,
            'key' => $this->apiKey,
        ]);

        if (! $response->successful()) {
            Log::error('YouTube API search error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);
            return null;
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return null;
        }

        return YouTubeVideoDTO::fromArray($items[0]);
    }

    public function createPlaylist(string $title, string $description, ?string $tokenIdentifier = null): YouTubePlaylistDTO
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for playlist creation');
        }

        $accessToken = $this->oauthService->getValidAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $response = Http::withToken($accessToken)
            ->asJson()
            ->post("{$this->baseUrl}/playlists?part=snippet,status", [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                ],
                'status' => [
                    'privacyStatus' => 'private',
                ],
            ]);

        if (! $response->successful()) {
            Log::error('YouTube playlist creation error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create YouTube playlist: '.$response->body());
        }

        $data = $response->json();

        return new YouTubePlaylistDTO(
            id: $data['id'],
            title: $data['snippet']['title'],
            description: $data['snippet']['description'] ?? '',
            videos: []
        );
    }

    public function addVideoToPlaylist(string $playlistId, string $videoId, ?string $tokenIdentifier = null): bool
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for adding videos to playlist');
        }

        $accessToken = $this->oauthService->getValidAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $response = Http::withToken($accessToken)
            ->asJson()
            ->post("{$this->baseUrl}/playlistItems?part=snippet", [
                'snippet' => [
                    'playlistId' => $playlistId,
                    'resourceId' => [
                        'kind' => 'youtube#video',
                        'videoId' => $videoId,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('YouTube add video to playlist error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'playlist_id' => $playlistId,
                'video_id' => $videoId,
            ]);

            // Don't throw for duplicate videos, just log
            if ($response->status() === 409) {
                Log::info('Video already exists in playlist', [
                    'playlist_id' => $playlistId,
                    'video_id' => $videoId,
                ]);
                return true; // Consider it successful if already exists
            }

            return false;
        }

        return true;
    }

    public function extractPlaylistIdFromUrl(string $url): ?string
    {
        // Handle various YouTube playlist URL formats
        // https://www.youtube.com/playlist?list=PLxxxxx
        // https://youtube.com/playlist?list=PLxxxxx
        // https://youtu.be/xxxxx?list=PLxxxxx
        // https://www.youtube.com/watch?v=xxxxx&list=PLxxxxx
        
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public function getPlaylist(string $playlistId, ?string $tokenIdentifier = null): YouTubePlaylistDTO
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for getting playlist');
        }

        $accessToken = $this->oauthService->getValidAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/playlists", [
                'part' => 'snippet,contentDetails',
                'id' => $playlistId,
            ]);

        if (! $response->successful()) {
            Log::error('YouTube get playlist error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'playlist_id' => $playlistId,
            ]);
            throw new \RuntimeException('Failed to get YouTube playlist: '.$response->body());
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            throw new \RuntimeException('YouTube playlist not found');
        }

        return YouTubePlaylistDTO::fromArray($items[0]);
    }

    public function getPlaylistVideos(string $playlistId, ?string $tokenIdentifier = null): array
    {
        if (! $tokenIdentifier) {
            throw new \RuntimeException('Token identifier is required for getting playlist videos');
        }

        $accessToken = $this->oauthService->getValidAccessToken($tokenIdentifier);

        if (! $accessToken) {
            throw new \RuntimeException('Valid OAuth token not found. Please re-authenticate.');
        }

        $videos = [];
        $nextPageToken = null;

        do {
            $params = [
                'part' => 'snippet,contentDetails',
                'playlistId' => $playlistId,
                'maxResults' => 50,
            ];

            if ($nextPageToken) {
                $params['pageToken'] = $nextPageToken;
            }

            $response = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/playlistItems", $params);

            if (! $response->successful()) {
                Log::error('YouTube get playlist videos error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'playlist_id' => $playlistId,
                ]);
                throw new \RuntimeException('Failed to get YouTube playlist videos: '.$response->body());
            }

            $data = $response->json();
            $items = $data['items'] ?? [];

            foreach ($items as $item) {
                if (isset($item['snippet']['resourceId']['videoId'])) {
                    $videos[] = YouTubeVideoDTO::fromArray([
                        'id' => ['videoId' => $item['snippet']['resourceId']['videoId']],
                        'snippet' => $item['snippet'],
                    ]);
                }
            }

            $nextPageToken = $data['nextPageToken'] ?? null;
        } while ($nextPageToken);

        return $videos;
    }
}

