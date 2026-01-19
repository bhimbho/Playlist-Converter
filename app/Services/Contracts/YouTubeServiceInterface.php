<?php

namespace App\Services\Contracts;

use App\DTOs\YouTubePlaylistDTO;
use App\DTOs\YouTubeVideoDTO;

interface YouTubeServiceInterface
{
    public function searchVideo(string $query): ?YouTubeVideoDTO;

    public function createPlaylist(string $title, string $description, ?string $tokenIdentifier = null): YouTubePlaylistDTO;

    public function getPlaylist(string $playlistId, ?string $tokenIdentifier = null): YouTubePlaylistDTO;

    public function getPlaylistVideos(string $playlistId, ?string $tokenIdentifier = null): array;

    public function addVideoToPlaylist(string $playlistId, string $videoId, ?string $tokenIdentifier = null): bool;

    public function extractPlaylistIdFromUrl(string $url): ?string;
}

