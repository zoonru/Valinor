<?php

namespace CuyZ\Valinor\Utility\Reflection;

use CuyZ\Valinor\Type\Parser\Exception\Template\DuplicatedTemplateName;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;

use function end;
use function preg_match;
use function preg_match_all;
use function str_replace;
use function str_split;
use function strrpos;
use function substr;
use function trim;

/** @internal */
final class DocParser
{
    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    public static function magicProperties(ReflectionClass $reflection): array
    {
        $types = [];
        $docComment = self::sanitizeDocComment($reflection);

        $expression = '/@property\s+(?<type>.+)\s+\$([a-zA-Z_0-9]+)/u';

        preg_match_all($expression, $docComment, $matches);

        foreach ($matches[2] as $key => $name) {
            $types[(string)$name] = $matches[1][$key];
        }

        return $types;
    }

    public static function propertyType(ReflectionProperty $reflection): ?string
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return null;
        }

        return self::annotationType($doc, 'var');
    }

    public static function parameterType(ReflectionParameter $reflection): ?string
    {
        $doc = self::sanitizeDocComment($reflection->getDeclaringFunction()->getDocComment());

        if ($doc === null) {
            return null;
        }

        if (! preg_match("/(?<type>.*)\\$$reflection->name(\s|\z)/s", $doc, $matches)) {
            return null;
        }

        return self::annotationType($matches['type'], 'param');
    }

    public static function functionReturnType(ReflectionFunctionAbstract $reflection): ?string
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return null;
        }

        return self::annotationType($doc, 'return');
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    public static function localTypeAliases(ReflectionClass $reflection): array
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return [];
        }

        $cases = self::splitStringBy($doc, '@phpstan-type', '@psalm-type');

        $types = [];

        foreach ($cases as $case) {
            if (! preg_match('/\s*(?<name>[a-zA-Z]\w*)\s*=?\s*(?<type>.*)/s', $case, $matches)) {
                continue;
            }

            $types[$matches['name']] = self::findType($matches['type']);
        }

        return $types;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string[]>
     */
    public static function importedTypeAliases(ReflectionClass $reflection): array
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return [];
        }

        $cases = self::splitStringBy($doc, '@phpstan-import-type', '@psalm-import-type');

        $types = [];

        foreach ($cases as $case) {
            if (! preg_match('/\s*(?<name>[a-zA-Z]\w*)\s+from\s+(?<class>\w+)/', $case, $matches)) {
                continue;
            }

            $types[$matches['class']][] = $matches['name'];
        }

        return $types;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string>
     */
    public static function classExtendsTypes(ReflectionClass $reflection): array
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return [];
        }

        preg_match_all('/@(phpstan-|psalm-)?extends\s+(?<type>.+)/', $doc, $matches);

        return $matches['type'];
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    public static function classTemplates(ReflectionClass $reflection): array
    {
        $doc = self::sanitizeDocComment($reflection->getDocComment());

        if ($doc === null) {
            return [];
        }

        $templates = [];

        preg_match_all("/@(phpstan-|psalm-)?template(?:-covariant)?\s+(?<name>\w+)(\s+of\s+(?<type>.+))?/", $doc, $matches);

        foreach ($matches['name'] as $key => $name) {
            /** @var string $name */
            if (isset($templates[$name])) {
                throw new DuplicatedTemplateName($reflection->name, $name);
            }

            $templates[$name] = self::findType($matches['type'][$key]);
        }

        return $templates;
    }

    private static function annotationType(string $string, string $annotation): ?string
    {
        foreach (["@phpstan-$annotation", "@psalm-$annotation", "@$annotation"] as $case) {
            $pos = strrpos($string, $case);

            if ($pos !== false) {
                return self::findType(substr($string, $pos + strlen($case)));
            }
        }

        return null;
    }

    private static function findType(string $string): string
    {
        $operatorsMatrix = [
            '{' => '}',
            '<' => '>',
            '"' => '"',
            "'" => "'",
        ];

        $type = '';
        $operators = [];
        $expectExpression = true;

        $string = str_replace("\n", ' ', $string);
        $chars = str_split($string);

        foreach ($chars as $key => $char) {
            if ($operators === []) {
                if ($char === '|' || $char === '&') {
                    $expectExpression = true;
                } elseif (! $expectExpression && $chars[$key - 1] === ' ') {
                    break;
                } elseif ($char !== ' ') {
                    $expectExpression = false;
                }
            }

            if (isset($operatorsMatrix[$char])) {
                $operators[] = $operatorsMatrix[$char];
            } elseif ($operators !== [] && $char === end($operators)) {
                array_pop($operators);
            }

            $type .= $char;
        }

        return trim($type);
    }

    private static function sanitizeDocComment(string|false $doc): ?string
    {
        /** @infection-ignore-all mutating `$doc` to `true` makes no sense */
        if ($doc === false) {
            return null;
        }

        $doc = preg_replace('#^\s*/\*\*([^/]+)\*/\s*$#', '$1', $doc);

        return preg_replace('/^\s*\*\s*(\S*)/m', '$1', $doc); // @phpstan-ignore-line
    }

    /**
     * @param non-empty-string ...$cases
     * @return list<string>
     */
    private static function splitStringBy(string $string, string ...$cases): array
    {
        $first = true;
        $result = [];
        $pos = 0;
        do {
            $next = false;
            $len = 0;
            foreach ($cases as $case) {
                $tentative = strpos($string, $case, $pos);
                if ($tentative === false) {
                    continue;
                }
                if ($next === false || $tentative < $next) {
                    $len = strlen($case);
                    $next = $tentative;
                }
            }
            if ($next === false) {
                break;
            }
            if ($first) {
                $first = false;
            } else {
                $result []= substr($string, $pos, $next-$pos);
            }
            $pos = $next+$len;
        } while (true);
        if (!$first) {
            $result []= substr($string, $pos);
        }

        return $result;
    }
}