<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\EventListener\Fixtures;

use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot as SnapshotEntity;

class TestEntityWithSnapshot
{
    #[Snapshot(groups: 'test_snapshot')]
    private ?object $source = null;

    private ?SnapshotEntity $sourceSnapshot = null;

    public function getSource(): ?object
    {
        return $this->source;
    }

    public function setSource(?object $source): void
    {
        $this->source = $source;
    }

    public function getSourceSnapshot(): ?SnapshotEntity
    {
        return $this->sourceSnapshot;
    }

    public function setSourceSnapshot(?SnapshotEntity $snapshot): void
    {
        $this->sourceSnapshot = $snapshot;
    }
}
