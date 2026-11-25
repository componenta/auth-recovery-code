<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode\Tests;

use Componenta\Auth\AuthenticationAdmission;
use Componenta\Auth\AuthenticationEvidence;
use Componenta\Auth\AuthenticationGuardInterface;
use Componenta\Auth\IdentityProviderInterface;
use Componenta\Auth\Session\AssuranceRequirement;
use Componenta\Auth\Session\AuthSession;
use Componenta\Auth\Session\AuthSessionRegistryInterface;
use Componenta\Auth\Session\Http\Csrf\AuthSessionCsrfTokenManager;
use Componenta\Auth\Session\Http\FactorManagementGuard;
use Componenta\Clock\FrozenClock;
use Componenta\Identity\IdentityInterface;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use Componenta\Auth\RecoveryCode\DatabaseRecoveryCodeManager;
use Componenta\Auth\RecoveryCode\RecoveryCode;
use Componenta\Auth\RecoveryCode\RecoveryCodeBatch;
use Componenta\Auth\RecoveryCode\RecoveryCodeManagerInterface;
use Componenta\Auth\RecoveryCode\RecoveryCodeRegenerateHandler;
use Componenta\Auth\RecoveryCode\Tests\Support\SqliteDatabaseFixture;

final class RegenerationAuthorizationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function responseStreams(): iterable
    {
        foreach (['complete', 'partial', 'stalled', 'throwing'] as $mode) {
            yield $mode => [$mode];
        }
    }

    #[DataProvider('responseStreams')]
    public function testRegenerationReturnsCompleteCodesOrPropagatesBodyFailure(string $mode): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        [$gate, $request, , $identity] = $this->context('allowed');
        $manager = new DatabaseRecoveryCodeManager(SqliteDatabaseFixture::create(), new FrozenClock('2030-01-01T00:00:00Z', 'UTC'));
        $sink = new class {
            public string $body = '';
            public int $calls = 0;
        };
        $failure = new \RuntimeException('Synthetic stream failure.');
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $bytes) use ($sink, $mode, $failure): int {
            if (++$sink->calls > 1) {
                if ($mode === 'stalled') {
                    return 0;
                }
                if ($mode === 'throwing') {
                    throw $failure;
                }
            }

            $written = $mode === 'complete' ? strlen($bytes) : min(7, strlen($bytes));
            $sink->body .= substr($bytes, 0, $written);

            return $written;
        });
        $stream->method('__toString')->willReturnCallback(static fn(): string => $sink->body);
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $responses->method('createResponse')->willReturn(new Response(200, [], $stream));
        $failureObserved = null;
        $response = null;

        try {
            $response = (new RecoveryCodeRegenerateHandler($manager, $responses, $gate, count: 2))->handle($request);
        } catch (\RuntimeException $exception) {
            $failureObserved = $exception;
        }

        if (in_array($mode, ['stalled', 'throwing'], true)) {
            self::assertInstanceOf(\RuntimeException::class, $failureObserved, 'Incomplete codes must not be returned as success.');
            self::assertNull($response);
            if ($mode === 'throwing') {
                self::assertSame($failure, $failureObserved);
            }

            return;
        }

        self::assertNull($failureObserved);
        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertJson((string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['codes']);
        foreach ($body['codes'] as $code) {
            self::assertTrue($manager->consume($identity->uuid, RecoveryCode::fromString($code)));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function responsePreparationFailures(): iterable
    {
        yield 'response factory' => ['factory'];
        yield 'response headers' => ['headers'];
    }

    #[DataProvider('responsePreparationFailures')]
    public function testResponsePreparationFailurePreservesExistingBatch(string $stage): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        [$gate, $request, , $identity] = $this->context('allowed');
        $manager = new DatabaseRecoveryCodeManager(
            SqliteDatabaseFixture::create(),
            new FrozenClock('2030-01-01T00:00:00Z', 'UTC'),
        );
        $existing = $manager->regenerate($identity->uuid, 2);
        $failure = new \RuntimeException('response preparation failed');
        $responses = $this->createStub(ResponseFactoryInterface::class);

        if ($stage === 'factory') {
            $responses->method('createResponse')->willThrowException($failure);
        } else {
            $response = $this->createStub(ResponseInterface::class);
            $response->method('getBody')->willReturn(Stream::create(''));
            $response->method('withHeader')->willThrowException($failure);
            $responses->method('createResponse')->willReturn($response);
        }

        try {
            (new RecoveryCodeRegenerateHandler($manager, $responses, $gate, count: 2))->handle($request);
            self::fail('Response preparation must fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertTrue($manager->consume($identity->uuid, $existing->codes[0]));
        self::assertTrue($manager->consume($identity->uuid, $existing->codes[1]));
    }

    #[DataProvider('requests')]
    public function testGateRunsBeforeCodeRegeneration(string $case, int $status): void
    {
        [$gate, $request, $responses, $identity] = $this->context($case);
        $manager = $this->createMock(RecoveryCodeManagerInterface::class);
        $manager->expects($case === 'allowed' ? self::once() : self::never())->method('regenerate')
            ->willReturn(new RecoveryCodeBatch([RecoveryCode::generate()]));
        $handler = new RecoveryCodeRegenerateHandler($manager, $responses, $gate);
        $response = $handler->handle($request);
        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public static function requests(): iterable
    {
        yield ['get', 405];
        yield ['no-csrf', 403];
        yield ['missing-origin', 403];
        yield ['stale', 403];
        yield ['allowed', 200];
    }

    private function context(string $case): array
    {
        $uuids = new UuidFactory();
        $identity = new readonly class($uuids->generate()) implements IdentityInterface {
            public function __construct(public UuidInterface $uuid) {}
        };
        $clock = new FrozenClock('2030-01-01T00:00:00+00:00', 'UTC');
        $at = $clock->now()->modify($case === 'stale' ? '-600 seconds' : '-60 seconds');
        $session = new AuthSession($uuids->generate(), $identity->uuid, new AuthenticationEvidence(['password']), 1, $at, null, $at, $at->modify('+1 hour'), $at->modify('+8 hours'));
        $registry = $this->createStub(AuthSessionRegistryInterface::class);
        $registry->method('find')->willReturn($session);
        $provider = $this->createStub(IdentityProviderInterface::class);
        $provider->method('findByUuid')->willReturn($identity);
        $guard = $this->createStub(AuthenticationGuardInterface::class);
        $guard->method('check')->willReturn(null);
        $responses = new Psr17Factory();
        $key = str_repeat('k', 32);
        $gate = new FactorManagementGuard($registry, new AuthenticationAdmission($provider, $guard), new AssuranceRequirement(['password'], maxAge: 300), $clock, $responses, $key);
        $request = (new ServerRequest($case === 'get' ? 'GET' : 'POST', 'https://example.test/factors'))
            ->withAttribute(IdentityInterface::class, $identity)
            ->withAttribute(AuthSession::class, $session);
        if ($case !== 'missing-origin') {
            $request = $request->withHeader('Origin', 'https://example.test');
        }
        if ($case !== 'no-csrf') {
            $request = $request->withHeader('X-CSRF-Token', (new AuthSessionCsrfTokenManager($session, $key))->generate());
        }
        return [$gate, $request, $responses, $identity];
    }
}
