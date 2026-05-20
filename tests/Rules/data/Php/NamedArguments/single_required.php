<?php

declare(strict_types=1);

namespace App\NamedArgs\SingleRequired;

function withOneRequired(int $required, int $optional = 0): void {}

withOneRequired(42);

withOneRequired(42, 10);
