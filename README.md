# Doctrine Snapshot Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)
[![License](https://img.shields.io/packagist/l/tourze/doctrine-snapshot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-snapshot-bundle)

A Symfony bundle that provides snapshot functionality for Doctrine entities. This bundle allows you to create, store, and restore snapshots of your entities at any point in time, perfect for audit trails, versioning, and data recovery scenarios.

## Features

- **Entity Snapshots**: Create complete snapshots of any Doctrine entity
- **Data Integrity**: Built-in checksum validation ensures data consistency
- **Flexible Storage**: Store snapshots with customizable metadata
- **Event System**: Extensible event-driven architecture
- **Serializer Integration**: Seamless integration with Symfony Serializer component
- **Query Support**: Efficient repository methods for snapshot retrieval
- **Configuration Options**: Customizable depth, exclusion properties, and more

## Installation

```bash
composer require tourze/doctrine-snapshot-bundle
```

## Configuration

Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    Tourze\DoctrineSnapshotBundle\DoctrineSnapshotBundle::class => ['all' => true],
];
```

## Environment Variables

Configure the bundle behavior using environment variables:

```yaml
# .env
# Enable/disable automatic snapshot creation
SNAPSHOT_AUTO_ENABLED=true

# Default maximum depth for serialization
SNAPSHOT_DEFAULT_MAX_DEPTH=1

# Properties to exclude from snapshots
SNAPSHOT_EXCLUDE_PROPERTIES="__initializer__,__cloner__,__isInitialized__"
```

## Basic Usage

### Creating Snapshots

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
        // Create a snapshot with default settings
        $snapshot = $this->snapshotManager->create($user);

        // The snapshot is automatically persisted to the database
        // You can access snapshot data:
        $snapshotData = $snapshot->getData();
        $checksum = $snapshot->getChecksum();
        $createdAt = $snapshot->getCreatedAt();
    }
}
```

### Finding Snapshots

```php
<?php

// Find the latest snapshot for an entity
$latestSnapshot = $this->snapshotManager->findLatestSnapshot($user);

if ($latestSnapshot) {
    $snapshotData = $latestSnapshot->getData();
    $createdAt = $latestSnapshot->getCreatedAt();
}

// Find all snapshots for an entity
$snapshots = $this->snapshotManager->findSnapshots($user, 10); // limit to 10

foreach ($snapshots as $snapshot) {
    echo "Snapshot from " . $snapshot->getCreatedAt()->format('Y-m-d H:i:s');
    echo "Checksum: " . $snapshot->getChecksum();
}
```

### Restoring Entities from Snapshots

```php
<?php

// Restore an entity from a snapshot
$restoredUser = $this->snapshotManager->hydrate($latestSnapshot);

// The restored entity will have all the properties from the snapshot
echo $restoredUser->getName();
echo $restoredUser->getEmail();
```

## Advanced Usage

### Custom Serialization Context

```php
<?php

// Create snapshot with custom serialization context
$snapshot = $this->snapshotManager->create($user, [
    'groups' => ['snapshot', 'sensitive'],
    'max_depth' => 2,
    AbstractNormalizer::IGNORED_ATTRIBUTES => ['password', 'secretKey']
]);
```

### Using the Snapshot Attribute

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

### Event System

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

        // Modify context or validate entity before snapshot
        $context['additional_info'] = 'Created by: ' . $this->getCurrentUser();
        $event->setContext($context);
    }

    public function onPostSnapshot(PostSnapshotEvent $event): void
    {
        $entity = $event->getEntity();
        $snapshot = $event->getSnapshot();

        // Log or process the created snapshot
        $this->logger->info('Snapshot created', [
            'entity' => get_class($entity),
            'snapshot_id' => $snapshot->getId(),
            'checksum' => $snapshot->getChecksum()
        ]);
    }
}
```

## Database Schema

The bundle creates a `doctrine_snapshot` table with the following structure:

```sql
CREATE TABLE doctrine_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_class VARCHAR(255) NOT NULL COMMENT 'Source entity class name',
    source_id VARCHAR(255) NOT NULL COMMENT 'Source entity ID',
    data JSON NOT NULL COMMENT 'Snapshot data',
    metadata JSON NULL COMMENT 'Metadata',
    version INT NOT NULL DEFAULT 1 COMMENT 'Version number',
    create_time DATETIME NOT NULL COMMENT 'Creation time',
    checksum VARCHAR(32) NOT NULL COMMENT 'Data checksum',
    INDEX doctrine_snapshot_idx_source (source_class, source_id)
);
```

## Testing

Run the test suite:

```bash
php vendor/bin/phpunit
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

- 📖 [Documentation](docs/)
- 🐛 [Issue Tracker](https://github.com/tourze/php-monorepo/issues)
- 💬 [Discussions](https://github.com/tourze/php-monorepo/discussions)

## Related Packages

- [tourze/doctrine-indexed-bundle](https://github.com/tourze/php-monorepo/tree/main/packages/doctrine-indexed-bundle) - Database indexing utilities
- [tourze/symfony-dependency-service-loader](https://github.com/tourze/php-monorepo/tree/main/packages/symfony-dependency-service-loader) - Enhanced service loading

---

**Maintained by**: [Tourze](https://github.com/tourze)
**Last Updated**: 2024-11-14