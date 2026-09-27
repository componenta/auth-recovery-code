<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode\Tests;

use Componenta\Auth\RecoveryCode\DatabaseRecoveryCodeManager;
use Componenta\Auth\RecoveryCode\Tests\Support\SqliteDatabaseFixture;
use Componenta\Clock\FrozenClock;
use Componenta\Identity\UuidFactory;
use Cycle\Database\Database;
use Cycle\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

final class StorageConsistencyTest extends TestCase
{
    public function testRemainingCountDoesNotUseAStaleReplica(): void
    {
        $primary = $this->database();
        $replica = $this->database();
        $clock = new FrozenClock('2030-01-01T00:00:00+00:00', 'UTC');
        $subject = (new UuidFactory())->generate();
        $writer = new DatabaseRecoveryCodeManager($primary, $clock);
        $writer->regenerate($subject, 3);
        $split = new Database('split', '', $primary->getDriver(DatabaseInterface::WRITE), $replica->getDriver(DatabaseInterface::READ));
        self::assertSame(3, (new DatabaseRecoveryCodeManager($split, $clock))->remaining($subject));
    }

    public function testSubjectMutexRespectsPrefixAndSurvivesRegenerationAndRevocation(): void
    {
        $database = $this->database();
        $schema = file_get_contents(dirname(__DIR__) . '/resources/schema/sqlite.sql');
        self::assertIsString($schema);
        foreach (array_filter(array_map('trim', explode(';', str_replace('auth_', 'tenant_auth_', $schema)))) as $sql) {
            $database->execute($sql);
        }
        $prefixed = $database->withPrefix('tenant_');
        $manager = new DatabaseRecoveryCodeManager($prefixed, new FrozenClock('2030-01-01T00:00:00+00:00', 'UTC'));
        $subject = (new UuidFactory())->generate();
        $first = $manager->regenerate($subject, 3);
        $second = $manager->regenerate($subject, 3);
        self::assertFalse($manager->consume($subject, $first->codes[0]));
        self::assertTrue($manager->consume($subject, $second->codes[0]));
        $manager->revokeAll($subject);
        self::assertSame(0, $manager->remaining($subject));
        $lock = $prefixed->select()->from('auth_recovery_code_subject_locks')->where('subject_uuid', $subject->toString())->run()->fetch();
        self::assertSame(3, (int) $lock['lock_version']);
        self::assertSame(0, $database->select()->from('auth_recovery_codes')->count());
    }

    private function database(): DatabaseInterface
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        return SqliteDatabaseFixture::create();
    }
}
