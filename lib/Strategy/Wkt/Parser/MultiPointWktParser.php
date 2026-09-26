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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPointFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Parses WKT multi-point representations.
 *
 * @internal
 */
final class MultiPointWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a multi-point parser.
     *
     * @param WktTokenCursor       $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader  $coordinateReader reader for coordinate values and dimensions
     * @param WktMultiPointFactory $factory          factory for the decoded multi-point
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktMultiPointFactory $factory
    ) {
    }

    /**
     * Parse a multi-point representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension collection dimension for unmarked coordinates
     *
     * @return SpatialInterface the decoded multi-point
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createMultiPoint($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        $points = [];
        $memberSyntax = null;
        $this->cursor->expectSymbol('(');
        do {
            if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
                $this->cursor->moveNext();
                $points[] = [];
                continue;
            }

            $parenthesized = $this->cursor->isNextToken(Lexer::T_OPEN_PARENTHESIS);
            if (null !== $memberSyntax && $memberSyntax !== $parenthesized) {
                throw $this->cursor->createInvalidInputException('A WKT multi-point must use one point-member representation consistently.');
            }
            $memberSyntax = $parenthesized;

            [$dimension, $ordinates] = $parenthesized
                ? $this->coordinateReader->consumePointCoordinate($dimension)
                : $this->coordinateReader->consumeBarePointCoordinate($dimension);
            $points[] = $ordinates;
        } while ($this->consumeMemberSeparator());

        $this->cursor->expectSymbol(')');

        return $this->factory->createMultiPoint($dimension ?? CoordinateDimensionEnum::XY, $points);
    }

    /** Consume a comma before the next point member, if present. */
    private function consumeMemberSeparator(): bool
    {
        if (!$this->cursor->isNextToken(Lexer::T_COMMA)) {
            return false;
        }

        $this->cursor->moveNext();

        return true;
    }
}
