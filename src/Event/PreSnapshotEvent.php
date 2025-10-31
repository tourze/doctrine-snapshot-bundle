<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PreSnapshotEvent extends Event
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private object $entity,
        private array $context,
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
