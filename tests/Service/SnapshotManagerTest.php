<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\DoctrineSnapshotBundle\Event\PostSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Event\PreSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Repository\SnapshotRepository;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;

/**
 * @internal
 */
#[CoversClass(SnapshotManager::class)]
class SnapshotManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private NormalizerInterface&DenormalizerInterface $serializer;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private SnapshotRepository&MockObject $snapshotRepository;

    private SnapshotManager $snapshotManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->snapshotRepository = $this->createMock(SnapshotRepository::class);

        // Create a mock that implements both interfaces
        $this->serializer = new class implements NormalizerInterface, DenormalizerInterface {
            /** @var callable|null */
            public $normalizeCallback;

            /** @var callable|null */
            public $denormalizeCallback;

            /**
             * @param array<mixed> $context
             * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
             */
            public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
            {
                if (null !== $this->normalizeCallback) {
                    /** @var array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null */
                    return ($this->normalizeCallback)($object, $format, $context);
                }

                return [];
            }

            /**
             * @param array<mixed> $context
             */
            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
            {
                if (null !== $this->denormalizeCallback) {
                    return ($this->denormalizeCallback)($data, $type, $format, $context);
                }

                return new \stdClass();
            }

            /**
             * @param array<mixed> $context
             */
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            /**
             * @param array<mixed> $context
             */
            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            /**
             * @return array<string, bool>
             */
            public function getSupportedTypes(?string $format): array
            {
                return ['*' => true];
            }
        };

        $_ENV['SNAPSHOT_AUTO_ENABLED'] = 'true';
        $_ENV['SNAPSHOT_DEFAULT_MAX_DEPTH'] = '2';
        $_ENV['SNAPSHOT_EXCLUDE_PROPERTIES'] = '__initializer__,__cloner__';

        $this->snapshotManager = new SnapshotManager(
            $this->entityManager,
            $this->serializer,
            $this->eventDispatcher,
            $this->snapshotRepository
        );
    }

    public function testCreateSnapshot(): void
    {
        $entity = new \stdClass();
        $entity->id = 123;
        $entity->name = 'Test Product';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn('App\Entity\Product')
        ;
        $metadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->willReturn(['id' => 123])
        ;

        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(get_class($entity))
            ->willReturn($metadata)
        ;

        $normalizedData = ['id' => 123, 'name' => 'Test Product'];
        if (property_exists($this->serializer, 'normalizeCallback')) {
            $this->serializer->normalizeCallback = function ($object, $format, $context) use ($normalizedData) {
                return $normalizedData;
            };
        }

        $eventCallCount = 0;
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$eventCallCount) {
                ++$eventCallCount;
                if (1 === $eventCallCount) {
                    $this->assertInstanceOf(PreSnapshotEvent::class, $event);
                } elseif (2 === $eventCallCount) {
                    $this->assertInstanceOf(PostSnapshotEvent::class, $event);
                }

                return $event;
            })
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::callback(static function ($snapshot): bool {
                return $snapshot instanceof Snapshot;
            }))
        ;

        $snapshot = $this->snapshotManager->create($entity, ['custom' => 'context']);

        $this->assertInstanceOf(Snapshot::class, $snapshot);
        $this->assertEquals('App\Entity\Product', $snapshot->getSourceClass());
        $this->assertEquals('123', $snapshot->getSourceId());
        $this->assertEquals($normalizedData, $snapshot->getData());
    }

    public function testHydrateSnapshot(): void
    {
        $data = ['id' => 123, 'name' => 'Test Product'];
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('123');
        $snapshot->setData($data);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $hydratedEntity = new \stdClass();
        $hydratedEntity->id = 123;
        $hydratedEntity->name = 'Test Product';

        if (property_exists($this->serializer, 'denormalizeCallback')) {
            $this->serializer->denormalizeCallback = function ($data, $type, $format, $context) use ($hydratedEntity) {
                return $hydratedEntity;
            };
        }

        $result = $this->snapshotManager->hydrate($snapshot);

        $this->assertEquals($hydratedEntity, $result);
    }

    public function testFindLatestSnapshot(): void
    {
        $entity = new \stdClass();
        $entity->id = 456;

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn('App\Entity\Customer')
        ;
        $metadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->willReturn(['id' => 456])
        ;

        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(get_class($entity))
            ->willReturn($metadata)
        ;

        $expectedSnapshot = new Snapshot();
        $expectedSnapshot->setSourceClass('App\Entity\Customer');
        $expectedSnapshot->setSourceId('456');
        $expectedSnapshot->setData(['data' => 'test']);
        $expectedSnapshot->setCreateTime(new \DateTimeImmutable());

        $this->snapshotRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                ['sourceClass' => 'App\Entity\Customer', 'sourceId' => '456'],
                ['createTime' => 'DESC']
            )
            ->willReturn($expectedSnapshot)
        ;

        $result = $this->snapshotManager->findLatestSnapshot($entity);

        $this->assertEquals($expectedSnapshot, $result);
    }

    public function testIsAutoSnapshotEnabled(): void
    {
        $this->assertTrue($this->snapshotManager->isAutoSnapshotEnabled());

        $_ENV['SNAPSHOT_AUTO_ENABLED'] = 'false';
        $manager = new SnapshotManager(
            $this->entityManager,
            $this->serializer,
            $this->eventDispatcher,
            $this->snapshotRepository
        );

        $this->assertFalse($manager->isAutoSnapshotEnabled());
    }

    public function testCompositeKeyEntityId(): void
    {
        $entity = new \stdClass();
        $entity->key1 = 'abc';
        $entity->key2 = 123;

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn('App\Entity\CompositeKey')
        ;
        $metadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->willReturn(['key1' => 'abc', 'key2' => 123])
        ;

        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(get_class($entity))
            ->willReturn($metadata)
        ;

        if (property_exists($this->serializer, 'normalizeCallback')) {
            $this->serializer->normalizeCallback = function ($object, $format, $context) {
                return ['data' => 'test'];
            };
        }

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
        ;

        $snapshot = $this->snapshotManager->create($entity);

        $this->assertEquals('{"key1":"abc","key2":123}', $snapshot->getSourceId());
    }

    public function testFindSnapshots(): void
    {
        $entity = new \stdClass();
        $entity->id = 789;

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn('App\Entity\User')
        ;
        $metadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->willReturn(['id' => 789])
        ;

        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(get_class($entity))
            ->willReturn($metadata)
        ;

        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\User');
        $snapshot1->setSourceId('789');
        $snapshot1->setData(['data1' => 'test1']);
        $snapshot1->setCreateTime(new \DateTimeImmutable());

        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\User');
        $snapshot2->setSourceId('789');
        $snapshot2->setData(['data2' => 'test2']);
        $snapshot2->setCreateTime(new \DateTimeImmutable());

        $expectedSnapshots = [$snapshot1, $snapshot2];

        $this->snapshotRepository->expects($this->once())
            ->method('findBy')
            ->with(
                ['sourceClass' => 'App\Entity\User', 'sourceId' => '789'],
                ['createTime' => 'DESC'],
                5
            )
            ->willReturn($expectedSnapshots)
        ;

        $result = $this->snapshotManager->findSnapshots($entity, 5);

        $this->assertEquals($expectedSnapshots, $result);
    }

    protected function tearDown(): void
    {
        unset($_ENV['SNAPSHOT_AUTO_ENABLED'], $_ENV['SNAPSHOT_DEFAULT_MAX_DEPTH'], $_ENV['SNAPSHOT_EXCLUDE_PROPERTIES']);
    }
}
