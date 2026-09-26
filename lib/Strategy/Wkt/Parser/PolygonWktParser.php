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
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktSpatialObjectFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Parses WKT polygon representations.
 *
 * @internal
 */
final class PolygonWktParser
{
    /**
     * Construct a polygon parser.
     *
     * @param WktTokenCursor          $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader     $coordinateReader reader for coordinate values and dimensions
     * @param WktSpatialObjectFactory $factory          factory for the decoded polygon
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktSpatialObjectFactory $factory
    ) {
    }

    /**
     * Consume a polygon body and its rings.
     *
     * @param CoordinateDimensionEnum|null $dimension declared or inferred dimension
     *
     * @return array{CoordinateDimensionEnum|null, list<list<list<float|int>>>} effective dimension and rings
     */
    public function consumePolygon(?CoordinateDimensionEnum $dimension): array
    {
        $rings = [];
        $this->cursor->expectSymbol('(');
        do {
            [$dimension, $coordinates] = $this->coordinateReader->consumeCoordinateSequence($dimension);
            $this->assertRing($coordinates);
            $rings[] = $coordinates;

            $hasNextRing = $this->cursor->isNextToken(Lexer::T_COMMA);
            if ($hasNextRing) {
                $this->cursor->moveNext();
            }
        } while ($hasNextRing);

        $this->cursor->expectSymbol(')');

        return [$dimension, $rings];
    }

    /**
     * Parse a polygon representation.
     *
     * @return SpatialInterface the decoded polygon
     */
    public function parse(): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension();
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();
            $this->cursor->assertEnd();

            return $this->factory->createPolygon($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        [$dimension, $rings] = $this->consumePolygon($dimension);
        $this->cursor->assertEnd();

        return $this->factory->createPolygon($dimension ?? CoordinateDimensionEnum::XY, $rings);
    }

    /**
     * Ensure that a coordinate sequence forms a closed linear ring.
     *
     * @param list<list<float|int>> $coordinates ordered ring coordinates
     */
    private function assertRing(array $coordinates): void
    {
        if (\count($coordinates) < 4 || $coordinates[0] != $coordinates[\count($coordinates) - 1]) {
            throw $this->cursor->createInvalidInputException('A WKT polygon ring must contain at least four coordinates and be closed.');
        }
    }
}
