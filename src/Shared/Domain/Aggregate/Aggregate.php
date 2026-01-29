<?php

declare(strict_types=1);

namespace App\Shared\Domain\Aggregate;

abstract class Aggregate
{
    /**
     * @var object[]
     */
    private array $events = [];

    protected function raise(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return object[]
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}
