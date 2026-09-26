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
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

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
     * @return SpatialInterface the decoded line string
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): SpatialInterface
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
