<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\ArticleView;
use App\Application\Port\ArticleCachePort;

final class ListLatestArticlesUseCase
{
    public function __construct(private ArticleCachePort $cache)
    {
    }

    /**
     * @return ArticleView[]
     */
    public function execute(int $page, int $limit, array $context = []): array
    {
        return $this->cache->getLatestList($page, $limit, $context);
    }
}
