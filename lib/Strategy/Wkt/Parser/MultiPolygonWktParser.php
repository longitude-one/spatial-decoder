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
 * Parses WKT multi-polygon representations.
 *
 * @internal
 */
final class MultiPolygonWktParser
{
    private PolygonWktParser $polygonParser;

    /**
     * Construct a multi-polygon parser.
     *
     * @param WktTokenCursor          $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader     $coordinateReader reader for coordinate values and dimensions
     * @param WktSpatialObjectFactory $factory          factory for the decoded multi-polygon
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktSpatialObjectFactory $factory
    ) {
        $this->polygonParser = new PolygonWktParser($cursor, $coordinateReader, $factory);
    }

    /**
     * Parse a multi-polygon representation.
     *
     * @return SpatialInterface the decoded multi-polygon
     */
    public function parse(): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension();
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();
            $this->cursor->assertEnd();

            return $this->factory->createMultiPolygon($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        $polygons = [];
        $this->cursor->expectSymbol('(');
        do {
            [$dimension, $rings] = $this->consumePolygonMember($dimension);
            $polygons[] = $rings;

            $hasNextPolygon = $this->cursor->isNextToken(Lexer::T_COMMA);
            if ($hasNextPolygon) {
                $this->cursor->moveNext();
            }
        } while ($hasNextPolygon);

        $this->cursor->expectSymbol(')');
        $this->cursor->assertEnd();

        return $this->factory->createMultiPolygon($dimension ?? CoordinateDimensionEnum::XY, $polygons);
    }

    /**
     * Consume one polygon member, preserving empty members.
     *
     * @param CoordinateDimensionEnum|null $dimension declared or inferred dimension
     *
     * @return array{CoordinateDimensionEnum|null, list<list<list<float|int>>>} effective dimension and rings
     */
    private function consumePolygonMember(?CoordinateDimensionEnum $dimension): array
    {
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return [$dimension, []];
        }

        return $this->polygonParser->consumePolygon($dimension);
    }
}
