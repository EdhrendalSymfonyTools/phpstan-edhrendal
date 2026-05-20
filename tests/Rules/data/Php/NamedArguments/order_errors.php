<?php

declare(strict_types=1);

namespace App\NamedArgs\OrderErrors;

function foo(int $a, int $b, int $c): void {}

foo(b: 2, a: 1, c: 3);

foo(c: 3, b: 2, a: 1);
