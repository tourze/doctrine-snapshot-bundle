<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Attribute;

use Symfony\Component\Serializer\Attribute\Groups;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class Snapshot extends Groups
{
    public const DEFAULT_GROUP = 'snapshot';

    public string $targetSnapshotProperty;

    /** @var array<string, mixed> */
    public array $context;

    public bool $cascade;

    /**
     * @param array<string>|string $groups
     * @param array<string, mixed> $context
     */
    public function __construct(
        array|string $groups = self::DEFAULT_GROUP,
        string $targetSnapshotProperty = '',
        array $context = [],
        bool $cascade = true,
    ) {
        parent::__construct($groups);

        $this->targetSnapshotProperty = $targetSnapshotProperty;
        $this->context = $context;
        $this->cascade = $cascade;
    }

    public function getTargetSnapshotProperty(string $sourcePropertyName): string
    {
        if ('' !== $this->targetSnapshotProperty) {
            return $this->targetSnapshotProperty;
        }

        return $sourcePropertyName . 'Snapshot';
    }
}
