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

use Doctrine\Common\Lexer\Token;
use LongitudeOne\Core\Diagnostic\DiagnosticValueFormatter;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;

/**
 * Owns the WKT lexer cursor and input-specific syntax errors.
 *
 * @internal
 */
final class WktTokenCursor
{
    private Lexer $lexer;

    /**
     * Construct a cursor for the supplied WKT input.
     *
     * @param string $input WKT text to parse
     */
    public function __construct(private string $input)
    {
        $this->lexer = new Lexer($input);
        $this->lexer->moveNext();
    }

    /**
     * Ensure that no input tokens remain.
     */
    public function assertEnd(): void
    {
        if (null !== $this->lexer->lookahead) {
            throw $this->createInvalidInputException('The WKT input contains trailing or unsupported data.');
        }
    }

    /**
     * Create an exception that includes a safe rendering of the supplied WKT.
     *
     * @param string $reason explanation of why the input was rejected
     */
    public function createInvalidInputException(string $reason): InvalidArgumentException
    {
        $formattedInput = DiagnosticValueFormatter::format($this->input);

        return new InvalidArgumentException(\sprintf('%s Invalid WKT input: "%s".', $reason, $formattedInput));
    }

    /**
     * Return the next token, if one exists.
     *
     * @return Token<int, int|string>|null
     */
    public function currentToken(): ?Token
    {
        return $this->lexer->lookahead;
    }

    /**
     * Consume the expected parenthesis token.
     *
     * @param string $symbol expected parenthesis
     *
     * @throws InvalidArgumentException when the next token does not match
     */
    public function expectSymbol(string $symbol): void
    {
        $expectedType = match ($symbol) {
            '(' => Lexer::T_OPEN_PARENTHESIS,
            ')' => Lexer::T_CLOSE_PARENTHESIS,
            default => throw $this->createInvalidInputException('The WKT geometry syntax is malformed.'),
        };
        if (!$this->isNextToken($expectedType)) {
            throw $this->createInvalidInputException('The WKT geometry syntax is malformed.');
        }

        $this->moveNext();
    }

    /**
     * Determine whether the next token has the supplied type.
     *
     * @param int $type token type to check
     *
     * @return bool whether the next token has the requested type
     */
    public function isNextToken(int $type): bool
    {
        return $this->lexer->isNextToken($type);
    }

    /**
     * Advance the lexer to the next token.
     */
    public function moveNext(): void
    {
        $this->lexer->moveNext();
    }
}
