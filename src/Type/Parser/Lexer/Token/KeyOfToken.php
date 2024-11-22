<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Lexer\Token;

use BackedEnum;
use CuyZ\Valinor\Type\Parser\Exception\Magic\KeyOfIncorrectSubType;
use CuyZ\Valinor\Type\Parser\Exception\Magic\KeyOfClosingBracketMissing;
use CuyZ\Valinor\Type\Parser\Exception\Magic\KeyOfOpeningBracketMissing;
use CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use CuyZ\Valinor\Type\Type;
use CuyZ\Valinor\Type\Types\ArrayType;
use CuyZ\Valinor\Type\Types\EnumType;
use CuyZ\Valinor\Type\Types\Factory\ValueTypeFactory;
use CuyZ\Valinor\Type\Types\ShapedArrayType;
use CuyZ\Valinor\Type\Types\UnionType;
use CuyZ\Valinor\Utility\IsSingleton;

use function array_map;
use function array_values;
use function count;
use function is_a;

/** @internal */
final class KeyOfToken implements TraversingToken
{
    use IsSingleton;

    public function traverse(TokenStream $stream): Type
    {
        if ($stream->done() || !$stream->forward() instanceof OpeningBracketToken) {
            throw new KeyOfOpeningBracketMissing();
        }

        $subType = $stream->read();

        if ($stream->done() || !$stream->forward() instanceof ClosingBracketToken) {
            throw new KeyOfClosingBracketMissing($subType);
        }

        if ($subType instanceof ShapedArrayType) {
            $list = [];
            foreach ($subType->elements() as $element) {
                $list []= $element->key();
            }
            if (count($list) > 0) {
                return new UnionType(...$list);
            }
            return $list[0];
        } elseif ($subType instanceof ArrayType) {
            return $subType->keyType();
        }

        if (! $subType instanceof EnumType) {
            throw new KeyOfIncorrectSubType($subType);
        }

        if (! is_a($subType->className(), BackedEnum::class, true)) {
            throw new KeyOfIncorrectSubType($subType);
        }

        $cases = array_map(
            // @phpstan-ignore-next-line / We know it's a BackedEnum
            fn (BackedEnum $case) => ValueTypeFactory::from($case->value),
            array_values($subType->cases()),
        );

        if (count($cases) > 1) {
            return new UnionType(...$cases);
        }

        return $cases[0];
    }

    public function symbol(): string
    {
        return 'key-of';
    }
}
