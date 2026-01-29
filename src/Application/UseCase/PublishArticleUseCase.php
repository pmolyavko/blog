<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\ArticleCachePort;
use App\Domain\Blog\Repository\ArticleRepositoryInterface;
use DateTimeImmutable;

final class PublishArticleUseCase
{
    public function __construct(
        private ArticleRepositoryInterface $articles,
        private ArticleCachePort $cache,
    ) {
    }

    public function execute(int $id): bool
    {
        $article = $this->articles->findById($id);
        if ($article === null) {
            return false;
        }

        $article->publish(new DateTimeImmutable());
        $this->articles->save($article);
        $this->cache->invalidateArticle($article);

        return true;
    }
}
