<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\GetArticleUseCase;
use App\Application\UseCase\GetRenderedArticleUseCase;
use App\Application\UseCase\ListLatestArticlesUseCase;
use App\Application\UseCase\PublishArticleUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController
{
    public function __construct(
        private GetArticleUseCase $getArticle,
        private ListLatestArticlesUseCase $listLatest,
        private GetRenderedArticleUseCase $getRendered,
        private PublishArticleUseCase $publishArticle,
    ) {
    }

    #[Route('/articles/{id}', name: 'article_show', methods: ['GET'])]
    public function show(int $id, Request $request): JsonResponse
    {
        $context = $this->contextFromRequest($request);
        $view = $this->getArticle->execute($id, $context);

        if ($view === null) {
            return new JsonResponse(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($view->toArray());
    }

    #[Route('/articles', name: 'article_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(20, max(1, (int) $request->query->get('limit', 10)));
        $context = $this->contextFromRequest($request);

        $items = $this->listLatest->execute($page, $limit, $context);

        return new JsonResponse([
            'items' => array_map(fn ($item) => $item->toArray(), $items),
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/articles/{id}/rendered', name: 'article_rendered', methods: ['GET'])]
    public function rendered(int $id, Request $request): JsonResponse
    {
        $context = $this->contextFromRequest($request);
        $view = $this->getRendered->execute($id, $context);

        if ($view === null) {
            return new JsonResponse(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($view->toArray());
    }

    #[Route('/admin/articles/{id}/publish', name: 'article_publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse
    {
        $success = $this->publishArticle->execute($id);

        if (!$success) {
            return new JsonResponse(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function contextFromRequest(Request $request): array
    {
        return [
            'language' => $request->query->get('lang', 'ru'),
            'role' => $request->query->get('role', 'guest'),
            'ab' => $request->query->get('ab', 'default'),
        ];
    }
}
