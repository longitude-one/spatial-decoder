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
use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;

/**
 * Reads and validates WKT dimensions, coordinates, and coordinate sequences.
 *
 * @internal
 */
final class WktCoordinateReader
{
    /**
     * Construct a coordinate reader.
     *
     * @param WktTokenCursor $cursor lexer cursor for the WKT input
     */
    public function __construct(private WktTokenCursor $cursor)
    {
    }

    /**
     * Read one coordinate sequence and its effective dimension.
     *
     * @param CoordinateDimensionEnum|null $dimension declared or inferred dimension
     *
     * @return array{CoordinateDimensionEnum, list<list<float|int>>} sequence dimension and coordinates
     */
    public function consumeCoordinateSequence(?CoordinateDimensionEnum $dimension): array
    {
        $coordinates = [];
        $this->cursor->expectSymbol('(');

        while (true) {
            $ordinates = $this->consumeOrdinates();
            $dimension ??= $this->dimensionForUnmarkedCoordinate(\count($ordinates));

            if (\count($ordinates) !== $dimension->coordinateDimension()) {
                throw $this->cursor->createInvalidInputException('The WKT coordinates do not match their coordinate dimension.');
            }

            $coordinates[] = $ordinates;
            if (!$this->cursor->isNextToken(Lexer::T_COMMA)) {
                break;
            }

            $this->cursor->moveNext();
        }

        $this->cursor->expectSymbol(')');
        if (\count($coordinates) < 2) {
            throw $this->cursor->createInvalidInputException('A non-empty WKT line string must contain at least two coordinates.');
        }

        return [$dimension, $coordinates];
    }

    /**
     * Consume an optional coordinate-dimension marker.
     *
     * @return CoordinateDimensionEnum|null the declared coordinate dimension
     */
    public function consumeDimension(): ?CoordinateDimensionEnum
    {
        return match ($this->cursor->currentToken()?->type) {
            Lexer::T_Z => $this->consumeDimensionToken(CoordinateDimensionEnum::XYZ),
            Lexer::T_M => $this->consumeDimensionToken(CoordinateDimensionEnum::XYM),
            Lexer::T_ZM => $this->consumeDimensionToken(CoordinateDimensionEnum::XYZM),
            default => null,
        };
    }

    /**
     * Read one point coordinate and its effective dimension.
     *
     * @param CoordinateDimensionEnum|null $dimension declared or inferred dimension
     *
     * @return array{CoordinateDimensionEnum, list<float|int>} point dimension and ordinates
     */
    public function consumePointCoordinate(?CoordinateDimensionEnum $dimension): array
    {
        $this->cursor->expectSymbol('(');
        $ordinates = $this->consumeOrdinates();
        $this->cursor->expectSymbol(')');
        $dimension ??= $this->dimensionForUnmarkedCoordinate(\count($ordinates));

        if (\count($ordinates) !== $dimension->coordinateDimension()) {
            throw $this->cursor->createInvalidInputException('The WKT point ordinates do not match its coordinate dimension.');
        }

        return [$dimension, $ordinates];
    }

    /**
     * Consume a dimension token and return its corresponding dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension represented by the token
     *
     * @return CoordinateDimensionEnum the consumed coordinate dimension
     */
    private function consumeDimensionToken(CoordinateDimensionEnum $dimension): CoordinateDimensionEnum
    {
        $this->cursor->moveNext();

        return $dimension;
    }

    /**
     * Consume a finite numeric ordinate token.
     *
     * @param Token<int, int|string> $token numeric token to consume
     *
     * @return float|int the parsed ordinate
     */
    private function consumeNumber(Token $token): float|int
    {
        $this->cursor->moveNext();
        $value = (string) $token->value;
        if (1 === preg_match('/^[+-]?\d+$/D', $value)) {
            $integer = filter_var($value, \FILTER_VALIDATE_INT);
            if (false !== $integer) {
                return $integer;
            }
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw $this->cursor->createInvalidInputException('A WKT ordinate is outside the supported numeric range.');
        }

        return $number;
    }

    /**
     * Consume ordered ordinates from the current coordinate tuple.
     *
     * @return list<float|int>
     */
    private function consumeOrdinates(): array
    {
        $ordinates = [];
        $previousEnd = null;
        while (true) {
            $token = $this->cursor->currentToken();
            if (null === $token || !$token->isA(Lexer::T_INTEGER, Lexer::T_FLOAT)) {
                break;
            }

            if (null !== $previousEnd && $token->position <= $previousEnd) {
                throw $this->cursor->createInvalidInputException('WKT ordinates must be separated by whitespace.');
            }

            $previousEnd = $token->position + \strlen((string) $token->value);
            $ordinates[] = $this->consumeNumber($token);
        }

        return $ordinates;
    }

    /**
     * Infer XY for unmarked coordinates and reject all other coordinate dimensions.
     *
     * OGC Simple Feature Access 1.2.1 (OGC 06-103r4) defines unmarked
     * coordinates in its 2D grammar (§7.2.2). Higher coordinate dimensions
     * use separate grammars tagged Z, M, and ZM (§7.2.3 to §7.2.5).
     *
     * @param int $ordinateCount number of ordinates in the coordinate
     *
     * @throws InvalidArgumentException when a non-XY dimension lacks its marker
     */
    private function dimensionForUnmarkedCoordinate(int $ordinateCount): CoordinateDimensionEnum
    {
        return match ($ordinateCount) {
            2 => CoordinateDimensionEnum::XY,
            default => throw $this->cursor->createInvalidInputException('A WKT coordinate with a non-XY dimension requires an explicit dimension marker.'),
        };
    }
}
