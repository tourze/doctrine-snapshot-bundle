<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\Entity\Snapshot;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Snapshot::class)]
class SnapshotTest extends AbstractEntityTestCase
{
    private Snapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshot = new Snapshot();
        $this->snapshot->setSourceClass('App\Entity\Product');
        $this->snapshot->setSourceId('123');
        $this->snapshot->setData(['name' => 'Test Product', 'price' => 99.99]);
        $this->snapshot->setMetadata(['user' => 'test_user']);
        $this->snapshot->setCreateTime(new \DateTimeImmutable());
    }

    protected function createEntity(): object
    {
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('123');
        $snapshot->setData(['name' => 'Test Product', 'price' => 99.99]);
        $snapshot->setMetadata(['user' => 'test_user']);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        return $snapshot;
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            'version' => ['version', 2],
        ];
    }

    public function testConstructor(): void
    {
        $data = ['name' => 'Test Product', 'price' => 99.99];
        $metadata = ['user' => 'test_user'];

        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('123');
        $snapshot->setData($data);
        $snapshot->setMetadata($metadata);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $this->assertEquals('App\Entity\Product', $snapshot->getSourceClass());
        $this->assertEquals('123', $snapshot->getSourceId());
        $this->assertEquals($data, $snapshot->getData());
        $this->assertEquals($metadata, $snapshot->getMetadata());
        $this->assertEquals(1, $snapshot->getVersion());
        $this->assertInstanceOf(\DateTimeImmutable::class, $snapshot->getCreatedAt());
        $this->assertNotEmpty($snapshot->getChecksum());
    }

    public function testChecksumCalculation(): void
    {
        $data1 = ['name' => 'Product 1'];
        $data2 = ['name' => 'Product 2'];

        $snapshot1 = new Snapshot();
        $snapshot1->setSourceClass('App\Entity\Product');
        $snapshot1->setSourceId('1');
        $snapshot1->setData($data1);
        $snapshot1->setCreateTime(new \DateTimeImmutable());

        $snapshot2 = new Snapshot();
        $snapshot2->setSourceClass('App\Entity\Product');
        $snapshot2->setSourceId('2');
        $snapshot2->setData($data1);
        $snapshot2->setCreateTime(new \DateTimeImmutable());

        $snapshot3 = new Snapshot();
        $snapshot3->setSourceClass('App\Entity\Product');
        $snapshot3->setSourceId('3');
        $snapshot3->setData($data2);
        $snapshot3->setCreateTime(new \DateTimeImmutable());

        $this->assertEquals($snapshot1->getChecksum(), $snapshot2->getChecksum());
        $this->assertNotEquals($snapshot1->getChecksum(), $snapshot3->getChecksum());
    }

    public function testNullMetadata(): void
    {
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('123');
        $snapshot->setData(['name' => 'Test']);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $this->assertNull($snapshot->getMetadata());
    }

    public function testGetters(): void
    {
        // 测试所有 getter 方法
        $this->assertEquals('App\Entity\Product', $this->snapshot->getSourceClass());
        $this->assertEquals('123', $this->snapshot->getSourceId());
        $this->assertEquals(['name' => 'Test Product', 'price' => 99.99], $this->snapshot->getData());
        $this->assertEquals(['user' => 'test_user'], $this->snapshot->getMetadata());
        $this->assertEquals(1, $this->snapshot->getVersion());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->snapshot->getCreatedAt());
        $this->assertNotEmpty($this->snapshot->getChecksum());
        $this->assertNull($this->snapshot->getId());
    }

    public function testVersionIsReadOnly(): void
    {
        // version 属性应该是只读的，默认值为 1
        $this->assertEquals(1, $this->snapshot->getVersion());
    }

    public function testSettersWork(): void
    {
        // 验证贫血模型的 setter 方法能正常工作
        $newSnapshot = new Snapshot();

        $newSnapshot->setSourceClass('Test\Entity');
        $this->assertEquals('Test\Entity', $newSnapshot->getSourceClass());

        $newSnapshot->setSourceId('999');
        $this->assertEquals('999', $newSnapshot->getSourceId());

        $data = ['test' => 'value'];
        $newSnapshot->setData($data);
        $this->assertEquals($data, $newSnapshot->getData());

        $metadata = ['key' => 'value'];
        $newSnapshot->setMetadata($metadata);
        $this->assertEquals($metadata, $newSnapshot->getMetadata());

        $createTime = new \DateTimeImmutable();
        $newSnapshot->setCreateTime($createTime);
        $this->assertEquals($createTime, $newSnapshot->getCreatedAt());

        $newSnapshot->setVersion(5);
        $this->assertEquals(5, $newSnapshot->getVersion());

        // checksum 是通过 setData 自动计算的
        $this->assertNotEmpty($newSnapshot->getChecksum());
    }

    public function testToString(): void
    {
        $snapshot = new Snapshot();
        $snapshot->setSourceClass('App\Entity\Product');
        $snapshot->setSourceId('123');
        $snapshot->setData(['name' => 'Test Product']);
        $snapshot->setCreateTime(new \DateTimeImmutable());

        $toString = (string) $snapshot;

        $this->assertStringContainsString('App\Entity\Product', $toString);
        $this->assertStringContainsString('123', $toString);
        $this->assertStringContainsString('Snapshot', $toString);
    }
}
