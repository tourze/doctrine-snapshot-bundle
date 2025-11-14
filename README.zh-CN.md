# Doctrine Snapshot Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)
[![License](https://img.shields.io/packagist/l/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)

一个为 Doctrine 实体提供快照功能的 Symfony Bundle。此包允许您在任何时间点创建、存储和恢复实体的完整快照，非常适合审计跟踪、版本控制和数据恢复场景。

## 功能特性

- **实体快照**：为任何 Doctrine 实体创建完整快照
- **数据完整性**：内置校验和确保数据一致性
- **灵活存储**：存储带有自定义元数据的快照
- **事件系统**：可扩展的事件驱动架构
- **序列化集成**：与 Symfony 序列化组件无缝集成
- **查询支持**：高效的快照检索仓库方法
- **配置选项**：可自定义深度、排除属性等

## 安装

```bash
composer require tourze/doctrine-snapshot-bundle
```

## 配置

在您的 Symfony 应用中注册此 Bundle：

```php
// config/bundles.php
return [
    // ...
    Tourze\DoctrineSnapshotBundle\DoctrineSnapshotBundle::class => ['all' => true],
];
```

## 环境变量

使用环境变量配置 Bundle 行为：

```yaml
# .env
# 启用/禁用自动快照创建
SNAPSHOT_AUTO_ENABLED=true

# 序列化的默认最大深度
SNAPSHOT_DEFAULT_MAX_DEPTH=1

# 从快照中排除的属性
SNAPSHOT_EXCLUDE_PROPERTIES="__initializer__,__cloner__,__isInitialized__"
```

## 基础用法

### 创建快照

```php
<?php

use Tourze\DoctrineSnapshotBundle\Service\SnapshotManager;
use App\Entity\User;

class UserService
{
    public function __construct(
        private SnapshotManager $snapshotManager
    ) {}

    public function createUserSnapshot(User $user): void
    {
        // 使用默认设置创建快照
        $snapshot = $this->snapshotManager->create($user);

        // 快照会自动持久化到数据库
        // 您可以访问快照数据：
        $snapshotData = $snapshot->getData();
        $checksum = $snapshot->getChecksum();
        $createdAt = $snapshot->getCreatedAt();
    }
}
```

### 查找快照

```php
<?php

// 查找实体的最新快照
$latestSnapshot = $this->snapshotManager->findLatestSnapshot($user);

if ($latestSnapshot) {
    $snapshotData = $latestSnapshot->getData();
    $createdAt = $latestSnapshot->getCreatedAt();
}

// 查找实体的所有快照
$snapshots = $this->snapshotManager->findSnapshots($user, 10); // 限制为10个

foreach ($snapshots as $snapshot) {
    echo "快照时间：" . $snapshot->getCreatedAt()->format('Y-m-d H:i:s');
    echo "校验和：" . $snapshot->getChecksum();
}
```

### 从快照恢复实体

```php
<?php

// 从快照恢复实体
$restoredUser = $this->snapshotManager->hydrate($latestSnapshot);

// 恢复的实体将拥有快照中的所有属性
echo $restoredUser->getName();
echo $restoredUser->getEmail();
```

## 高级用法

### 自定义序列化上下文

```php
<?php

// 使用自定义序列化上下文创建快照
$snapshot = $this->snapshotManager->create($user, [
    'groups' => ['snapshot', 'sensitive'],
    'max_depth' => 2,
    AbstractNormalizer::IGNORED_ATTRIBUTES => ['password', 'secretKey']
]);
```

### 使用快照属性

```php
<?php

use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;

class User
{
    #[Snapshot(['snapshot'])]
    public string $name;

    #[Snapshot(['admin_only'], targetSnapshotProperty: 'adminSnapshot')]
    public string $role;

    #[Snapshot(['basic_info'], cascade: false)]
    public string $email;
}
```

### 事件系统

```php
<?php

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\DoctrineSnapshotBundle\Event\PreSnapshotEvent;
use Tourze\DoctrineSnapshotBundle\Event\PostSnapshotEvent;

class SnapshotSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreSnapshotEvent::class => 'onPreSnapshot',
            PostSnapshotEvent::class => 'onPostSnapshot',
        ];
    }

    public function onPreSnapshot(PreSnapshotEvent $event): void
    {
        $entity = $event->getEntity();
        $context = $event->getContext();

        // 在快照前修改上下文或验证实体
        $context['additional_info'] = '创建者：' . $this->getCurrentUser();
        $event->setContext($context);
    }

    public function onPostSnapshot(PostSnapshotEvent $event): void
    {
        $entity = $event->getEntity();
        $snapshot = $event->getSnapshot();

        // 记录或处理创建的快照
        $this->logger->info('快照已创建', [
            'entity' => get_class($entity),
            'snapshot_id' => $snapshot->getId(),
            'checksum' => $snapshot->getChecksum()
        ]);
    }
}
```

## 数据库架构

Bundle 会创建 `doctrine_snapshot` 表，结构如下：

```sql
CREATE TABLE doctrine_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_class VARCHAR(255) NOT NULL COMMENT '源实体类名',
    source_id VARCHAR(255) NOT NULL COMMENT '源实体ID',
    data JSON NOT NULL COMMENT '快照数据',
    metadata JSON NULL COMMENT '元数据',
    version INT NOT NULL DEFAULT 1 COMMENT '版本号',
    create_time DATETIME NOT NULL COMMENT '创建时间',
    checksum VARCHAR(32) NOT NULL COMMENT '数据校验和',
    INDEX doctrine_snapshot_idx_source (source_class, source_id)
);
```

## 测试

运行测试套件：

```bash
php vendor/bin/phpunit
```

## 贡献

1. Fork 仓库
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 开启 Pull Request

## 许可证

此 Bundle 在 MIT 许可证下发布。详见 [LICENSE](LICENSE) 文件。

## 支持

- 📖 [文档](docs/)
- 🐛 [问题追踪](https://github.com/tourze/php-monorepo/issues)
- 💬 [讨论](https://github.com/tourze/php-monorepo/discussions)

## 相关包

- [tourze/doctrine-indexed-bundle](https://github.com/tourze/php-monorepo/tree/main/packages/doctrine-indexed-bundle) - 数据库索引工具
- [tourze/symfony-dependency-service-loader](https://github.com/tourze/php-monorepo/tree/main/packages/symfony-dependency-service-loader) - 增强的服务加载

---

**维护者**：[Tourze](https://github.com/tourze)
**最后更新**：2024-11-14
