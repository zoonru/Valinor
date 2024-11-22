<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Exception\Iterable;

use CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use RuntimeException;

/** @internal */
final class ShapedListNonMonotonicKey extends RuntimeException implements InvalidType
{
    public function __construct(int $key, int $expectedKey)
    {
        parent::__construct(
            "Expected monotonically increasing key `$expectedKey`, but got key `$key`.",
            1631283210
        );
    }
}
