<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\DoctrineSnapshotBundle\Event\PostSnapshotEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(PostSnapshotEvent::class)]
final class PostSnapshotEventTest extends AbstractEventTestCase
{
    public function testCanBeInstantiated(): void
    {
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('stdClass');
        $snapshot->setSourceId('123');
        $snapshot->setData([]);
        $snapshot->setCreateTime(new \DateTimeImmutable());
        $event = new PostSnapshotEvent(new \stdClass(), $snapshot);
        $this->assertInstanceOf(PostSnapshotEvent::class, $event);
    }
}
