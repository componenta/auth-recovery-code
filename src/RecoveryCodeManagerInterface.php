<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

use Componenta\Identity\UuidInterface;

interface RecoveryCodeManagerInterface
{
    public function regenerate(
        UuidInterface $subjectId,
        int $count = 10,
    ): RecoveryCodeBatch;

    public function consume(
        UuidInterface $subjectId,
        #[\SensitiveParameter]
        RecoveryCode $code,
    ): bool;

    public function remaining(UuidInterface $subjectId): int;

    public function revokeAll(UuidInterface $subjectId): void;
}
