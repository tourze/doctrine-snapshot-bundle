<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;
use Tourze\DoctrineSnapshotBundle\Exception\InvalidSnapshotTargetException;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;

#[AsDoctrineListener(event: 'prePersist')]
#[AsDoctrineListener(event: 'preUpdate')]
readonly class SnapshotListener
{
    public function __construct(
        private SnapshotManager $snapshotManager,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!$this->snapshotManager->isAutoSnapshotEnabled()) {
            return;
        }

        $this->handleSnapshot($args->getObject(), $args);
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    private function handleSnapshot(object $entity, LifecycleEventArgs $args): void
    {
        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getProperties() as $property) {
            $snapshotAttributes = $property->getAttributes(Snapshot::class);

            if ([] === $snapshotAttributes) {
                continue;
            }

            /** @var Snapshot $snapshotAttr */
            $snapshotAttr = $snapshotAttributes[0]->newInstance();
            $property->setAccessible(true);
            $sourceEntity = $property->getValue($entity);

            if (null === $sourceEntity) {
                continue;
            }

            if ($sourceEntity instanceof \Tourze\DoctrineSnapshotBundle\Entity\Snapshot) {
                continue;
            }

            $targetPropertyName = $snapshotAttr->getTargetSnapshotProperty($property->getName());

            if (!$reflection->hasProperty($targetPropertyName)) {
                throw new InvalidSnapshotTargetException(sprintf('Target snapshot property "%s" does not exist in class "%s"', $targetPropertyName, $reflection->getName()));
            }

            $context = array_merge(
                $snapshotAttr->context,
                ['groups' => $snapshotAttr->groups]
            );

            $snapshot = $this->snapshotManager->create($sourceEntity, $context);

            $targetProperty = $reflection->getProperty($targetPropertyName);
            $targetProperty->setAccessible(true);
            $targetProperty->setValue($entity, $snapshot);

            if ($snapshotAttr->cascade) {
                $args->getObjectManager()->persist($snapshot);
            }

            $property->setValue($entity, null);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->snapshotManager->isAutoSnapshotEnabled()) {
            return;
        }

        $this->handleSnapshot($args->getObject(), $args);
    }
}
