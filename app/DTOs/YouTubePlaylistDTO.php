<?php

namespace App\DTOs;

readonly class YouTubePlaylistDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public array $videos,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['snippet']['title'] ?? $data['title'] ?? '',
            description: $data['snippet']['description'] ?? $data['description'] ?? '',
            videos: array_map(
                fn (array $video) => YouTubeVideoDTO::fromArray($video),
                $data['items'] ?? []
            ),
        );
    }
}






