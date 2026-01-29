<?php

declare(strict_types=1);

namespace App\Domain\Blog\Event;

final class ArticlePublished
{
    public function __construct(
        public readonly int $articleId,
        public readonly string $publishedAt,
    ) {
    }
}
