<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

use Componenta\Auth\AuthenticationEvidence;

final class RecoveryCodeEvidence
{
    private function __construct() {}

    public static function create(): AuthenticationEvidence
    {
        return new AuthenticationEvidence(
            methods: ['recovery_code'],
            capabilities: ['recovery'],
        );
    }
}
