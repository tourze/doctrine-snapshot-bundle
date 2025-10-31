<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;

class SnapshotFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\Product');
        $snapshot1->setSourceId('1');
        $snapshot1->setData([
            'id' => 1,
            'name' => 'Test Product 1',
            'price' => 99.99,
        ]);
        $snapshot1->setMetadata(['created_by' => 'test_user']);
        $snapshot1->setCreateTime(new \DateTimeImmutable());
        $manager->persist($snapshot1);

        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\Product');
        $snapshot2->setSourceId('2');
        $snapshot2->setData([
            'id' => 2,
            'name' => 'Test Product 2',
            'price' => 149.99,
        ]);
        $snapshot2->setCreateTime(new \DateTimeImmutable());
        $manager->persist($snapshot2);

        $snapshot3 = new Snapshot();
        $snapshot3->setSourceClass('App\Entity\Order');
        $snapshot3->setSourceId('100');
        $snapshot3->setData([
            'id' => 100,
            'orderNumber' => 'ORD-2024-001',
            'totalAmount' => 249.98,
            'items' => [
                ['productId' => 1, 'quantity' => 1, 'price' => 99.99],
                ['productId' => 2, 'quantity' => 1, 'price' => 149.99],
            ],
        ]);
        $snapshot3->setCreateTime(new \DateTimeImmutable());
        $manager->persist($snapshot3);

        $manager->flush();
    }
}
