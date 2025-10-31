<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSnapshotBundle\Exception\InvalidSnapshotTargetException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidSnapshotTargetException::class)]
class InvalidSnapshotTargetExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new InvalidSnapshotTargetException('Custom error message');

        $this->assertEquals('Custom error message', $exception->getMessage());
        $this->assertInstanceOf(\LogicException::class, $exception);
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidSnapshotTargetException('Error', 123, $previous);

        $this->assertEquals('Error', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
