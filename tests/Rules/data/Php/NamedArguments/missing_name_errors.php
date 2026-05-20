<?php

declare(strict_types=1);

namespace App\NamedArgs\MissingName;

function bar(int $x, int $y): void {}

bar(1, 2);

bar(1, y: 2);
