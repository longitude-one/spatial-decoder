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

namespace LongitudeOne\SpatialDecoder\Tests\Unit;

use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer
 */
class LexerTest extends TestCase
{
    /** Test classification and preservation of extreme numeric lexemes. */
    public function testPreservesExtremeNumericLexemesAndClassifiesThem(): void
    {
        $minimumInteger = (string) \PHP_INT_MIN;
        $maximumInteger = (string) \PHP_INT_MAX;
        $values = [
            $minimumInteger,
            $maximumInteger,
            $maximumInteger.'0',
            '-'.substr($minimumInteger, 1).'0',
            '1.7976931348623157e308',
            '5e-324',
            '1e309',
            '-1e309',
        ];
        $tokens = $this->tokenize(new Lexer(implode(' ', $values)));

        self::assertSame([
            Lexer::T_INTEGER,
            Lexer::T_INTEGER,
            Lexer::T_INTEGER,
            Lexer::T_INTEGER,
            Lexer::T_FLOAT,
            Lexer::T_FLOAT,
            Lexer::T_FLOAT,
            Lexer::T_FLOAT,
        ], array_column($tokens, 'type'));
        self::assertSame($values, array_column($tokens, 'value'));
    }

    /** Test tokenization of WKT keywords, punctuation, and unknown characters. */
    public function testTokenizesKeywordsSymbolsAndUnknownCharacters(): void
    {
        $tokens = $this->tokenize(new Lexer('pOiNt zm empty polyhedralsurface (1, 2);?'));

        self::assertSame([
            Lexer::T_POINT,
            Lexer::T_ZM,
            Lexer::T_EMPTY,
            Lexer::T_POLYHEDRALSURFACE,
            Lexer::T_OPEN_PARENTHESIS,
            Lexer::T_INTEGER,
            Lexer::T_COMMA,
            Lexer::T_INTEGER,
            Lexer::T_CLOSE_PARENTHESIS,
            Lexer::T_SEMICOLON,
            Lexer::T_NONE,
        ], array_column($tokens, 'type'));
        self::assertSame(['pOiNt', 'zm', 'empty', 'polyhedralsurface', '(', '1', ',', '2', ')', ';', '?'], array_column($tokens, 'value'));
    }

    /** Test normalized values at the native integer boundaries. */
    public function testValuePreservesNativeIntegerBoundaries(): void
    {
        $lexer = new Lexer(\sprintf('%d %d', \PHP_INT_MIN, \PHP_INT_MAX));

        self::assertTrue($lexer->moveNext());
        self::assertTrue($lexer->moveNext());
        self::assertSame(\PHP_INT_MIN, $lexer->value());
        self::assertFalse($lexer->moveNext());
        self::assertSame(\PHP_INT_MAX, $lexer->value());
    }

    /**
     * Consume all tokens from a lexer.
     *
     * @param Lexer $lexer lexer to consume
     *
     * @return list<array{type: int|null, value: int|string, position: int}>
     */
    private function tokenize(Lexer $lexer): array
    {
        $tokens = [];
        while ($lexer->moveNext()) {
            $token = $lexer->lookahead;
            $tokens[] = [
                'type' => $token->type,
                'value' => $token->value,
                'position' => $token->position,
            ];
        }

        return $tokens;
    }
}
