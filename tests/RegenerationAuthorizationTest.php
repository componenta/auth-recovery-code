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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use Componenta\Auth\RecoveryCode\RecoveryCode;
use Componenta\Auth\RecoveryCode\RecoveryCodeBatch;
use Componenta\Auth\RecoveryCode\RecoveryCodeManagerInterface;
use Componenta\Auth\RecoveryCode\RecoveryCodeRegenerateHandler;

final class RegenerationAuthorizationTest extends TestCase
{
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
        if ($case !== 'no-csrf') {
            $request = $request->withHeader('X-CSRF-Token', (new AuthSessionCsrfTokenManager($session, $key))->generate());
        }
        return [$gate, $request, $responses, $identity];
    }
}
