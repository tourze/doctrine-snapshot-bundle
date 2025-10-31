<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;

class PostSnapshotEvent extends Event
{
    public function __construct(
        private object $entity,
        private Snapshot $snapshot,
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getSnapshot(): Snapshot
    {
        return $this->snapshot;
    }
}
