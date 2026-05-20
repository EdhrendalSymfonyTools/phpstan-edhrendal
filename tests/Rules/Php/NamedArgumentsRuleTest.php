<?php

declare(strict_types=1);

namespace EdhrendalSfTools\PHPStan\Tests\Rules\Php;

use EdhrendalSfTools\PHPStan\Rules\Php\NamedArgumentsRule;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NamedArgumentsRule>
 */
final class NamedArgumentsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NamedArgumentsRule(
            reflectionProvider: static::getContainer()->getByType(ReflectionProvider::class),
            forceNamed: true,
            forceSingleRequired: false,
        );
    }

    public function testValidCalls(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/Php/NamedArguments/valid_calls.php'],
            []
        );
    }

    public function testOrderErrors(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/Php/NamedArguments/order_errors.php'],
            [
                [
                    'Named arguments are not in the same order as declared in the function signature (expected: $a, $b, $c; got: $b, $a, $c).',
                    9,
                ],
                [
                    'Named arguments are not in the same order as declared in the function signature (expected: $a, $b, $c; got: $c, $b, $a).',
                    11,
                ],
            ]
        );
    }

    public function testMissingNameErrors(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/Php/NamedArguments/missing_name_errors.php'],
            [
                [
                    'Argument $x must be passed as a named argument.',
                    9,
                ],
                [
                    'Argument $y must be passed as a named argument.',
                    9,
                ],
                [
                    'Argument $x must be passed as a named argument.',
                    11,
                ],
            ]
        );
    }

    public function testSingleRequiredIsExempt(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/Php/NamedArguments/single_required.php'],
            []
        );
    }
}
