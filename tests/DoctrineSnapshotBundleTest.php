<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineSnapshotBundle\DoctrineSnapshotBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineSnapshotBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineSnapshotBundleTest extends AbstractBundleTestCase
{
}
