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
final class NamedArgumentsRuleForceSingleRequiredTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NamedArgumentsRule(
            reflectionProvider: static::getContainer()->getByType(ReflectionProvider::class),
            forceNamed: true,
            forceSingleRequired: true,
        );
    }

    public function testSingleRequiredIsForced(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/Php/NamedArguments/single_required.php'],
            [
                [
                    'Argument $required must be passed as a named argument.',
                    9,
                ],
                [
                    'Argument $required must be passed as a named argument.',
                    11,
                ],
                [
                    'Argument $optional must be passed as a named argument.',
                    11,
                ],
            ]
        );
    }
}
