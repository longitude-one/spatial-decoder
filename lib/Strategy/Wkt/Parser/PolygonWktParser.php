<?php

/*
 * This file is part of the LongitudeOne Spatial project.
 *
 * Copyright (c) 2026 Alexandre Tranchant <alexandre.tranchant@gmail.com>
 *
 * See LICENSE for copyright and license information.
 *
 */

declare(strict_types=1);

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPolygonFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePolygonInterface;

/**
 * Parses WKT polygon representations.
 *
 * @internal
 */
final class PolygonWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a polygon parser.
     *
     * @param WktTokenCursor      $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader $coordinateReader reader for coordinate values and dimensions
     * @param WktPolygonFactory   $factory          factory for the decoded polygon
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktPolygonFactory $factory
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
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return ImmutablePolygonInterface the decoded polygon
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutablePolygonInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createPolygon($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        [$dimension, $rings] = $this->consumePolygon($dimension);

        return $this->factory->createPolygon($dimension ?? CoordinateDimensionEnum::XY, $rings);
    }
}
