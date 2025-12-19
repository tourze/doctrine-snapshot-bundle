<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;
use Tourze\DoctrineSnapshotBundle\Attribute\Snapshot;

/**
 * @internal
 */
#[CoversClass(Snapshot::class)]
class SnapshotTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $snapshot = new Snapshot();

        $this->assertEquals(['snapshot'], $snapshot->groups);
        $this->assertEquals('', $snapshot->targetSnapshotProperty);
        $this->assertEquals([], $snapshot->context);
        $this->assertTrue($snapshot->cascade);
    }

    public function testCustomConstruction(): void
    {
        $snapshot = new Snapshot(
            groups: ['custom_group', 'another_group'],
            targetSnapshotProperty: 'customSnapshot',
            context: ['enable_max_depth' => true],
            cascade: false
        );

        $this->assertEquals(['custom_group', 'another_group'], $snapshot->groups);
        $this->assertEquals('customSnapshot', $snapshot->targetSnapshotProperty);
        $this->assertEquals(['enable_max_depth' => true], $snapshot->context);
        $this->assertFalse($snapshot->cascade);
    }

    public function testGetTargetSnapshotPropertyWithExplicitValue(): void
    {
        $snapshot = new Snapshot(targetSnapshotProperty: 'explicitSnapshot');

        $this->assertEquals('explicitSnapshot', $snapshot->getTargetSnapshotProperty('product'));
    }

    public function testGetTargetSnapshotPropertyAutoGeneration(): void
    {
        $snapshot = new Snapshot();

        $this->assertEquals('productSnapshot', $snapshot->getTargetSnapshotProperty('product'));
        $this->assertEquals('customerSnapshot', $snapshot->getTargetSnapshotProperty('customer'));
        $this->assertEquals('orderItemSnapshot', $snapshot->getTargetSnapshotProperty('orderItem'));
    }

    public function testInheritanceFromGroups(): void
    {
        $snapshot = new Snapshot(['group1', 'group2']);

        $this->assertInstanceOf(Groups::class, $snapshot);
        $this->assertEquals(['group1', 'group2'], $snapshot->groups);
    }

    public function testSingleGroupString(): void
    {
        $snapshot = new Snapshot('single_group');

        $this->assertEquals(['single_group'], $snapshot->groups);
    }
}
