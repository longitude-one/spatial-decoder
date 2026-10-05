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
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktCircularStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Parses WKT circular-string representations.
 *
 * @internal
 */
final class CircularStringWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a circular-string parser.
     *
     * @param WktTokenCursor           $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader      $coordinateReader reader for coordinate values and dimensions
     * @param WktCircularStringFactory $factory          factory for the decoded circular string
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktCircularStringFactory $factory
    ) {
    }

    /**
     * Parse a circular-string representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return SpatialInterface the decoded circular string
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createCircularString($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        [$dimension, $coordinates] = $this->coordinateReader->consumeCoordinateSequence($dimension);

        return $this->factory->createCircularString($dimension, $coordinates);
    }
}
