<?php

namespace App\DTOs;

readonly class SpotifyTrackDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public array $artists,
        public ?string $album,
        public ?int $durationMs,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $track = $data['track'] ?? $data;

        return new self(
            id: $track['id'],
            name: $track['name'],
            artists: array_map(
                fn (array $artist) => $artist['name'],
                $track['artists'] ?? []
            ),
            album: $track['album']['name'] ?? null,
            durationMs: $track['duration_ms'] ?? null,
        );
    }

    public function getSearchQuery(): string
    {
        $artists = implode(' ', $this->artists);
        return "{$this->name} {$artists}";
    }
}






