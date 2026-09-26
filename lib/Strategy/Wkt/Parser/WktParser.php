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

use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktSpatialObjectFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Dispatches supported WKT geometry types to their parsers.
 *
 * @internal
 */
final class WktParser
{
    private WktCoordinateReader $coordinateReader;

    private WktTokenCursor $cursor;

    private WktSpatialObjectFactory $factory;

    /**
     * Construct a parser for the supplied WKT input.
     *
     * @param string $input WKT text to parse
     */
    public function __construct(string $input)
    {
        $this->cursor = new WktTokenCursor($input);
        // The coordinate reader consumes the same token stream as the geometry parser.
        $this->coordinateReader = new WktCoordinateReader($this->cursor);
        $this->factory = new WktSpatialObjectFactory();
    }

    /**
     * Parse a geometry from the supported WKT subset.
     */
    public function parse(): SpatialInterface
    {
        $geometryType = $this->cursor->currentToken()?->type;
        $this->cursor->moveNext();

        // Delegate type-specific syntax while sharing coordinate reading and object creation.
        return match ($geometryType) {
            Lexer::T_POINT => (new PointWktParser($this->cursor, $this->coordinateReader, $this->factory))->parse(),
            Lexer::T_LINESTRING => (new LineStringWktParser($this->cursor, $this->coordinateReader, $this->factory))->parse(),
            Lexer::T_MULTILINESTRING => (new MultiLineStringWktParser($this->cursor, $this->coordinateReader, $this->factory))->parse(),
            default => throw $this->cursor->createInvalidInputException('The supplied WKT geometry type is not supported.'),
        };
    }
}
