<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\RenderedArticleView;
use App\Application\Port\ArticleCachePort;

final class GetRenderedArticleUseCase
{
    public function __construct(private ArticleCachePort $cache)
    {
    }

    public function execute(int $id, array $context = []): ?RenderedArticleView
    {
        return $this->cache->getRenderedArticle($id, $context);
    }
}
