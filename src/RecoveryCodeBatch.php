<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

final readonly class RecoveryCodeBatch implements \JsonSerializable
{
    /** @var non-empty-list<RecoveryCode> */
    public array $codes;

    /** @param non-empty-list<RecoveryCode> $codes */
    public function __construct(array $codes)
    {
        $this->codes = $codes;
    }

    /** @return array{codes: string} */
    public function __debugInfo(): array
    {
        return ['codes' => '[REDACTED]'];
    }

    /** @return array{codes: string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
