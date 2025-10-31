# DoctrineSnapshotBundle 开发规范文档

## 1. 项目概述

### 1.1 核心目标
DoctrineSnapshotBundle 是一个 Symfony 扩展包，提供通用、可配置的解决方案，用于为 Doctrine 实体创建和管理"时间点"快照。当一个实体（如 Order）需要引用另一个实体（如 Product）在特定时刻的状态时，此 Bundle 将自动或手动地将目标实体的状态序列化并存储起来，防止未来对源实体的修改影响历史记录。

### 1.2 解决的核心问题
- **数据一致性**：确保订单、日志、审计记录等引用的关联数据是创建那一刻的精确状态
- **开发简化**：将快照逻辑抽象成独立的 Bundle，避免代码冗余
- **灵活性**：支持手动和自动两种快照创建模式，精细化控制快照内容

### 1.3 设计原则
- 使用专门的 Snapshot 实体存储所有快照数据（JSON格式）
- 通过 PHP Attributes（PHP 8）实现配置
- 提供统一的快照管理服务
- 充分利用 Symfony Serializer 的 Groups 功能

## 2. 核心概念定义

| 概念 | 说明 | 示例 |
|------|------|------|
| **源实体 (Source Entity)** | 需要被创建快照的原始 Doctrine 实体 | `App\Entity\Product` |
| **快照实体 (Snapshot Entity)** | 专门用于存储快照数据的 Doctrine 实体 | `Tourze\DoctrineSnapshotBundle\Entity\Snapshot` |
| **快照数据 (Snapshot Data)** | 源实体在某一时刻被序列化后的 JSON 数据 | `{"name": "iPhone", "price": 999}` |
| **快照所有者 (Snapshot Owner)** | 引用快照实体的实体 | `App\Entity\Order` |

## 3. 项目结构

```
DoctrineSnapshotBundle/
├── src/
│   ├── Attribute/
│   │   └── Snapshot.php              # 标记需要快照的属性（继承自 Groups）
│   ├── DependencyInjection/
│   │   └── TourzeDoctrineSnapshotExtension.php  # DI 扩展
│   ├── Entity/
│   │   └── Snapshot.php              # 核心快照实体
│   ├── Event/
│   │   ├── PreSnapshotEvent.php      # 快照前事件
│   │   └── PostSnapshotEvent.php     # 快照后事件
│   ├── EventListener/
│   │   └── SnapshotListener.php      # Doctrine 事件监听器
│   ├── Repository/
│   │   └── SnapshotRepository.php    # 快照查询仓库
│   ├── Service/
│   │   └── SnapshotManager.php       # 核心快照管理服务
│   └── TourzeDoctrineSnapshotBundle.php    # Bundle 主类
├── Resources/
│   └── config/
│       └── services.yaml             # 服务定义
├── tests/                            # 测试文件
└── composer.json
```

## 4. 核心组件详细设计

### 4.1 Snapshot 实体

```php
<?php
// src/Entity/Snapshot.php
namespace Tourze\DoctrineSnapshotBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnapshotBundle\Repository\SnapshotRepository;

#[ORM\Entity(repositoryClass: SnapshotRepository::class)]
#[ORM\Table(name: 'doctrine_snapshot')]
#[ORM\Index(columns: ['source_class', 'source_id'], name: 'idx_source')]
class Snapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $sourceClass;

    #[ORM\Column(type: 'string', length: 255)]
    private string $sourceId;

    #[ORM\Column(type: 'json')]
    private array $data;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 32)]
    private string $checksum;

    public function __construct(
        string $sourceClass,
        string $sourceId,
        array $data,
        ?array $metadata = null
    ) {
        $this->sourceClass = $sourceClass;
        $this->sourceId = $sourceId;
        $this->data = $data;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
        $this->checksum = $this->calculateChecksum($data);
    }

    private function calculateChecksum(array $data): string
    {
        return md5(json_encode($data));
    }

    // Getters...
    public function getId(): ?int { return $this->id; }
    public function getSourceClass(): string { return $this->sourceClass; }
    public function getSourceId(): string { return $this->sourceId; }
    public function getData(): array { return $this->data; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getVersion(): int { return $this->version; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getChecksum(): string { return $this->checksum; }
}
```

### 4.2 Snapshot Attribute（继承自 Symfony Serializer Groups）

```php
<?php
// src/Attribute/Snapshot.php
namespace Tourze\DoctrineSnapshotBundle\Attribute;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Snapshot Attribute 继承自 Symfony 的 Groups
 * 用于标记需要快照的属性，同时支持序列化分组
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class Snapshot extends Groups
{
    public const DEFAULT_GROUP = 'snapshot';
    
    public string $targetSnapshotProperty;
    public array $context;
    public bool $cascade;
    
    public function __construct(
        array|string $groups = self::DEFAULT_GROUP,
        string $targetSnapshotProperty = '',
        array $context = [],
        bool $cascade = true
    ) {
        // 调用父类构造函数设置 groups
        parent::__construct($groups);
        
        // 如果没有提供 targetSnapshotProperty，自动推断
        // 例如：product -> productSnapshot
        $this->targetSnapshotProperty = $targetSnapshotProperty;
        $this->context = $context;
        $this->cascade = $cascade;
    }
    
    /**
     * 获取目标快照属性名
     * 如果未设置，根据源属性名自动生成
     */
    public function getTargetSnapshotProperty(string $sourcePropertyName): string
    {
        if ($this->targetSnapshotProperty) {
            return $this->targetSnapshotProperty;
        }
        
        // 自动生成：product -> productSnapshot
        return $sourcePropertyName . 'Snapshot';
    }
}
```

### 4.3 SnapshotManager 服务

```php
<?php
// src/Service/SnapshotManager.php
namespace Tourze\DoctrineSnapshotBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\DoctrineSnapshotBundle\Event\PreSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Event\PostSnapshotEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SnapshotManager
{
    private bool $autoSnapshotEnabled;
    private array $defaultContext;
    private array $globalExcludeProperties;
    private int $defaultMaxDepth;
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private EventDispatcherInterface $eventDispatcher
    ) {
        // 从环境变量读取配置
        $this->autoSnapshotEnabled = filter_var($_ENV['SNAPSHOT_AUTO_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->defaultMaxDepth = (int)($_ENV['SNAPSHOT_DEFAULT_MAX_DEPTH'] ?? 1);
        $this->globalExcludeProperties = $this->parseExcludeProperties();
        $this->defaultContext = $this->buildDefaultContext();
    }

    /**
     * 为给定的实体创建快照
     */
    public function create(object $entity, array $context = []): Snapshot
    {
        // 1. 触发快照前事件
        $preEvent = new PreSnapshotEvent($entity, $context);
        $this->eventDispatcher->dispatch($preEvent);
        $context = $preEvent->getContext();
        
        // 2. 获取实体元数据
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);
        
        // 3. 准备序列化上下文
        $serializerContext = $this->prepareSerializerContext($context);
        
        // 4. 序列化实体
        $data = $this->serializer->normalize($entity, null, $serializerContext);
        
        // 5. 创建快照实体
        $snapshot = new Snapshot(
            $entityClass,
            $entityId,
            $data,
            ['context' => $context]
        );
        
        // 6. 持久化快照（不调用 flush）
        $this->entityManager->persist($snapshot);
        
        // 7. 触发快照后事件
        $postEvent = new PostSnapshotEvent($entity, $snapshot);
        $this->eventDispatcher->dispatch($postEvent);
        
        return $snapshot;
    }

    /**
     * 将快照恢复为对象（非托管）
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

    /**
     * 查找实体的最新快照
     */
    public function findLatestSnapshot(object $entity): ?Snapshot
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);
        
        return $this->entityManager->getRepository(Snapshot::class)
            ->findOneBy(
                ['sourceClass' => $entityClass, 'sourceId' => $entityId],
                ['createdAt' => 'DESC']
            );
    }

    /**
     * 查找实体的所有快照
     */
    public function findSnapshots(object $entity, int $limit = null): array
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $entityClass = $metadata->getName();
        $entityId = $this->getEntityId($entity, $metadata);
        
        $criteria = ['sourceClass' => $entityClass, 'sourceId' => $entityId];
        $orderBy = ['createdAt' => 'DESC'];
        
        return $this->entityManager->getRepository(Snapshot::class)
            ->findBy($criteria, $orderBy, $limit);
    }

    /**
     * 检查是否启用自动快照
     */
    public function isAutoSnapshotEnabled(): bool
    {
        return $this->autoSnapshotEnabled;
    }

    private function prepareSerializerContext(array $context): array
    {
        return array_merge($this->defaultContext, $context);
    }

    private function buildDefaultContext(): array
    {
        return [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $this->getEntityId($object, $this->entityManager->getClassMetadata(get_class($object)));
            },
            AbstractNormalizer::MAX_DEPTH_HANDLER => function ($object) {
                return null;
            },
            AbstractNormalizer::IGNORED_ATTRIBUTES => $this->globalExcludeProperties,
            'enable_max_depth' => true,
            'max_depth' => $this->defaultMaxDepth,
        ];
    }

    private function parseExcludeProperties(): array
    {
        $excludeList = $_ENV['SNAPSHOT_EXCLUDE_PROPERTIES'] ?? '__initializer__,__cloner__,__isInitialized__';
        return array_map('trim', explode(',', $excludeList));
    }

    private function getEntityId(object $entity, $metadata): string
    {
        $idValues = $metadata->getIdentifierValues($entity);
        
        if (count($idValues) === 1) {
            return (string) reset($idValues);
        }
        
        return json_encode($idValues);
    }
}
```

### 4.4 SnapshotListener 事件监听器

```php
<?php
// src/EventListener/SnapshotListener.php
namespace Tourze\DoctrineSnapshotBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;

#[AsDoctrineListener(event: 'prePersist')]
#[AsDoctrineListener(event: 'preUpdate')]
class SnapshotListener
{
    public function __construct(
        private SnapshotManager $snapshotManager
    ) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!$this->snapshotManager->isAutoSnapshotEnabled()) {
            return;
        }
        
        $this->handleSnapshot($args->getObject(), $args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->snapshotManager->isAutoSnapshotEnabled()) {
            return;
        }
        
        $this->handleSnapshot($args->getObject(), $args);
    }

    private function handleSnapshot(object $entity, $args): void
    {
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $snapshotAttributes = $property->getAttributes(Snapshot::class);
            
            if (empty($snapshotAttributes)) {
                continue;
            }
            
            /** @var Snapshot $snapshotAttr */
            $snapshotAttr = $snapshotAttributes[0]->newInstance();
            $property->setAccessible(true);
            $sourceEntity = $property->getValue($entity);
            
            if ($sourceEntity === null) {
                continue;
            }
            
            // 检查是否已经是快照
            if ($sourceEntity instanceof \Tourze\DoctrineSnapshotBundle\Entity\Snapshot) {
                continue;
            }
            
            // 获取目标快照属性名
            $targetPropertyName = $snapshotAttr->getTargetSnapshotProperty($property->getName());
            
            // 检查目标属性是否存在
            if (!$reflection->hasProperty($targetPropertyName)) {
                throw new \LogicException(sprintf(
                    'Target snapshot property "%s" does not exist in class "%s"',
                    $targetPropertyName,
                    $reflection->getName()
                ));
            }
            
            // 准备序列化上下文，使用 Snapshot 继承的 groups
            $context = array_merge(
                $snapshotAttr->context,
                ['groups' => $snapshotAttr->getGroups()]
            );
            
            // 创建快照
            $snapshot = $this->snapshotManager->create($sourceEntity, $context);
            
            // 设置快照到目标属性
            $targetProperty = $reflection->getProperty($targetPropertyName);
            $targetProperty->setAccessible(true);
            $targetProperty->setValue($entity, $snapshot);
            
            // 如果配置了级联，持久化快照
            if ($snapshotAttr->cascade) {
                $args->getObjectManager()->persist($snapshot);
            }
            
            // 清空源属性（因为它是 transient）
            $property->setValue($entity, null);
        }
    }
}
```

### 4.5 事件类

```php
<?php
// src/Event/PreSnapshotEvent.php
namespace Tourze\DoctrineSnapshotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PreSnapshotEvent extends Event
{
    public function __construct(
        private object $entity,
        private array $context
    ) {}

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
```

```php
<?php
// src/Event/PostSnapshotEvent.php
namespace Tourze\DoctrineSnapshotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;

class PostSnapshotEvent extends Event
{
    public function __construct(
        private object $entity,
        private Snapshot $snapshot
    ) {}

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getSnapshot(): Snapshot
    {
        return $this->snapshot;
    }
}
```

### 4.6 服务配置

```yaml
# Resources/config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Tourze\DoctrineSnapshotBundle\:
        resource: '../../src/'
        exclude:
            - '../../src/DependencyInjection/'
            - '../../src/Entity/'
            - '../../src/Event/'
            - '../../src/Attribute/'
            - '../../src/TourzeDoctrineSnapshotBundle.php'

    # 明确配置事件监听器
    Tourze\DoctrineSnapshotBundle\EventListener\SnapshotListener:
        tags:
            - { name: doctrine.event_listener, event: prePersist }
            - { name: doctrine.event_listener, event: preUpdate }

    # 公开 SnapshotManager 服务供外部使用
    Tourze\DoctrineSnapshotBundle\Service\SnapshotManager:
        public: true
```

## 5. 使用示例

### 5.1 基本实体定义

```php
<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['snapshot', 'product_detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['snapshot', 'product_detail'])]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['snapshot', 'product_detail'])]
    private string $price;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[Groups(['product_detail'])]  // 不包含在默认快照中
    private ?Category $category = null;

    // Getters and setters...
}
```

### 5.2 手动创建快照

```php
<?php
namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SnapshotManager $snapshotManager
    ) {}

    public function createOrder(Product $product, User $user): Order
    {
        // 手动创建产品快照，使用自定义 groups
        $snapshot = $this->snapshotManager->create($product, [
            'groups' => ['snapshot', 'order_context']
        ]);
        
        $order = new Order();
        $order->setUser($user);
        $order->setProductSnapshot($snapshot);
        
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        
        return $order;
    }
}
```

### 5.3 自动创建快照（使用 Snapshot Attribute）

```php
<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot as SnapshotEntity;

#[ORM\Entity]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * 临时属性，用于设置产品
     * 使用 Snapshot attribute，它继承自 Groups
     * 所以同时定义了序列化分组
     */
    #[Snapshot(
        groups: ['snapshot', 'order_product'],  // 序列化时使用的分组
        targetSnapshotProperty: 'productSnapshot',  // 可选，不设置会自动推断为 productSnapshot
        context: ['enable_max_depth' => true],
        cascade: true
    )]
    private ?Product $product = null;

    /**
     * 实际持久化的快照引用
     */
    #[ORM\ManyToOne(targetEntity: SnapshotEntity::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?SnapshotEntity $productSnapshot = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getProductSnapshot(): ?SnapshotEntity
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(?SnapshotEntity $snapshot): self
    {
        $this->productSnapshot = $snapshot;
        return $this;
    }

    // Other getters and setters...
}
```

使用自动快照：

```php
// 在控制器或服务中
$order = new Order();
$order->setUser($currentUser);
$order->setProduct($product); // 设置产品，会自动创建快照

$entityManager->persist($order);
$entityManager->flush(); // 触发 prePersist 事件，自动创建快照
```

### 5.4 自动推断属性名的例子

```php
<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot as SnapshotEntity;

#[ORM\Entity]
class Invoice
{
    /**
     * 不需要指定 targetSnapshotProperty
     * 会自动推断为 customerSnapshot
     */
    #[Snapshot(groups: 'invoice_snapshot')]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: SnapshotEntity::class, cascade: ['persist'])]
    private ?SnapshotEntity $customerSnapshot = null;

    /**
     * 多个快照属性
     */
    #[Snapshot(groups: 'invoice_snapshot')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: SnapshotEntity::class, cascade: ['persist'])]
    private ?SnapshotEntity $productSnapshot = null;

    // Setters and getters...
}
```

### 5.5 恢复快照数据

```php
// 获取订单的产品快照
$order = $orderRepository->find($orderId);
$snapshot = $order->getProductSnapshot();

// 将快照恢复为 Product 对象（非托管）
$productAtOrderTime = $snapshotManager->hydrate($snapshot);

// 使用恢复的产品数据
echo $productAtOrderTime->getName();
echo $productAtOrderTime->getPrice();

// 查看所有历史快照
$allSnapshots = $snapshotManager->findSnapshots($originalProduct, 10);
foreach ($allSnapshots as $historicalSnapshot) {
    echo $historicalSnapshot->getCreatedAt()->format('Y-m-d H:i:s');
    $historicalProduct = $snapshotManager->hydrate($historicalSnapshot);
    echo $historicalProduct->getPrice();
}
```

## 6. 环境变量配置

在 `.env` 文件中配置：

```bash
# 是否启用自动快照（默认 true）
SNAPSHOT_AUTO_ENABLED=true

# 默认最大序列化深度（默认 1）
SNAPSHOT_DEFAULT_MAX_DEPTH=1

# 全局排除的属性（逗号分隔）
SNAPSHOT_EXCLUDE_PROPERTIES=__initializer__,__cloner__,__isInitialized__
```

## 7. 测试策略

### 7.1 单元测试示例

```php
<?php
namespace Tourze\DoctrineSnapshotBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;

class SnapshotManagerTest extends TestCase
{
    public function testCreateSnapshot(): void
    {
        // 测试快照创建逻辑
    }
    
    public function testHydrateSnapshot(): void
    {
        // 测试快照恢复逻辑
    }
    
    public function testSnapshotWithGroups(): void
    {
        // 测试使用 Groups 的快照
    }
}
```

### 7.2 功能测试示例

```php
<?php
namespace Tourze\DoctrineSnapshotBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SnapshotListenerTest extends KernelTestCase
{
    public function testAutomaticSnapshotCreation(): void
    {
        // 测试自动快照创建
    }
    
    public function testSnapshotAttributeInheritance(): void
    {
        // 测试 Snapshot 继承 Groups 的功能
    }
}
```

## 8. 性能优化建议

1. **批量操作优化**：对于大量实体的快照创建，考虑批处理
2. **序列化优化**：充分利用 Groups 功能，避免序列化不必要的数据
3. **查询优化**：利用已创建的索引进行高效查询
4. **环境变量调优**：根据实际需求调整 `SNAPSHOT_DEFAULT_MAX_DEPTH`

## 9. 注意事项与最佳实践

### 9.1 注意事项
- 自动快照在 `prePersist` 和 `preUpdate` 事件中触发，会有轻微性能开销
- 快照数据是只读的，不应该被修改
- 大型对象图的快照可能占用大量存储空间

### 9.2 最佳实践
- 充分利用 Symfony Serializer 的 Groups 功能控制快照内容
- 合理使用 `Snapshot` attribute 的 groups 参数
- 定期清理旧快照数据
- 对于关键业务数据，建议同时使用手动和自动快照

## 10. 总结

DoctrineSnapshotBundle 提供了一个强大而灵活的实体快照解决方案。通过让 `Snapshot` attribute 继承自 Symfony 的 `Groups`，我们既保持了与 Symfony Serializer 的完美集成，又提供了快照的特定功能。使用环境变量进行配置使得 Bundle 更加灵活，适应不同的部署环境。

本文档为 Claude Code 提供了完整的开发指导，包含了所有必要的实现细节和示例代码。