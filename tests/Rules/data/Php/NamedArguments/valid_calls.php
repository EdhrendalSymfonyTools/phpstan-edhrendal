<?php

declare(strict_types=1);

namespace App\NamedArgs\Valid;

function twoRequired(int $a, int $b): void {}

function oneRequired(int $a): void {}

twoRequired(a: 1, b: 2);

oneRequired(42);
