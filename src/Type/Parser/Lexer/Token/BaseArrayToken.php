<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Lexer\Token;

use AssertionError;
use CuyZ\Valinor\Type\IntegerType;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayClosingBracketMissing;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayColonTokenMissing;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayCommaMissing;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayElementTypeMissing;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayEmptyElements;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayInvalidUnsealedType;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayUnexpectedTokenAfterSealedType;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedArrayWithoutElementsWithSealedType;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedListNonMonotonicKey;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedListRequiredValueAfterOptional;
use CuyZ\Valinor\Type\Parser\Exception\Iterable\ShapedListStringKey;
use CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use CuyZ\Valinor\Type\Types\ArrayType;
use CuyZ\Valinor\Type\Types\IntegerValueType;
use CuyZ\Valinor\Type\Types\NativeIntegerType;
use CuyZ\Valinor\Type\Types\ShapedArrayElement;
use CuyZ\Valinor\Type\Types\ShapedArrayType;
use CuyZ\Valinor\Type\Types\StringValueType;

/** @internal */
abstract class BaseArrayToken implements TraversingToken
{
    protected function shapedArrayType(TokenStream $stream, bool $list): ShapedArrayType
    {
        $stream->forward();

        $elements = [];
        $index = 0;
        $isUnsealed = false;
        $unsealedType = null;
        $wasOptional = false;

        while (! $stream->done()) {
            if ($stream->next() instanceof ClosingCurlyBracketToken) {
                $stream->forward();
                break;
            }

            if (! empty($elements) && ! $stream->forward() instanceof CommaToken) {
                throw new ShapedArrayCommaMissing($elements);
            }

            if ($stream->done()) {
                throw new ShapedArrayClosingBracketMissing($elements);
            }

            if ($stream->next() instanceof ClosingCurlyBracketToken) {
                $stream->forward();
                break;
            }

            $optional = false;

            if ($stream->next() instanceof TripleDotsToken) {
                $isUnsealed = true;
                $stream->forward();
            }

            if ($stream->done()) {
                throw new ShapedArrayClosingBracketMissing($elements, unsealedType: false);
            }

            if ($stream->next() instanceof VacantToken) {
                $type = new StringValueType($stream->forward()->symbol());
            } elseif ($isUnsealed && $stream->next() instanceof ClosingCurlyBracketToken) {
                $stream->forward();
                break;
            } else {
                $type = $stream->read();
            }

            if ($isUnsealed) {
                $unsealedType = $type;

                if ($elements === []) {
                    throw new ShapedArrayWithoutElementsWithSealedType($unsealedType);
                }

                if (! $unsealedType instanceof ArrayType) {
                    throw new ShapedArrayInvalidUnsealedType($elements, $unsealedType);
                }

                if ($stream->done()) {
                    throw new ShapedArrayClosingBracketMissing($elements, $unsealedType);
                } elseif (! $stream->next() instanceof ClosingCurlyBracketToken) {
                    $unexpected = [];

                    while (! $stream->done() && ! $stream->next() instanceof ClosingCurlyBracketToken) {
                        $unexpected[] = $stream->forward();
                    }

                    throw new ShapedArrayUnexpectedTokenAfterSealedType($elements, $unsealedType, $unexpected);
                }

                continue;
            }

            if ($stream->done()) {
                $elements[] = new ShapedArrayElement(new IntegerValueType($index), $type);

                throw new ShapedArrayClosingBracketMissing($elements);
            }

            if ($stream->next() instanceof NullableToken) {
                $stream->forward();
                $optional = true;

                if ($stream->done()) {
                    throw new ShapedArrayColonTokenMissing($elements, $type);
                }
            }

            if ($stream->next() instanceof ColonToken) {
                $stream->forward();

                $key = $type;
                $type = null;

                if (! $key instanceof StringValueType && ! $key instanceof IntegerValueType) {
                    $key = new StringValueType($key->toString());
                }

                if ($key instanceof IntegerValueType) {
                    $expected = $index++;
                    if ($list && $key->value() !== $expected) {
                        throw new ShapedListNonMonotonicKey($key->value(), $index);
                    }
                } elseif ($list) {
                    throw new ShapedListStringKey($key->toString());
                }
            } else {
                if ($optional) {
                    throw new ShapedArrayColonTokenMissing($elements, $type);
                }

                $key = new IntegerValueType($index++);
            }

            if (! $type) {
                if ($stream->done()) {
                    throw new ShapedArrayElementTypeMissing($elements, $key, $optional);
                }

                $type = $stream->read();
            }

            if ($list && !$optional && $wasOptional) {
                throw new ShapedListRequiredValueAfterOptional($key->toString());
            }
            $wasOptional = $optional;

            $elements[] = new ShapedArrayElement($key, $type, $optional);

            if ($stream->done()) {
                throw new ShapedArrayClosingBracketMissing($elements);
            }
        }

        if ($elements === []) {
            throw new ShapedArrayEmptyElements();
        }

        if ($list && $unsealedType !== null && !$unsealedType->keyType()->isMatchedBy(NativeIntegerType::get())) {
            throw new ShapedListStringKey($unsealedType->keyType()->toString());
        }

        if ($unsealedType) {
            return $list
                ? ShapedArrayType::unsealedList($unsealedType, ...$elements)
                : ShapedArrayType::unsealed($unsealedType, ...$elements);
        } elseif ($isUnsealed) {
            return $list
                ? ShapedArrayType::unsealedListWithoutType(...$elements)
                : ShapedArrayType::unsealedWithoutType(...$elements);
        } elseif ($list) {
            return ShapedArrayType::list(...$elements);
        }

        return new ShapedArrayType(...$elements);
    }
}
