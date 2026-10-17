<?php

namespace Nails\FindReplace\Factory;

class Run
{
    const SOURCE_ADMIN = 'ADMIN';

    const SOURCE_CLI = 'CLI';

    public function __construct(
        private readonly string $id,
        private readonly string $source,
        private readonly ?int $searchId = null,
        private readonly ?int $userId = null,
    ) {
        if ($source !== self::SOURCE_ADMIN && $source !== self::SOURCE_CLI) {
            throw new \InvalidArgumentException('Replace source must be ADMIN or CLI');
        }
    }

    public static function cli(): self
    {
        return new self(self::id(), self::SOURCE_CLI);
    }

    public static function id(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4)
            . '-' . substr($hex, 20);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSearchId(): ?int
    {
        return $this->searchId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
