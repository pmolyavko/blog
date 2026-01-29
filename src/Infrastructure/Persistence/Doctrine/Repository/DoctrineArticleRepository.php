<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Blog\Article;
use App\Domain\Blog\Repository\ArticleRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\ArticleRecord;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findById(int $id): ?Article
    {
        $record = $this->entityManager->find(ArticleRecord::class, $id);
        if (!$record instanceof ArticleRecord) {
            return null;
        }

        return $this->toDomain($record);
    }

    public function findLatestPublished(int $limit, int $offset): array
    {
        $records = $this->entityManager->getRepository(ArticleRecord::class)
            ->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return array_map(fn (ArticleRecord $record) => $this->toDomain($record), $records);
    }

    public function save(Article $article): void
    {
        $record = $article->id() === null
            ? new ArticleRecord()
            : $this->entityManager->find(ArticleRecord::class, $article->id());

        if (!$record instanceof ArticleRecord) {
            $record = new ArticleRecord();
        }

        $record->setTitle($article->title());
        $record->setSlug($article->slug());
        $record->setContent($article->content());
        $record->setStatus($article->status());
        $record->setPublishedAt($article->publishedAt());
        $record->setUpdatedAt($article->updatedAt());
        $record->setAuthorId($article->authorId());
        $record->setLanguage($article->language());
        $record->setTagIds($article->tagIds());

        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    private function toDomain(ArticleRecord $record): Article
    {
        return new Article(
            $record->getId(),
            $record->getTitle(),
            $record->getSlug(),
            $record->getContent(),
            $record->getAuthorId(),
            $record->getLanguage(),
            $record->getTagIds(),
            $record->getStatus(),
            $record->getPublishedAt(),
            $record->getUpdatedAt(),
        );
    }
}
