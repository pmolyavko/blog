<?php

declare(strict_types=1);

namespace App\Domain\Blog\Repository;

use App\Domain\Blog\Article;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    /**
     * @return Article[]
     */
    public function findLatestPublished(int $limit, int $offset): array;

    public function save(Article $article): void;
}
