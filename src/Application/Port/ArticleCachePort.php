<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ArticleView;
use App\Application\Dto\RenderedArticleView;
use App\Domain\Blog\Article;

interface ArticleCachePort
{
    public function getArticle(int $id, array $context = []): ?ArticleView;

    public function getRenderedArticle(int $id, array $context = []): ?RenderedArticleView;

    /**
     * @return ArticleView[]
     */
    public function getLatestList(int $page, int $limit, array $context = []): array;

    /**
     * @return ArticleView[]
     */
    public function getTagList(int $tagId, int $page, int $limit, array $context = []): array;

    /**
     * @return ArticleView[]
     */
    public function getAuthorList(int $authorId, int $page, int $limit, array $context = []): array;

    public function invalidateArticle(Article $article): void;
}
