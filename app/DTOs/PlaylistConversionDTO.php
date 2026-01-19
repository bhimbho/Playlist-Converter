<?php

namespace App\DTOs;

readonly class PlaylistConversionDTO
{
    public function __construct(
        public SpotifyPlaylistDTO $spotifyPlaylist,
        public YouTubePlaylistDTO $youtubePlaylist,
        public array $conversionResults,
    ) {
    }

    public function toArray(): array
    {
        return [
            'spotify_playlist' => [
                'id' => $this->spotifyPlaylist->id,
                'name' => $this->spotifyPlaylist->name,
                'description' => $this->spotifyPlaylist->description,
                'track_count' => count($this->spotifyPlaylist->tracks),
                'tracks' => array_map(fn($track) => [
                    'id' => $track->id,
                    'name' => $track->name,
                    'artists' => $track->artists,
                ], $this->spotifyPlaylist->tracks),
            ],
            'youtube_playlist' => [
                'id' => $this->youtubePlaylist->id,
                'title' => $this->youtubePlaylist->title,
                'description' => $this->youtubePlaylist->description,
                'video_count' => count($this->youtubePlaylist->videos),
            ],
            'conversion_results' => $this->conversionResults,
        ];
    }
}






