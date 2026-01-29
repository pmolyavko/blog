<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\Dto\ArticleView;
use App\Application\Dto\RenderedArticleView;
use App\Application\Mapper\ArticleViewFactory;
use App\Application\Port\ArticleCachePort;
use App\Domain\Blog\Article;
use App\Domain\Blog\Repository\ArticleRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Redis;

final class RedisArticleCache implements ArticleCachePort
{
    private const ARTICLE_TTL = 300;
    private const LIST_TTL = 120;
    private const STALE_TTL = 180;
    private const LOCK_TTL = 5;
    private const WAIT_USEC = 50000;
    private const WAIT_ATTEMPTS = 5;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private Redis $redis,
        private LoggerInterface $logger,
        private string $listVersionKey,
        private ArticleRepositoryInterface $articles,
        private ArticleViewFactory $viewFactory,
    ) {
    }

    public function getArticle(int $id, array $context = []): ?ArticleView
    {
        $key = $this->buildArticleKey($id, $context);
        $payload = $this->getWithSingleFlight($key, self::ARTICLE_TTL, function () use ($id): ?array {
            $article = $this->articles->findById($id);
            if ($article === null) {
                return null;
            }

            return $this->viewFactory->fromDomain($article)->toArray();
        });

        return $payload ? $this->hydrateArticleView($payload) : null;
    }

    public function getRenderedArticle(int $id, array $context = []): ?RenderedArticleView
    {
        $key = $this->buildRenderedKey($id, $context);
        $payload = $this->getWithStaleWhileRevalidate($key, self::ARTICLE_TTL, self::STALE_TTL, function () use ($id): ?array {
            $article = $this->articles->findById($id);
            if ($article === null) {
                return null;
            }

            return [
                'id' => $article->id() ?? 0,
                'html' => sprintf('<article><h1>%s</h1><p>%s</p></article>', $article->title(), $article->content()),
                'freshUntil' => time(),
            ];
        });

        if ($payload === null) {
            return null;
        }

        return new RenderedArticleView($payload['id'], $payload['html'], $payload['freshUntil']);
    }

    public function getLatestList(int $page, int $limit, array $context = []): array
    {
        $version = $this->getListVersion();
        $key = sprintf('articles:list:v%d:page:%d:limit:%d:%s', $version, $page, $limit, $this->contextSuffix($context));
        $payload = $this->getWithSingleFlight($key, self::LIST_TTL, function () use ($limit, $page): array {
            $offset = ($page - 1) * $limit;
            $articles = $this->articles->findLatestPublished($limit, $offset);
            return array_map(fn (Article $article) => $this->viewFactory->fromDomain($article)->toArray(), $articles);
        });

        return array_map(fn (array $item) => $this->hydrateArticleView($item), $payload);
    }

    public function getTagList(int $tagId, int $page, int $limit, array $context = []): array
    {
        $version = $this->getListVersion();
        $key = sprintf('articles:tag:%d:v%d:page:%d:limit:%d:%s', $tagId, $version, $page, $limit, $this->contextSuffix($context));
        $payload = $this->getWithSingleFlight($key, self::LIST_TTL, fn (): array => []);

        return array_map(fn (array $item) => $this->hydrateArticleView($item), $payload);
    }

    public function getAuthorList(int $authorId, int $page, int $limit, array $context = []): array
    {
        $version = $this->getListVersion();
        $key = sprintf('articles:author:%d:v%d:page:%d:limit:%d:%s', $authorId, $version, $page, $limit, $this->contextSuffix($context));
        $payload = $this->getWithSingleFlight($key, self::LIST_TTL, fn (): array => []);

        return array_map(fn (array $item) => $this->hydrateArticleView($item), $payload);
    }

    public function invalidateArticle(Article $article): void
    {
        $baseContext = ['language' => $article->language()];
        $key = $this->buildArticleKey((int) $article->id(), $baseContext);
        $this->cache->deleteItem($key);
        $this->cache->deleteItem($this->buildRenderedKey((int) $article->id(), $baseContext));
        $this->bumpListVersion();

        $this->deletePattern('articles:latest:*');
        $this->deletePattern(sprintf('articles:author:%d:*', $article->authorId()));
        foreach ($article->tagIds() as $tagId) {
            $this->deletePattern(sprintf('articles:tag:%d:*', $tagId));
        }
    }

    private function buildArticleKey(int $id, array $context): string
    {
        return sprintf('article:%d:%s', $id, $this->contextSuffix($context));
    }

    private function buildRenderedKey(int $id, array $context): string
    {
        return sprintf('article:rendered:%d:%s', $id, $this->contextSuffix($context));
    }

    private function contextSuffix(array $context): string
    {
        $language = $context['language'] ?? 'ru';
        $role = $context['role'] ?? 'guest';
        $ab = $context['ab'] ?? 'default';

        return sprintf('lang:%s:role:%s:ab:%s', $language, $role, $ab);
    }

    private function getListVersion(): int
    {
        $version = $this->redis->get($this->listVersionKey);
        if ($version === false) {
            $this->redis->set($this->listVersionKey, '1');
            return 1;
        }

        return (int) $version;
    }

    private function bumpListVersion(): int
    {
        return (int) $this->redis->incr($this->listVersionKey);
    }

    private function getWithSingleFlight(string $key, int $ttl, callable $loader): mixed
    {
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        $lockKey = sprintf('lock:%s', $key);
        if ($this->acquireLock($lockKey)) {
            try {
                $payload = $loader();
                $item->set($payload);
                $item->expiresAfter($ttl);
                $this->cache->save($item);
                return $payload;
            } finally {
                $this->releaseLock($lockKey);
            }
        }

        for ($attempt = 0; $attempt < self::WAIT_ATTEMPTS; $attempt++) {
            usleep(self::WAIT_USEC);
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $payload = $loader();
        $item->set($payload);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
        return $payload;
    }

    private function getWithStaleWhileRevalidate(string $key, int $ttl, int $staleTtl, callable $loader): ?array
    {
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            $payload = $item->get();
            if (!isset($payload['freshUntil'])) {
                return $payload;
            }

            if ($payload['freshUntil'] >= time()) {
                return $payload;
            }

            $this->logger->info('Serving stale cache and revalidating in background', ['key' => $key]);
            $this->refreshInBackground($key, $ttl, $staleTtl, $loader);
            return $payload;
        }

        $payload = $loader();
        if ($payload === null) {
            return null;
        }

        $this->storeStalePayload($key, $payload, $ttl, $staleTtl);
        return $payload;
    }

    private function storeStalePayload(string $key, array $payload, int $ttl, int $staleTtl): void
    {
        $payload['freshUntil'] = time() + $ttl;
        $item = $this->cache->getItem($key);
        $item->set($payload);
        $item->expiresAfter($ttl + $staleTtl);
        $this->cache->save($item);
    }

    private function refreshInBackground(string $key, int $ttl, int $staleTtl, callable $loader): void
    {
        if (!$this->acquireLock(sprintf('lock:%s:refresh', $key))) {
            return;
        }

        try {
            $payload = $loader();
            if ($payload !== null) {
                $this->storeStalePayload($key, $payload, $ttl, $staleTtl);
            }
        } finally {
            $this->releaseLock(sprintf('lock:%s:refresh', $key));
        }
    }

    private function acquireLock(string $lockKey): bool
    {
        return (bool) $this->redis->set($lockKey, '1', ['nx', 'ex' => self::LOCK_TTL]);
    }

    private function releaseLock(string $lockKey): void
    {
        $this->redis->del($lockKey);
    }

    private function deletePattern(string $pattern): void
    {
        $cursor = null;
        do {
            $result = $this->redis->scan($cursor, $pattern, 100);
            if ($result === false) {
                break;
            }
            if ($result !== []) {
                $this->redis->unlink(...$result);
            }
        } while ($cursor !== 0);
    }

    private function hydrateArticleView(array $payload): ArticleView
    {
        return new ArticleView(
            $payload['id'],
            $payload['title'],
            $payload['slug'],
            $payload['content'],
            $payload['status'],
            $payload['publishedAt'],
            $payload['updatedAt'],
            $payload['authorId'],
            $payload['language'],
            $payload['tagIds'],
        );
    }
}
