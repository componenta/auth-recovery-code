<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode;

use Componenta\Auth\AuthenticationEvidence;

final class RecoveryCodeEvidence
{
    private function __construct() {}

    public static function augment(
        AuthenticationEvidence $evidence,
    ): AuthenticationEvidence {
        return new AuthenticationEvidence(
            methods: array_values(array_unique([
                ...$evidence->methods,
                'recovery_code',
            ])),
            capabilities: array_values(array_unique([
                ...$evidence->capabilities,
                'recovery',
            ])),
        );
    }
}
