<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Exception\Iterable;

use CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use RuntimeException;

/** @internal */
final class ShapedListStringKey extends RuntimeException implements InvalidType
{
    public function __construct(string $key)
    {
        parent::__construct(
            "String key `$key` cannot be used in shaped list signature.",
            1631283210
        );
    }
}
