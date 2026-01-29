<?php

declare(strict_types=1);

namespace App\Domain\Blog;

use App\Domain\Blog\Event\ArticlePublished;
use App\Shared\Domain\Aggregate\Aggregate;
use DateTimeImmutable;

final class Article extends Aggregate
{
    private ?int $id;
    private string $title;
    private string $slug;
    private string $content;
    private string $status;
    private ?DateTimeImmutable $publishedAt;
    private DateTimeImmutable $updatedAt;
    private int $authorId;
    private string $language;
    /**
     * @var int[]
     */
    private array $tagIds;

    /**
     * @param int[] $tagIds
     */
    public function __construct(
        ?int $id,
        string $title,
        string $slug,
        string $content,
        int $authorId,
        string $language = 'ru',
        array $tagIds = [],
        string $status = 'draft',
        ?DateTimeImmutable $publishedAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->slug = $slug;
        $this->content = $content;
        $this->authorId = $authorId;
        $this->language = $language;
        $this->tagIds = $tagIds;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function language(): string
    {
        return $this->language;
    }

    /**
     * @return int[]
     */
    public function tagIds(): array
    {
        return $this->tagIds;
    }

    public function publish(DateTimeImmutable $publishedAt): void
    {
        $this->status = 'published';
        $this->publishedAt = $publishedAt;
        $this->updatedAt = new DateTimeImmutable();

        if ($this->id !== null) {
            $this->raise(new ArticlePublished($this->id, $publishedAt->format(DateTimeImmutable::ATOM)));
        }
    }

    public function updateContent(string $title, string $slug, string $content): void
    {
        $this->title = $title;
        $this->slug = $slug;
        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param int[] $tagIds
     */
    public function updateTags(array $tagIds): void
    {
        $this->tagIds = $tagIds;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateLanguage(string $language): void
    {
        $this->language = $language;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateAuthor(int $authorId): void
    {
        $this->authorId = $authorId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markDraft(): void
    {
        $this->status = 'draft';
        $this->publishedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }
}
