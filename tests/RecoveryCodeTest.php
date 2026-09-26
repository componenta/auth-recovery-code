<?php

declare(strict_types=1);

namespace Componenta\Auth\RecoveryCode\Tests;

use Componenta\Auth\RecoveryCode\RecoveryCode;
use PHPUnit\Framework\TestCase;

final class RecoveryCodeTest extends TestCase
{
    public function testCodeHas128BitsOfSourceEntropyAndRedactsDebugOutput(): void
    {
        $code = RecoveryCode::generate();

        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{4}(?:-[a-f0-9]{4}){7}\z/D',
            $code->toString(),
        );
        self::assertStringNotContainsString(
            $code->toString(),
            json_encode($code, JSON_THROW_ON_ERROR),
        );
    }
}
