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
 * Parses WKT point representations.
 *
 * @internal
 */
final class PointWktParser
{
    /**
     * Construct a point parser.
     *
     * @param WktTokenCursor          $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader     $coordinateReader reader for coordinate values and dimensions
     * @param WktSpatialObjectFactory $factory          factory for the decoded point
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktSpatialObjectFactory $factory
    ) {
    }

    /**
     * Parse a point representation.
     *
     * @return SpatialInterface the decoded point
     */
    public function parse(): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension();
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();
            $this->cursor->assertEnd();

            return $this->factory->createEmptyPoint($dimension ?? CoordinateDimensionEnum::XY);
        }

        [$dimension, $ordinates] = $this->coordinateReader->consumePointCoordinate($dimension);
        $this->cursor->assertEnd();

        return $this->factory->createPoint($dimension, $ordinates);
    }
}
