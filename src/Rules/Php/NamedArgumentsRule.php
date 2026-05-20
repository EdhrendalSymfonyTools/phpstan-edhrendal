<?php

declare(strict_types=1);

namespace EdhrendalSfTools\PHPStan\Rules\Php;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces named argument conventions on all function, method, static-method, and
 * constructor calls:
 *   1. Named arguments must be passed in the same order as declared in the signature
 *   2. All arguments must use named syntax — unless the callable has only one required
 *      parameter, in which case positional syntax is tolerated
 *
 * Both checks are skipped when the callable does not support named arguments
 * (e.g. some internal C extensions).
 *
 * This rule is opt-in. Include it explicitly in your phpstan.neon:
 * ```neon
 * includes:
 *     - vendor/edhrendal-sf-tools/phpstan-edhrendal/rules/php/named-arguments.neon
 *
 * parameters:
 *     edhrendal:
 *         php:
 *             namedArguments:
 *                 forceNamed: true        # default: true
 *                 forceSingleRequired: false  # default: false — when true, the
 *                                             # single-required-param exemption is removed
 * ```
 *
 * @implements Rule<Node\Expr\CallLike>
 */
final class NamedArgumentsRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly bool $forceNamed = true,
        private readonly bool $forceSingleRequired = false,
    ) {}

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $args = $node->getArgs();

        if (count($args) === 0) {
            return [];
        }

        $acceptor = $this->resolveAcceptor($node, $scope);

        if ($acceptor === null) {
            return [];
        }

        $parameters = $acceptor->getParameters();

        if (count($parameters) === 0) {
            return [];
        }

        $errors = [];

        $orderError = $this->checkOrder($args, $parameters);

        if ($orderError !== null) {
            $errors[] = $orderError;
        }

        if ($this->forceNamed === true) {
            $errors = array_merge($errors, $this->checkForceNamed($args, $parameters));
        }

        return $errors;
    }

    /**
     * @param Node\Arg[] $args
     * @param ParameterReflection[] $parameters
     */
    private function checkOrder(array $args, array $parameters): ?IdentifierRuleError
    {
        $paramPositions = [];

        foreach ($parameters as $index => $param) {
            $paramPositions[$param->getName()] = $index;
        }

        $namedArgNames = [];
        $namedArgPositions = [];

        foreach ($args as $arg) {
            if ($arg->name === null) {
                continue;
            }

            $name = $arg->name->name;

            if (isset($paramPositions[$name]) === false) {
                continue;
            }

            $namedArgNames[] = '$' . $name;
            $namedArgPositions[] = $paramPositions[$name];
        }

        if (count($namedArgPositions) <= 1) {
            return null;
        }

        $sortedPositions = $namedArgPositions;
        sort($sortedPositions);

        if ($namedArgPositions === $sortedPositions) {
            return null;
        }

        $positionToName = array_flip($paramPositions);
        $expectedNames = array_map(static fn(int $pos): string => '$' . $positionToName[$pos], $sortedPositions);

        return RuleErrorBuilder::message(
            sprintf(
                'Named arguments are not in the same order as declared in the function signature (expected: %s; got: %s).',
                implode(', ', $expectedNames),
                implode(', ', $namedArgNames)
            )
        )
            ->identifier('edhrendal.namedArguments.order')
            ->build();
    }

    /**
     * @param Node\Arg[] $args
     * @param ParameterReflection[] $parameters
     * @return IdentifierRuleError[]
     */
    private function checkForceNamed(array $args, array $parameters): array
    {
        $requiredCount = count(array_filter(
            $parameters,
            static fn(ParameterReflection $p): bool => $p->isOptional() === false && $p->isVariadic() === false
        ));

        if ($requiredCount <= 1 && $this->forceSingleRequired === false) {
            return [];
        }

        $errors = [];
        $positionalIndex = 0;

        foreach ($args as $arg) {
            if ($arg->name !== null) {
                continue;
            }

            if ($arg->unpack === true) {
                $positionalIndex++;
                continue;
            }

            $param = $parameters[$positionalIndex] ?? null;
            $paramLabel = $param !== null ? '$' . $param->getName() : '#' . ($positionalIndex + 1);

            $errors[] = RuleErrorBuilder::message(
                sprintf('Argument %s must be passed as a named argument.', $paramLabel)
            )
                ->identifier('edhrendal.namedArguments.missingName')
                ->line($arg->getStartLine())
                ->build();

            $positionalIndex++;
        }

        return $errors;
    }

    private function resolveAcceptor(Node\Expr\CallLike $node, Scope $scope): ?ParametersAcceptor
    {
        if ($node instanceof Node\Expr\FuncCall) {
            return $this->resolveFuncCallAcceptor($node, $scope);
        }

        if ($node instanceof Node\Expr\MethodCall) {
            return $this->resolveMethodCallAcceptor($node, $scope);
        }

        if ($node instanceof Node\Expr\StaticCall) {
            return $this->resolveStaticCallAcceptor($node, $scope);
        }

        if ($node instanceof Node\Expr\New_) {
            return $this->resolveNewAcceptor($node, $scope);
        }

        return null;
    }

    private function resolveFuncCallAcceptor(Node\Expr\FuncCall $node, Scope $scope): ?ParametersAcceptor
    {
        if ($node->name instanceof Node\Name === false) {
            return null;
        }

        if ($this->reflectionProvider->hasFunction($node->name, $scope) === false) {
            return null;
        }

        $fn = $this->reflectionProvider->getFunction($node->name, $scope);

        if ($fn->acceptsNamedArguments()->no()) {
            return null;
        }

        return ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $fn->getVariants(), $fn->getNamedArgumentsVariants());
    }

    private function resolveMethodCallAcceptor(Node\Expr\MethodCall $node, Scope $scope): ?ParametersAcceptor
    {
        if ($node->name instanceof Node\Identifier === false) {
            return null;
        }

        $callerType = $scope->getType($node->var);
        $method = $scope->getMethodReflection($callerType, $node->name->name);

        if ($method === null) {
            return null;
        }

        if ($method->acceptsNamedArguments()->no()) {
            return null;
        }

        return ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $method->getVariants(), $method->getNamedArgumentsVariants());
    }

    private function resolveStaticCallAcceptor(Node\Expr\StaticCall $node, Scope $scope): ?ParametersAcceptor
    {
        if ($node->name instanceof Node\Identifier === false) {
            return null;
        }

        if ($node->class instanceof Node\Name) {
            $classType = $scope->resolveTypeByName($node->class);
        } elseif ($node->class instanceof Node\Expr) {
            $classType = $scope->getType($node->class);
        } else {
            return null;
        }

        $method = $scope->getMethodReflection($classType, $node->name->name);

        if ($method === null) {
            return null;
        }

        if ($method->acceptsNamedArguments()->no()) {
            return null;
        }

        return ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $method->getVariants(), $method->getNamedArgumentsVariants());
    }

    private function resolveNewAcceptor(Node\Expr\New_ $node, Scope $scope): ?ParametersAcceptor
    {
        if ($node->class instanceof Node\Name === false) {
            return null;
        }

        $className = $scope->resolveName($node->class);

        if ($this->reflectionProvider->hasClass($className) === false) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if ($classReflection->hasConstructor() === false) {
            return null;
        }

        $constructor = $classReflection->getConstructor();

        if ($constructor->acceptsNamedArguments()->no()) {
            return null;
        }

        return ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $constructor->getVariants(), $constructor->getNamedArgumentsVariants());
    }
}
