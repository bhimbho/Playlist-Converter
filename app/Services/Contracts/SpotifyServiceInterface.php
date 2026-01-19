<?php

namespace App\Services\Contracts;

use App\DTOs\SpotifyPlaylistDTO;
use App\DTOs\SpotifyTrackDTO;

interface SpotifyServiceInterface
{
    public function getPlaylist(string $playlistId): SpotifyPlaylistDTO;

    public function searchTrack(string $query): ?SpotifyTrackDTO;

    public function createPlaylist(string $name, string $description, ?string $tokenIdentifier = null): SpotifyPlaylistDTO;

    public function addTracksToPlaylist(string $playlistId, array $trackIds, ?string $tokenIdentifier = null): bool;

    public function extractPlaylistIdFromUrl(string $url): ?string;
}






