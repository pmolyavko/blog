<?php

declare(strict_types=1);

namespace App\Application\Dto;

final class RenderedArticleView
{
    public function __construct(
        public readonly int $id,
        public readonly string $html,
        public readonly int $freshUntil,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'html' => $this->html,
            'freshUntil' => $this->freshUntil,
        ];
    }
}
