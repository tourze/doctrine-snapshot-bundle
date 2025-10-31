<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\Event\PreSnapshotEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(PreSnapshotEvent::class)]
final class PreSnapshotEventTest extends AbstractEventTestCase
{
    public function testCanBeInstantiated(): void
    {
        $event = new PreSnapshotEvent(new \stdClass(), []);
        $this->assertInstanceOf(PreSnapshotEvent::class, $event);
    }
}
