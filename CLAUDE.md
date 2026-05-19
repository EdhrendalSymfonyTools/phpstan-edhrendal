# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a PHPStan extension package (`edhrendal/phpstan-edhrendal`) providing custom static analysis rules for Edhrendal projects. It targets PHP 8.5+ and PHPStan 2.x.

## Commands

```bash
# Install dependencies
composer install && composer dump-autoload

# Run tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Rules/Doctrine/Repository/NoRepositoryMagicMethodRuleTest.php

# Run PHPStan on the extension's own source
vendor/bin/phpstan analyse --configuration phpstan.neon.dist
```

## Architecture

**Rules are opt-in.** `extension.neon` is auto-loaded by PHPStan via `extra.phpstan.includes` in `composer.json`, but it registers no rules — it only serves as the entry point. Each rule has its own neon file under `rules/` which consumers include explicitly in their `phpstan.neon`.

**Adding a new rule:**
1. Create the class in `src/Rules/` implementing `PHPStan\Rules\Rule<TNode>`
2. Create a neon file in `rules/` that registers the service, declares the `parametersSchema`, and sets parameter defaults — everything self-contained in that one file
3. Document the include path in the class PHPDoc

**Test pattern** — Rule tests live in `tests/Rules/` mirroring `src/Rules/`, and extend `PHPStan\Testing\RuleTestCase<TRule>`. Fixture files live under `tests/Rules/data/`. Third-party stubs (e.g. `Doctrine\ORM\EntityRepository`) are defined as lightweight stub classes in `tests/Rules/data/` and registered via the `classmap` entry in `composer.json` `autoload-dev` so that PHPStan's `ReflectionProvider` can resolve them. Run `composer dump-autoload` after adding new fixture classes.

**Namespace mapping:**
- `Edhrendal\PHPStan\` → `src/`
- `Edhrendal\PHPStan\Tests\` → `tests/`
