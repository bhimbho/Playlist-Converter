<?php

namespace App\DTOs;

readonly class ConvertPlaylistRequestDTO
{
    public function __construct(
        public string $playlistId,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            playlistId: $data['playlist_id'],
        );
    }
}






