<?php

namespace Vvbx\SuiteBundle\Tests\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Vvbx\SuiteBundle\Validator\Siren;

final class SirenTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function values(): iterable
    {
        yield 'neuf chiffres' => ['123456789', true];
        yield 'nul accepté' => [null, true];
        yield 'trop court' => ['12345678', false];
        yield 'lettres' => ['12345678A', false];
        yield 'SIRET (14 chiffres)' => ['12345678900012', false];
    }

    #[DataProvider('values')]
    public function testFormat(?string $value, bool $valid): void
    {
        $violations = Validation::createValidator()->validate($value, new Siren());

        self::assertSame($valid, 0 === \count($violations));
        if (!$valid) {
            self::assertSame(Siren::MESSAGE, $violations->get(0)->getMessageTemplate());
        }
    }

    public function testNormalize(): void
    {
        self::assertSame('123456789', Siren::normalize(' 123 456 789 '));
        self::assertNull(Siren::normalize('  '));
        self::assertNull(Siren::normalize(null));
    }
}
