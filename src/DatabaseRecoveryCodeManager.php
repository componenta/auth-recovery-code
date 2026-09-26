<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

use Componenta\Identity\UuidInterface;
use Cycle\Database\DatabaseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final readonly class DatabaseRecoveryCodeManager implements
    RecoveryCodeManagerInterface
{
    private const int MIN_CODES = 1;
    private const int MAX_CODES = 50;
    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private DatabaseInterface $database,
        private ClockInterface $clock,
        private string $table = 'auth_recovery_codes',
    ) {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $this->table) !== 1) {
            throw new \InvalidArgumentException(
                'Recovery-code table name is invalid.',
            );
        }
    }

    #[\Override]
    public function regenerate(
        UuidInterface $subjectId,
        int $count = 10,
    ): RecoveryCodeBatch {
        if ($count < self::MIN_CODES || $count > self::MAX_CODES) {
            throw new \InvalidArgumentException(
                'Recovery-code count is out of bounds.',
            );
        }

        $codes = [];

        for ($i = 0; $i < $count; ++$i) {
            $codes[] = RecoveryCode::generate();
        }

        $subject = $subjectId->toString();
        $batchId = bin2hex(random_bytes(16));
        $createdAt = $this->format($this->now());

        $this->database->transaction(function () use (
            $subject,
            $batchId,
            $createdAt,
            $codes,
        ): void {
            $this->database->delete($this->table)
                ->where('subject_uuid', $subject)
                ->run();

            foreach ($codes as $code) {
                $this->database->insert($this->table)->values([
                    'subject_uuid' => $subject,
                    'batch_id' => $batchId,
                    'code_hash' => self::hash($code),
                    'created_at' => $createdAt,
                    'used_at' => null,
                ])->run();
            }
        });

        /** @var non-empty-list<RecoveryCode> $codes */
        return new RecoveryCodeBatch($codes);
    }

    #[\Override]
    public function consume(
        UuidInterface $subjectId,
        #[\SensitiveParameter]
        RecoveryCode $code,
    ): bool {
        $now = $this->format($this->now());

        return $this->database->update($this->table)
            ->where('subject_uuid', $subjectId->toString())
            ->where('code_hash', self::hash($code))
            ->where('used_at', null)
            ->values(['used_at' => $now])
            ->run() === 1;
    }

    #[\Override]
    public function remaining(UuidInterface $subjectId): int
    {
        return $this->database->select()
            ->from($this->table)
            ->where('subject_uuid', $subjectId->toString())
            ->where('used_at', null)
            ->count();
    }

    #[\Override]
    public function revokeAll(UuidInterface $subjectId): void
    {
        $this->database->delete($this->table)
            ->where('subject_uuid', $subjectId->toString())
            ->run();
    }

    private static function hash(
        #[\SensitiveParameter]
        RecoveryCode $code,
    ): string {
        return hash(
            'sha256',
            "componenta-auth-recovery-code-v1\0" . $code->toString(),
        );
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))
            ->format(self::DATE_FORMAT);
    }
}
