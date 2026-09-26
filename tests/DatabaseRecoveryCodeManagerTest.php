<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode\Tests;

use Componenta\Auth\RecoveryCode\DatabaseRecoveryCodeManager;
use Componenta\Auth\RecoveryCode\Tests\Support\SqliteDatabaseFixture;
use Componenta\Clock\FrozenClock;
use Componenta\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class DatabaseRecoveryCodeManagerTest extends TestCase
{
    public function testCodesAreHashedSingleUseAndRegenerationInvalidatesOldBatch(): void
    {
        self::requireSqlite();
        $database = SqliteDatabaseFixture::create();
        $manager = new DatabaseRecoveryCodeManager(
            $database,
            new FrozenClock('2030-01-01T00:00:00+00:00', 'UTC'),
        );
        $subject = Uuid::fromString(
            '018f6d5d-3f7a-7a9b-8c2f-123456789abc',
        );

        $first = $manager->regenerate($subject, 3);
        self::assertSame(3, $manager->remaining($subject));

        $row = $database->select()
            ->from('auth_recovery_codes')
            ->run()
            ->fetch();
        self::assertIsArray($row);
        $serialized = json_encode($row, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(
            $first->codes[0]->toString(),
            $serialized,
        );

        self::assertTrue(
            $manager->consume($subject, $first->codes[0]),
        );
        self::assertFalse(
            $manager->consume($subject, $first->codes[0]),
        );
        self::assertSame(2, $manager->remaining($subject));

        $second = $manager->regenerate($subject, 2);
        self::assertSame(2, $manager->remaining($subject));
        self::assertFalse(
            $manager->consume($subject, $first->codes[1]),
        );
        self::assertTrue(
            $manager->consume($subject, $second->codes[0]),
        );
    }

    private static function requireSqlite(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
    }
}
