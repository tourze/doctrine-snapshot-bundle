<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class DoctrineSnapshotExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function getAlias(): string
    {
        return 'doctrine_snapshot';
    }
}
