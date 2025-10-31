<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineSnapshotBundle\EventListener\SnapshotListener;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;
use Tourze\DoctrineSnapshotBundle\Tests\EventListener\Fixtures\TestEntityWithSnapshot;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(SnapshotListener::class)]
#[RunTestsInSeparateProcesses]
class SnapshotListenerTest extends AbstractEventSubscriberTestCase
{
    private SnapshotManager $snapshotManager;

    private SnapshotListener $listener;

    public function testListenerIsRegistered(): void
    {
        $this->assertInstanceOf(SnapshotListener::class, $this->listener);
        $this->assertInstanceOf(SnapshotManager::class, $this->snapshotManager);
    }

    public function testPrePersistCallsListener(): void
    {
        $entity = new TestEntityWithSnapshot();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $args = new PrePersistEventArgs($entity, $entityManager);

        // 这是一个集成测试，验证监听器可以被调用而不抛出异常
        $this->expectNotToPerformAssertions();
        $this->listener->prePersist($args);
    }

    public function testPreUpdateCallsListener(): void
    {
        $entity = new TestEntityWithSnapshot();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $changeSet = [];
        $args = new PreUpdateEventArgs($entity, $entityManager, $changeSet);

        // 这是一个集成测试，验证监听器可以被调用而不抛出异常
        $this->expectNotToPerformAssertions();
        $this->listener->preUpdate($args);
    }

    protected function onSetUp(): void
    {
        /** @var SnapshotManager $snapshotManager */
        $snapshotManager = self::getContainer()->get(SnapshotManager::class);
        /** @var SnapshotListener $listener */
        $listener = self::getContainer()->get(SnapshotListener::class);

        $this->snapshotManager = $snapshotManager;
        $this->listener = $listener;
    }
}
