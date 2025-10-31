<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\EventListener\Fixtures;

use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;

class TestEntityWithoutTargetProperty
{
    #[Snapshot]
    public ?object $invalidSource = null;
}
