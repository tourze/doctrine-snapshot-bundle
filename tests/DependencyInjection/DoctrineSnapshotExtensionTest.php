<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\DependencyInjection\DoctrineSnapshotExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineSnapshotExtension::class)]
final class DoctrineSnapshotExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
