<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

final readonly class RecoveryCode implements \Stringable, \JsonSerializable
{
    private const string PATTERN =
        '/\A[a-f0-9]{4}(?:-[a-f0-9]{4}){7}\z/D';

    private function __construct(
        #[\SensitiveParameter]
        private string $value,
    ) {
        if (preg_match(self::PATTERN, $this->value) !== 1) {
            throw new \InvalidArgumentException(
                'Recovery code is invalid.',
            );
        }
    }

    public static function generate(): self
    {
        return self::fromBytes(random_bytes(16));
    }

    public static function fromString(
        #[\SensitiveParameter]
        string $value,
    ): self {
        return new self(strtolower($value));
    }

    public static function fromBytes(
        #[\SensitiveParameter]
        string $bytes,
    ): self {
        if (strlen($bytes) !== 16) {
            throw new \InvalidArgumentException(
                'Recovery code source must contain exactly 16 bytes.',
            );
        }

        return new self(
            implode('-', str_split(bin2hex($bytes), 4)),
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /** @return array{code: string} */
    public function __debugInfo(): array
    {
        return ['code' => '[REDACTED]'];
    }

    /** @return array{code: string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
