<?php

namespace App\DTOs;

readonly class YouTubeVideoDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $channelTitle,
        public ?string $thumbnail,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id']['videoId'] ?? $data['id'],
            title: $data['snippet']['title'] ?? $data['title'] ?? '',
            channelTitle: $data['snippet']['channelTitle'] ?? $data['channelTitle'] ?? '',
            thumbnail: $data['snippet']['thumbnails']['default']['url'] ?? $data['thumbnail'] ?? null,
        );
    }
}






