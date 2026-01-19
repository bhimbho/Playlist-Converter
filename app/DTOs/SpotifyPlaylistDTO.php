<?php

namespace App\DTOs;

readonly class SpotifyPlaylistDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $tracks,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? '',
            tracks: array_map(
                fn (array $track) => SpotifyTrackDTO::fromArray($track),
                $data['tracks']['items'] ?? []
            ),
        );
    }
}






