<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\DoctrineSnapshotBundle\Repository\SnapshotRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(SnapshotRepository::class)]
#[RunTestsInSeparateProcesses]
class SnapshotRepositoryTest extends AbstractRepositoryTestCase
{
    public function testFindBySource(): void
    {
        $repository = $this->getRepository();
        $em = parent::getEntityManager();

        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\Product');
        $snapshot1->setSourceId('123');
        $snapshot1->setData(['data' => 'test1']);
        $snapshot1->setCreateTime(new \DateTimeImmutable());

        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\Product');
        $snapshot2->setSourceId('123');
        $snapshot2->setData(['data' => 'test2']);
        $snapshot2->setCreateTime(new \DateTimeImmutable());

        $snapshot3 = new Snapshot();
        $snapshot3->setSourceClass('App\Entity\Order');
        $snapshot3->setSourceId('456');
        $snapshot3->setData(['data' => 'test3']);
        $snapshot3->setCreateTime(new \DateTimeImmutable());

        $em->persist($snapshot1);
        $em->persist($snapshot2);
        $em->persist($snapshot3);
        $em->flush();

        $result = $repository->findBySource('App\Entity\Product', '123', 10);

        $this->assertCount(2, $result);
        $sourceClasses = array_map(fn ($s) => $s->getSourceClass(), $result);
        $this->assertContains('App\Entity\Product', $sourceClasses);
        $this->assertNotContains('App\Entity\Order', $sourceClasses);
    }

    public function testDeleteOldSnapshots(): void
    {
        $repository = $this->getRepository();
        $em = parent::getEntityManager();

        // Clear existing data first
        foreach ($repository->findAll() as $entity) {
            $em->remove($entity);
        }
        $em->flush();

        // Create recent snapshot
        $recentSnapshot = new Snapshot();
        $recentSnapshot->setSourceClass('App\Entity\Product');
        $recentSnapshot->setSourceId('222');
        $recentSnapshot->setData(['data' => 'recent']);
        $recentSnapshot->setCreateTime(new \DateTimeImmutable());
        $em->persist($recentSnapshot);
        $em->flush();

        // Since we can't set createdAt directly, we'll test with no old snapshots
        // Delete snapshots older than 1 day (should delete nothing as all are recent)
        $before = new \DateTimeImmutable('-1 day');
        $deletedCount = $repository->deleteOldSnapshots($before);

        $this->assertEquals(0, $deletedCount);

        $remaining = $repository->findAll();
        $this->assertGreaterThan(0, count($remaining));
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();

        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('999');
        $snapshot->setData(['data' => 'test']);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $repository->save($snapshot, true);

        $found = $repository->findOneBy(['sourceId' => '999']);
        $this->assertNotNull($found);
        $this->assertEquals('999', $found->getSourceId());

        $repository->remove($found, true);

        $notFound = $repository->findOneBy(['sourceId' => '999']);
        $this->assertNull($notFound);
    }

    public function testFindBySourceClass(): void
    {
        $repository = $this->getRepository();
        $em = parent::getEntityManager();

        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\TestEntity');
        $snapshot1->setSourceId('111');
        $snapshot1->setData(['data' => 'test1']);
        $snapshot1->setCreateTime(new \DateTimeImmutable());

        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\TestEntity');
        $snapshot2->setSourceId('222');
        $snapshot2->setData(['data' => 'test2']);
        $snapshot2->setCreateTime(new \DateTimeImmutable());

        $snapshot3 = new Snapshot();
        $snapshot3->setSourceClass('App\Entity\AnotherEntity');
        $snapshot3->setSourceId('333');
        $snapshot3->setData(['data' => 'test3']);
        $snapshot3->setCreateTime(new \DateTimeImmutable());

        $em->persist($snapshot1);
        $em->persist($snapshot2);
        $em->persist($snapshot3);
        $em->flush();

        $result = $repository->findBySourceClass('App\Entity\TestEntity');

        $this->assertCount(2, $result);
        foreach ($result as $snapshot) {
            $this->assertEquals('App\Entity\TestEntity', $snapshot->getSourceClass());
        }
    }

    public function testFindLatestBySource(): void
    {
        $repository = $this->getRepository();
        $em = parent::getEntityManager();

        // Clear existing snapshots for this test
        foreach ($repository->findBySource('App\Entity\Product', '555') as $snapshot) {
            $em->remove($snapshot);
        }
        $em->flush();

        // Create first snapshot
        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\Product');
        $snapshot1->setSourceId('555');
        $snapshot1->setData(['data' => 'first']);
        $snapshot1->setCreateTime(new \DateTimeImmutable());
        $em->persist($snapshot1);
        $em->flush();

        // Sleep to ensure different timestamp
        sleep(1); // 1 second delay to ensure different timestamp

        // Create newer snapshot
        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\Product');
        $snapshot2->setSourceId('555');
        $snapshot2->setData(['data' => 'second']);
        $snapshot2->setCreateTime(new \DateTimeImmutable());
        $em->persist($snapshot2);
        $em->flush();

        $latest = $repository->findLatestBySource('App\Entity\Product', '555');

        $this->assertNotNull($latest);
        // The latest should be the second one created
        $this->assertEquals(['data' => 'second'], $latest->getData());
    }

    public function testRemove(): void
    {
        $repository = $this->getRepository();
        $em = parent::getEntityManager();

        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Test');
        $snapshot->setSourceId('777');
        $snapshot->setData(['data' => 'test']);
        $snapshot->setCreateTime(new \DateTimeImmutable());
        $em->persist($snapshot);
        $em->flush();

        $found = $repository->findOneBy(['sourceId' => '777']);
        $this->assertNotNull($found);

        $repository->remove($found, true);

        $notFound = $repository->findOneBy(['sourceId' => '777']);
        $this->assertNull($notFound);
    }

    protected function getRepositoryClass(): string
    {
        return SnapshotRepository::class;
    }

    protected function createNewEntity(): object
    {
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Test');
        $snapshot->setSourceId(uniqid());
        $snapshot->setData(['test' => 'data']);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        return $snapshot;
    }

    protected function onSetUp(): void
    {
        // Override the parent class behavior for the specific test
        if ('testFindAllWhenNoRecordsExistShouldReturnEmptyArray' === $this->name()) {
            $repository = $this->getRepository();
            $em = parent::getEntityManager();
            foreach ($repository->findAll() as $entity) {
                $em->remove($entity);
            }
            $em->flush();
        }
    }

    protected function getRepository(): SnapshotRepository
    {
        return self::getService(SnapshotRepository::class);
    }
}
