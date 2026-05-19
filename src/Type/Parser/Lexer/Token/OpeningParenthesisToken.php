<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Type\Parser\Lexer\Token;

use CuyZ\Valinor\Type\Parser\Exception\UnexpectedToken;
use CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use CuyZ\Valinor\Type\Type;
use CuyZ\Valinor\Utility\IsSingleton;

/** @internal */
final class OpeningParenthesisToken implements TraversingToken
{
    use IsSingleton;

    public function traverse(TokenStream $stream): Type
    {
        if ($stream->done()) {
            throw new UnexpectedToken('(');
        }

        $type = $stream->read();

        if ($stream->done() || ! $stream->forward() instanceof ClosingParenthesisToken) {
            throw new UnexpectedToken(')');
        }

        return $type;
    }

    public function symbol(): string
    {
        return '(';
    }
}
