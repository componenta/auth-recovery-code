<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

use Componenta\Auth\Session\Http\FactorManagementGuard;
use Componenta\Identity\IdentityInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RecoveryCodeRegenerateHandler implements
    RequestHandlerInterface
{
    public function __construct(
        private RecoveryCodeManagerInterface $codes,
        private ResponseFactoryInterface $responses,
        private FactorManagementGuard $guard,
        private int $count = 10,
    ) {}

    #[\Override]
    public function handle(
        #[\SensitiveParameter]
        ServerRequestInterface $request,
    ): ResponseInterface {
        if (($denial = $this->guard->check($request)) !== null) {
            return $denial;
        }

        $identity = $request->getAttribute(IdentityInterface::class);

        if (!$identity instanceof IdentityInterface) {
            return $this->responses->createResponse(401);
        }

        $batch = $this->codes->regenerate($identity->uuid, $this->count);
        $response = $this->responses->createResponse(200);
        $response->getBody()->write(json_encode([
            'codes' => array_map(
                static fn(RecoveryCode $code): string => $code->toString(),
                $batch->codes,
            ),
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}
