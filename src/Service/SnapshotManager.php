<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\DoctrineSnapshotBundle\Event\PostSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Event\PreSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Exception\SnapshotException;
use Tourze\DoctrineSnapshotBundle\Repository\SnapshotRepository;

class SnapshotManager
{
    private bool $autoSnapshotEnabled;

    /** @var array<string, mixed> */
    private array $defaultContext;

    /** @var array<string> */
    private array $globalExcludeProperties;

    private int $defaultMaxDepth;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NormalizerInterface&DenormalizerInterface $serializer,
        private EventDispatcherInterface $eventDispatcher,
        private SnapshotRepository $snapshotRepository,
    ) {
        $this->autoSnapshotEnabled = filter_var($_ENV['SNAPSHOT_AUTO_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->defaultMaxDepth = (int) ($_ENV['SNAPSHOT_DEFAULT_MAX_DEPTH'] ?? 1);
        $this->globalExcludeProperties = $this->parseExcludeProperties();
        $this->defaultContext = $this->buildDefaultContext();
    }

    /**
     * @return array<string>
     */
    private function parseExcludeProperties(): array
    {
        $excludeList = $_ENV['SNAPSHOT_EXCLUDE_PROPERTIES'] ?? '__initializer__,__cloner__,__isInitialized__';

        return array_map('trim', explode(',', $excludeList));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultContext(): array
    {
        return [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                /** @var class-string $className */
                $className = get_class($object);

                return $this->getEntityId($object, $this->entityManager->getClassMetadata($className));
            },
            AbstractNormalizer::IGNORED_ATTRIBUTES => $this->globalExcludeProperties,
            'enable_max_depth' => true,
            'max_depth' => $this->defaultMaxDepth,
        ];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function getEntityId(object $entity, ClassMetadata $metadata): string
    {
        $idValues = $metadata->getIdentifierValues($entity);

        if (1 === count($idValues)) {
            return (string) reset($idValues);
        }

        return (string) json_encode($idValues);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function create(object $entity, array $context = []): Snapshot
    {
        $preEvent = new PreSnapshotEvent($entity, $context);
        $this->eventDispatcher->dispatch($preEvent);
        $context = $preEvent->getContext();

        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);

        $serializerContext = $this->prepareSerializerContext($context);

        $data = $this->serializer->normalize($entity, null, $serializerContext);
        if (!is_array($data)) {
            throw new SnapshotException('Failed to normalize entity to array');
        }

        $snapshot = new Snapshot();
        $snapshot->setSourceClass($entityClass);
        $snapshot->setSourceId($entityId);
        $snapshot->setData($data);
        $snapshot->setMetadata(['context' => $context]);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $this->entityManager->persist($snapshot);

        $postEvent = new PostSnapshotEvent($entity, $snapshot);
        $this->eventDispatcher->dispatch($postEvent);

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function prepareSerializerContext(array $context): array
    {
        return array_merge($this->defaultContext, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hydrate(Snapshot $snapshot, array $context = []): object
    {
        $sourceClass = $snapshot->getSourceClass();
        $data = $snapshot->getData();

        $hydrateContext = array_merge([
            AbstractNormalizer::OBJECT_TO_POPULATE => null,
        ], $context);

        return $this->serializer->denormalize($data, $sourceClass, null, $hydrateContext);
    }

    public function findLatestSnapshot(object $entity): ?Snapshot
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);

        return $this->snapshotRepository
            ->findOneBy(
                ['sourceClass' => $entityClass, 'sourceId' => $entityId],
                ['createTime' => 'DESC']
            )
        ;
    }

    /**
     * @return Snapshot[]
     */
    public function findSnapshots(object $entity, ?int $limit = null): array
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);

        $criteria = ['sourceClass' => $entityClass, 'sourceId' => $entityId];
        $orderBy = ['createTime' => 'DESC'];

        return $this->snapshotRepository
            ->findBy($criteria, $orderBy, $limit)
        ;
    }

    public function isAutoSnapshotEnabled(): bool
    {
        return $this->autoSnapshotEnabled;
    }
}
