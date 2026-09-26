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

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\Point as Point2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\Point as Point3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\Point as Point3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\Point as Point4D;

/**
 * Parses the supported subset of WKT into spatial objects.
 *
 * @internal
 */
final class WktParser
{
    private Lexer $lexer;

    /**
     * Construct a parser for the supplied WKT input.
     *
     * @param string $input WKT text to parse
     */
    public function __construct(string $input)
    {
        $this->lexer = new Lexer($input);
        $this->lexer->moveNext();
    }

    /**
     * Parse a geometry from the supported WKT subset.
     */
    public function parse(): SpatialInterface
    {
        if (!$this->lexer->isNextToken(Lexer::T_POINT)) {
            throw new InvalidArgumentException('The supplied WKT geometry type is not supported.');
        }

        $this->lexer->moveNext();

        return $this->parsePoint();
    }

    /**
     * Ensure the lexer has consumed the entire input.
     *
     * @throws InvalidArgumentException when tokens remain
     */
    private function assertEnd(): void
    {
        if (null !== $this->lexer->lookahead) {
            throw new InvalidArgumentException('The WKT input contains trailing or unsupported data.');
        }
    }

    /**
     * Consume and return an optional coordinate-dimension token.
     *
     * @return CoordinateDimensionEnum|null the declared coordinate dimension
     */
    private function consumeDimension(): ?CoordinateDimensionEnum
    {
        return match ($this->lexer->lookahead?->type) {
            Lexer::T_Z => $this->consumeDimensionToken(CoordinateDimensionEnum::XYZ),
            Lexer::T_M => $this->consumeDimensionToken(CoordinateDimensionEnum::XYM),
            Lexer::T_ZM => $this->consumeDimensionToken(CoordinateDimensionEnum::XYZM),
            default => null,
        };
    }

    /**
     * Consume a dimension token and return its corresponding dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension represented by the token
     *
     * @return CoordinateDimensionEnum the consumed dimension
     */
    private function consumeDimensionToken(CoordinateDimensionEnum $dimension): CoordinateDimensionEnum
    {
        $this->lexer->moveNext();

        return $dimension;
    }

    /**
     * Consume the next finite numeric ordinate.
     *
     * @return float|int the parsed ordinate
     *
     * @throws InvalidArgumentException when the next token is not a supported number
     */
    private function consumeNumber(): float|int
    {
        $token = $this->lexer->lookahead;
        if (null === $token || !$token->isA(Lexer::T_INTEGER, Lexer::T_FLOAT)) {
            throw new InvalidArgumentException('A WKT point ordinate is missing or malformed.');
        }

        $this->lexer->moveNext();
        $value = (string) $token->value;
        if (1 === preg_match('/^[+-]?\d+$/D', $value)) {
            $integer = filter_var($value, \FILTER_VALIDATE_INT);
            if (false !== $integer) {
                return $integer;
            }
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw new InvalidArgumentException('A WKT point ordinate is outside the supported numeric range.');
        }

        return $number;
    }

    /**
     * @return list<float|int>
     */
    private function consumeOrdinates(): array
    {
        $ordinates = [];
        $previousEnd = null;
        while ($this->lexer->isNextTokenAny([Lexer::T_INTEGER, Lexer::T_FLOAT])) {
            $token = $this->lexer->lookahead;
            if (null !== $previousEnd && $token->position <= $previousEnd) {
                throw new InvalidArgumentException('WKT point ordinates must be separated by whitespace.');
            }

            $previousEnd = $token->position + \strlen((string) $token->value);
            $ordinates[] = $this->consumeNumber();
        }

        return $ordinates;
    }

    /**
     * Create an empty point with the requested coordinate dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension for the empty point
     *
     * @return SpatialInterface the empty point
     */
    private function createEmptyPoint(CoordinateDimensionEnum $dimension): SpatialInterface
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => new Point2D(),
            CoordinateDimensionEnum::XYZ => new Point3DZ(),
            CoordinateDimensionEnum::XYM => new Point3DM(),
            CoordinateDimensionEnum::XYZM => new Point4D(),
        };
    }

    /**
     * Create a point from its dimension and ordinates.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the point
     * @param list<float|int>         $ordinates ordered point ordinates
     *
     * @return SpatialInterface the created point
     */
    private function createPoint(CoordinateDimensionEnum $dimension, array $ordinates): SpatialInterface
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => new Point2D($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1)),
            CoordinateDimensionEnum::XYZ => new Point3DZ($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2)),
            CoordinateDimensionEnum::XYM => new Point3DM($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2)),
            CoordinateDimensionEnum::XYZM => new Point4D($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2), $this->ordinateAt($ordinates, 3)),
        };
    }

    /**
     * Consume the expected parenthesis token.
     *
     * @param string $symbol expected parenthesis
     *
     * @throws InvalidArgumentException when the next token does not match
     */
    private function expectSymbol(string $symbol): void
    {
        $expectedType = match ($symbol) {
            '(' => Lexer::T_OPEN_PARENTHESIS,
            ')' => Lexer::T_CLOSE_PARENTHESIS,
            default => throw new InvalidArgumentException('The WKT geometry syntax is malformed.'),
        };
        if (!$this->lexer->isNextToken($expectedType)) {
            throw new InvalidArgumentException('The WKT geometry syntax is malformed.');
        }

        $this->lexer->moveNext();
    }

    /**
     * Retrieve one ordinate, rejecting a missing value.
     *
     * @param list<float|int> $ordinates point ordinates
     * @param int             $index     ordinate index
     *
     * @return float|int the ordinate at the requested index
     *
     * @throws InvalidArgumentException when the ordinate is missing
     */
    private function ordinateAt(array $ordinates, int $index): float|int
    {
        $ordinate = $ordinates[$index] ?? null;
        if (null === $ordinate) {
            throw new InvalidArgumentException('The WKT point is missing a required ordinate.');
        }

        return $ordinate;
    }

    /**
     * Return the number of ordinates required by a coordinate dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension
     *
     * @return int required ordinate count
     */
    private function ordinateCount(CoordinateDimensionEnum $dimension): int
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => 2,
            CoordinateDimensionEnum::XYZ, CoordinateDimensionEnum::XYM => 3,
            CoordinateDimensionEnum::XYZM => 4,
        };
    }

    /**
     * Parse a point, retaining its declared or inferred coordinate dimension.
     */
    private function parsePoint(): SpatialInterface
    {
        $dimension = $this->consumeDimension();
        if ($this->lexer->isNextToken(Lexer::T_EMPTY)) {
            $this->lexer->moveNext();
            $this->assertEnd();

            return $this->createEmptyPoint($dimension ?? CoordinateDimensionEnum::XY);
        }

        $this->expectSymbol('(');
        $ordinates = $this->consumeOrdinates();
        $this->expectSymbol(')');
        $this->assertEnd();

        $dimension ??= match (\count($ordinates)) {
            2 => CoordinateDimensionEnum::XY,
            3 => CoordinateDimensionEnum::XYZ,
            4 => CoordinateDimensionEnum::XYZM,
            default => throw new InvalidArgumentException('The WKT point has an unsupported number of ordinates.'),
        };

        if (\count($ordinates) !== $this->ordinateCount($dimension)) {
            throw new InvalidArgumentException('The WKT point ordinates do not match its coordinate dimension.');
        }

        return $this->createPoint($dimension, $ordinates);
    }
}
