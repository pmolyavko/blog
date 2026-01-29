<?php

declare(strict_types=1);

namespace App\Application\Dto;

final class ArticleView
{
    /**
     * @param int[] $tagIds
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $content,
        public readonly string $status,
        public readonly ?string $publishedAt,
        public readonly string $updatedAt,
        public readonly int $authorId,
        public readonly string $language,
        public readonly array $tagIds,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'status' => $this->status,
            'publishedAt' => $this->publishedAt,
            'updatedAt' => $this->updatedAt,
            'authorId' => $this->authorId,
            'language' => $this->language,
            'tagIds' => $this->tagIds,
        ];
    }
}
