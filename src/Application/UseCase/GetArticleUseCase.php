<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\ArticleView;
use App\Application\Port\ArticleCachePort;

final class GetArticleUseCase
{
    public function __construct(private ArticleCachePort $cache)
    {
    }

    public function execute(int $id, array $context = []): ?ArticleView
    {
        return $this->cache->getArticle($id, $context);
    }
}
