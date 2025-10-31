<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\Exception\SnapshotException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SnapshotException::class)]
class SnapshotExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new SnapshotException('Snapshot error occurred');

        $this->assertEquals('Snapshot error occurred', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new SnapshotException('Error', 500, $previous);

        $this->assertEquals('Error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
