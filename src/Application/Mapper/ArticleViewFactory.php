<?php

declare(strict_types=1);

namespace App\Application\Mapper;

use App\Application\Dto\ArticleView;
use App\Domain\Blog\Article;
use DateTimeImmutable;

final class ArticleViewFactory
{
    public function fromDomain(Article $article): ArticleView
    {
        return new ArticleView(
            $article->id() ?? 0,
            $article->title(),
            $article->slug(),
            $article->content(),
            $article->status(),
            $article->publishedAt()?->format(DateTimeImmutable::ATOM),
            $article->updatedAt()->format(DateTimeImmutable::ATOM),
            $article->authorId(),
            $article->language(),
            $article->tagIds(),
        );
    }
}
