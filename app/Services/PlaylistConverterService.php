<?php

namespace App\Services;

use App\DTOs\PlaylistConversionDTO;
use App\DTOs\SpotifyPlaylistDTO;
use App\DTOs\YouTubePlaylistDTO;
use App\DTOs\YouTubeVideoDTO;
use App\Models\Conversion;
use App\Services\Contracts\SpotifyServiceInterface;
use App\Services\Contracts\YouTubeServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlaylistConverterService
{
    public function __construct(
        private SpotifyServiceInterface $spotifyService,
        private YouTubeServiceInterface $youtubeService
    ) {
    }

    public function convert(string $spotifyPlaylistId, ?string $youtubeTokenIdentifier = null, ?string $youtubePlaylistUrl = null): PlaylistConversionDTO
    {
        // Fetch Spotify playlist
        $spotifyPlaylist = $this->spotifyService->getPlaylist($spotifyPlaylistId);

        // Get or create YouTube playlist
        if ($youtubePlaylistUrl) {
            $playlistId = $this->youtubeService->extractPlaylistIdFromUrl($youtubePlaylistUrl);
            if (!$playlistId) {
                throw new \RuntimeException('Invalid YouTube playlist URL');
            }
            $youtubePlaylist = $this->youtubeService->getPlaylist($playlistId, $youtubeTokenIdentifier);
        } else {
            // Create new YouTube playlist
            $youtubePlaylist = $this->youtubeService->createPlaylist(
                title: $spotifyPlaylist->name,
                description: $spotifyPlaylist->description,
                tokenIdentifier: $youtubeTokenIdentifier
            );
        }

        // Convert each track
        $conversionResults = [];
        $youtubeVideos = [];
        $totalTracks = count($spotifyPlaylist->tracks);
        $estimatedQuota = ($totalTracks * 150) + 50; // 150 per track + 50 for playlist creation
        
        Log::info('Starting playlist conversion', [
            'total_tracks' => $totalTracks,
            'estimated_quota_units' => $estimatedQuota,
            'quota_warning' => $estimatedQuota > 8000 ? 'High quota usage - consider smaller playlists' : null,
        ]);

        foreach ($spotifyPlaylist->tracks as $index => $track) {
            $searchQuery = $track->getSearchQuery();
            $youtubeVideo = $this->youtubeService->searchVideo($searchQuery);

            // Add small delay to avoid rate limiting (100ms between requests)
            if ($index > 0) {
                usleep(100000); // 100ms delay
            }

            $result = [
                'spotify_track' => [
                    'id' => $track->id,
                    'name' => $track->name,
                    'artists' => $track->artists,
                ],
                'found' => $youtubeVideo !== null,
            ];

            if ($youtubeVideo) {
                $result['youtube_video'] = [
                    'id' => $youtubeVideo->id,
                    'title' => $youtubeVideo->title,
                    'channel' => $youtubeVideo->channelTitle,
                ];

                $youtubeVideos[] = $youtubeVideo;

                // Add video to playlist
                $this->youtubeService->addVideoToPlaylist(
                    $youtubePlaylist->id,
                    $youtubeVideo->id,
                    tokenIdentifier: $youtubeTokenIdentifier
                );
                
                // Small delay after adding to playlist
                usleep(50000); // 50ms delay
            } else {
                Log::warning('YouTube video not found', [
                    'spotify_track' => $track->name,
                    'search_query' => $searchQuery,
                ]);
            }

            $conversionResults[] = $result;
            
            // Log progress every 10 tracks
            if (($index + 1) % 10 === 0) {
                Log::info('Conversion progress', [
                    'processed' => $index + 1,
                    'total' => $totalTracks,
                    'found' => count($youtubeVideos),
                ]);
            }
        }

        // Create final YouTube playlist DTO with videos
        $finalYoutubePlaylist = new YouTubePlaylistDTO(
            id: $youtubePlaylist->id,
            title: $youtubePlaylist->title,
            description: $youtubePlaylist->description,
            videos: $youtubeVideos
        );

        $conversionDTO = new PlaylistConversionDTO(
            spotifyPlaylist: $spotifyPlaylist,
            youtubePlaylist: $finalYoutubePlaylist,
            conversionResults: $conversionResults
        );

        // Save conversion to database
        if (Auth::check()) {
            $convertedCount = count(array_filter($conversionResults, fn($r) => $r['found']));
            $failedCount = count($conversionResults) - $convertedCount;

            Conversion::create([
                'user_id' => Auth::id(),
                'spotify_playlist_id' => $spotifyPlaylist->id,
                'spotify_playlist_name' => $spotifyPlaylist->name,
                'spotify_playlist_url' => "https://open.spotify.com/playlist/{$spotifyPlaylist->id}",
                'youtube_playlist_id' => $finalYoutubePlaylist->id,
                'youtube_playlist_title' => $finalYoutubePlaylist->title,
                'youtube_playlist_url' => "https://www.youtube.com/playlist?list={$finalYoutubePlaylist->id}",
                'total_tracks' => count($conversionResults),
                'converted_tracks' => $convertedCount,
                'failed_tracks' => $failedCount,
                'conversion_results' => $conversionResults,
            ]);
        }

        return $conversionDTO;
    }

    public function convertFromYouTube(
        string $youtubePlaylistId,
        ?string $spotifyTokenIdentifier = null,
        ?string $spotifyPlaylistUrl = null,
        ?string $youtubeTokenIdentifier = null
    ): PlaylistConversionDTO {
        $youtubeTokenId = $youtubeTokenIdentifier ?? $spotifyTokenIdentifier;
        
        if (!$youtubeTokenId) {
            throw new \RuntimeException('YouTube token identifier is required to read the playlist');
        }
        
        $youtubePlaylist = $this->youtubeService->getPlaylist($youtubePlaylistId, $youtubeTokenId);
        $youtubeVideos = $this->youtubeService->getPlaylistVideos($youtubePlaylistId, $youtubeTokenId);
        if ($spotifyPlaylistUrl) {
            $playlistId = $this->spotifyService->extractPlaylistIdFromUrl($spotifyPlaylistUrl);
            if (!$playlistId) {
                throw new \RuntimeException('Invalid Spotify playlist URL');
            }
            $spotifyPlaylist = $this->spotifyService->getPlaylist($playlistId);
        } else {
            // Create new Spotify playlist
            $spotifyPlaylist = $this->spotifyService->createPlaylist(
                name: $youtubePlaylist->title,
                description: $youtubePlaylist->description,
                tokenIdentifier: $spotifyTokenIdentifier
            );
        }

        $conversionResults = [];
        $spotifyTracks = [];
        $totalVideos = count($youtubeVideos);

        Log::info('Starting YouTube to Spotify conversion', [
            'total_videos' => $totalVideos,
            'youtube_playlist_id' => $youtubePlaylistId,
        ]);

        foreach ($youtubeVideos as $index => $video) {
            $searchQuery = $video->title;
            
            if ($index > 0) {
                usleep(100000);
            }

            $spotifyTrack = $this->spotifyService->searchTrack($searchQuery);

            $result = [
                'youtube_video' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'channel' => $video->channelTitle,
                ],
                'found' => $spotifyTrack !== null,
            ];

            if ($spotifyTrack) {
                $result['spotify_track'] = [
                    'id' => $spotifyTrack->id,
                    'name' => $spotifyTrack->name,
                    'artists' => $spotifyTrack->artists,
                ];

                $spotifyTracks[] = $spotifyTrack;
            } else {
                Log::warning('Spotify track not found', [
                    'youtube_video' => $video->title,
                    'search_query' => $searchQuery,
                ]);
            }

            $conversionResults[] = $result;

            if (($index + 1) % 10 === 0) {
                Log::info('Conversion progress', [
                    'processed' => $index + 1,
                    'total' => $totalVideos,
                    'found' => count($spotifyTracks),
                ]);
            }
        }

        if (!empty($spotifyTracks)) {
            $trackIds = array_map(fn($track) => $track->id, $spotifyTracks);
            $this->spotifyService->addTracksToPlaylist(
                $spotifyPlaylist->id,
                $trackIds,
                tokenIdentifier: $spotifyTokenIdentifier
            );
        }

        $finalSpotifyPlaylist = new SpotifyPlaylistDTO(
            id: $spotifyPlaylist->id,
            name: $spotifyPlaylist->name,
            description: $spotifyPlaylist->description,
            tracks: $spotifyTracks
        );

        $conversionDTO = new PlaylistConversionDTO(
            spotifyPlaylist: $finalSpotifyPlaylist,
            youtubePlaylist: $youtubePlaylist,
            conversionResults: $conversionResults
        );

        // Save conversion to database
        if (Auth::check()) {
            $convertedCount = count(array_filter($conversionResults, fn($r) => $r['found']));
            $failedCount = count($conversionResults) - $convertedCount;

            Conversion::create([
                'user_id' => Auth::id(),
                'spotify_playlist_id' => $finalSpotifyPlaylist->id,
                'spotify_playlist_name' => $finalSpotifyPlaylist->name,
                'spotify_playlist_url' => "https://open.spotify.com/playlist/{$finalSpotifyPlaylist->id}",
                'youtube_playlist_id' => $youtubePlaylist->id,
                'youtube_playlist_title' => $youtubePlaylist->title,
                'youtube_playlist_url' => "https://www.youtube.com/playlist?list={$youtubePlaylist->id}",
                'total_tracks' => count($conversionResults),
                'converted_tracks' => $convertedCount,
                'failed_tracks' => $failedCount,
                'conversion_results' => $conversionResults,
            ]);
        }

        return $conversionDTO;
    }
}

