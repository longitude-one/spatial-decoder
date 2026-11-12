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
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

/**
 * Parses WKT line-string representations.
 *
 * @internal
 */
final class LineStringWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a line-string parser.
     *
     * @param WktTokenCursor       $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader  $coordinateReader reader for coordinate values and dimensions
     * @param WktLineStringFactory $factory          factory for the decoded line string
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktLineStringFactory $factory
    ) {
    }

    /**
     * Parse a line-string representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return ImmutableSpatialInterface the decoded line string
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutableSpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createLineString($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        [$dimension, $coordinates] = $this->coordinateReader->consumeCoordinateSequence($dimension);

        return $this->factory->createLineString($dimension, $coordinates);
    }
}
