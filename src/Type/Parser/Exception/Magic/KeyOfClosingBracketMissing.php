<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Exception\Magic;

use CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use CuyZ\Valinor\Type\Type;
use RuntimeException;

/** @internal */
final class KeyOfClosingBracketMissing extends RuntimeException implements InvalidType
{
    public function __construct(Type $type)
    {
        parent::__construct(
            "The closing bracket is missing for `key-of<{$type->toString()}>`.",
            1717702289
        );
    }
}
