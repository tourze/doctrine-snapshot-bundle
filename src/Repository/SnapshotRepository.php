<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Snapshot>
 */
#[AsRepository(entityClass: Snapshot::class)]
class SnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Snapshot::class);
    }

    /**
     * @return Snapshot[]
     */
    public function findBySource(string $sourceClass, string $sourceId, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.sourceClass = :sourceClass')
            ->andWhere('s.sourceId = :sourceId')
            ->setParameter('sourceClass', $sourceClass)
            ->setParameter('sourceId', $sourceId)
            ->orderBy('s.createTime', 'DESC')
        ;

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestBySource(string $sourceClass, string $sourceId): ?Snapshot
    {
        return $this->findOneBy(
            ['sourceClass' => $sourceClass, 'sourceId' => $sourceId],
            ['createTime' => 'DESC']
        );
    }

    /**
     * @return Snapshot[]
     */
    public function findBySourceClass(string $sourceClass, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.sourceClass = :sourceClass')
            ->setParameter('sourceClass', $sourceClass)
            ->orderBy('s.createTime', 'DESC')
        ;

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function deleteOldSnapshots(\DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.createTime < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(Snapshot $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Snapshot $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
