<?php

declare(strict_types=1);

namespace Tourze\DoctrineSnapshotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnapshotBundle\Repository\SnapshotRepository;

#[ORM\Entity(repositoryClass: SnapshotRepository::class)]
#[ORM\Table(name: 'doctrine_snapshot', options: ['comment' => '实体快照存储表'])]
#[ORM\Index(name: 'doctrine_snapshot_idx_source', columns: ['source_class', 'source_id'])]
class Snapshot implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '源实体类名'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $sourceClass;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '源实体ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $sourceId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '快照数据'])]
    #[Assert\NotNull]
    private array $data;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '版本号'])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '创建时间'])]
    #[IndexColumn]
    private \DateTimeImmutable $createTime;

    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => '数据校验和'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    private string $checksum;

    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calculateChecksum(array $data): string
    {
        return md5((string) json_encode($data));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceClass(): string
    {
        return $this->sourceClass;
    }

    public function setSourceClass(string $sourceClass): void
    {
        $this->sourceClass = $sourceClass;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function setSourceId(string $sourceId): void
    {
        $this->sourceId = $sourceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
        $this->checksum = $this->calculateChecksum($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * 设置版本号（仅供测试使用）
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createTime;
    }

    public function setCreateTime(\DateTimeImmutable $createTime): void
    {
        $this->createTime = $createTime;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function __toString(): string
    {
        return sprintf(
            'Snapshot[%s#%s]@%s',
            $this->sourceClass,
            $this->sourceId,
            $this->createTime->format('Y-m-d H:i:s')
        );
    }
}
