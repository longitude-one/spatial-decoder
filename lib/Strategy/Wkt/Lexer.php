<?php
/**
 * This file is part of the spatial-decoder project.
 *
 * PHP 8.4 | 8.5
 *
 * Copyright Alexandre Tranchant <alexandre.tranchant@gmail.com> 2026
 * Copyright Longitude One 2026
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

declare(strict_types=1);

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt;

use Doctrine\Common\Lexer\AbstractLexer;

/**
 * Convert spatial value to tokens.
 *
 * @extends AbstractLexer<int, int|string>
 */
class Lexer extends AbstractLexer
{
    public const T_BREPSOLID = 698;
    public const T_CIRCLE = 697;
    public const T_CIRCULARSTRING = 608;
    public const T_CLOSE_PARENTHESIS = 6;
    public const T_CLOTHOID = 696;
    public const T_COMMA = 8;
    public const T_COMPOUNDCURVE = 609;
    public const T_COMPOUNDSURFACE = 695;
    public const T_CURVE = 613;
    public const T_CURVEPOLYGON = 610;
    public const T_DOT = 10;
    public const T_ELLIPTICALCURVE = 694;
    public const T_EMPTY = 504;
    public const T_EQUALS = 11;
    public const T_FLOAT = 5;
    public const T_GEODESICSTRING = 693;
    public const T_GEOMETRY = 690;
    public const T_GEOMETRYCOLLECTION = 607;
    public const T_INTEGER = 2;
    public const T_LINESTRING = 602;
    public const T_M = 503;
    public const T_MINUS = 14;
    public const T_MULTICURVE = 611;
    public const T_MULTILINESTRING = 605;
    public const T_MULTIPOINT = 604;
    public const T_MULTIPOLYGON = 606;
    public const T_MULTISURFACE = 612;
    public const T_NONE = 1;
    public const T_NURBSCURVE = 692;
    public const T_OPEN_PARENTHESIS = 7;
    public const T_POINT = 601;
    public const T_POLYGON = 603;
    public const T_POLYHEDRALSURFACE = 615;
    public const T_SEMICOLON = 50;
    public const T_SOLID = 699;
    public const T_SPIRALCURVE = 691;
    public const T_SRID = 500;
    public const T_STRING = 3;
    public const T_SURFACE = 614;
    public const T_TIN = 616;
    public const T_TRIANGLE = 617;
    public const T_TYPE = 600;
    public const T_Z = 502;
    public const T_ZM = 501;

    /**
     * Initialize the lexer with optional WKT input.
     *
     * @param string|null $input WKT text to tokenize
     */
    public function __construct(?string $input = null)
    {
        if (null !== $input) {
            $this->setInput((string) $input);
        }
    }

    /**
     * Return the normalized value of the last consumed token.
     *
     * @return int|string normalized token value
     */
    public function value(): int|string
    {
        $tokenValue = $this->token?->value;
        if (\is_int($tokenValue)) {
            return $tokenValue;
        }

        if (is_numeric($tokenValue)) {
            $integer = filter_var((string) $tokenValue, \FILTER_VALIDATE_INT);
            if (false !== $integer) {
                return $integer;
            }

            return (string) (float) $tokenValue;
        }

        return $tokenValue ?? '';
    }

    /**
     * @return string[]
     */
    protected function getCatchablePatterns(): array
    {
        return [
            '[a-z]+',
            '[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:e[+-]?[0-9]+)?',
            '[(),=;]',
        ];
    }

    /**
     * @return string[]
     */
    protected function getNonCatchablePatterns(): array
    {
        return ['\s+'];
    }

    /**
     * Determine the token type for a matched lexeme.
     *
     * @param string $value matched lexeme
     *
     * @return int token type
     */
    protected function getType(string &$value): int
    {
        if (is_numeric($value)) {
            return $this->getNumericType($value);
        }

        if (preg_match('/^[a-zA-Z]+$/', $value)) {
            return $this->getWordType($value);
        }

        return match ($value) {
            ',' => self::T_COMMA,
            '(' => self::T_OPEN_PARENTHESIS,
            ')' => self::T_CLOSE_PARENTHESIS,
            '=' => self::T_EQUALS,
            ';' => self::T_SEMICOLON,
            default => self::T_NONE,
        };
    }

    /**
     * Classify a numeric lexeme as an integer or floating-point token.
     *
     * @param string $value numeric lexeme
     *
     * @return int numeric token type
     */
    private function getNumericType(string $value): int
    {
        return 1 === preg_match('/^[+-]?[0-9]+$/D', $value) ? self::T_INTEGER : self::T_FLOAT;
    }

    /**
     * Resolve a word to its named token type when one exists.
     *
     * @param string $value word lexeme
     *
     * @return int named token type, or the generic string token type
     */
    private function getWordType(string $value): int
    {
        $name = __CLASS__.'::T_'.strtoupper($value);
        if (!\defined($name)) {
            return self::T_STRING;
        }

        $constantValue = \constant($name);

        return \is_int($constantValue) ? $constantValue : self::T_STRING;
    }
}
