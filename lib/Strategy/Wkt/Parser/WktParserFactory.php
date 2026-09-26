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

use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktGeometryCollectionFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPointFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPolygonFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPointFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPolygonFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;

/**
 * Builds the WKT parser graph and its shared token stream.
 *
 * @internal
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class WktParserFactory
{
    /**
     * Create a parser for the supplied WKT input.
     *
     * @param string $input WKT text to parse
     */
    public function create(string $input): WktParser
    {
        $cursor = new WktTokenCursor($input);
        $coordinateReader = new WktCoordinateReader($cursor);
        $pointFactory = new WktPointFactory();
        $lineStringFactory = new WktLineStringFactory($pointFactory);
        $polygonFactory = new WktPolygonFactory($lineStringFactory);
        $registry = new WktGeometryParserRegistry($cursor);

        $registry->register(Lexer::T_POINT, new PointWktParser($cursor, $coordinateReader, $pointFactory));
        $registry->register(Lexer::T_LINESTRING, new LineStringWktParser($cursor, $coordinateReader, $lineStringFactory));
        $registry->register(Lexer::T_MULTIPOINT, new MultiPointWktParser($cursor, $coordinateReader, new WktMultiPointFactory($pointFactory)));
        $registry->register(Lexer::T_MULTILINESTRING, new MultiLineStringWktParser($cursor, $coordinateReader, new WktMultiLineStringFactory($lineStringFactory)));
        $registry->register(Lexer::T_POLYGON, new PolygonWktParser($cursor, $coordinateReader, $polygonFactory));
        $registry->register(
            Lexer::T_MULTIPOLYGON,
            new MultiPolygonWktParser($cursor, $coordinateReader, new WktMultiPolygonFactory($polygonFactory), $polygonFactory)
        );
        $registry->register(
            Lexer::T_GEOMETRYCOLLECTION,
            new GeometryCollectionWktParser($cursor, $coordinateReader, new WktGeometryCollectionFactory(), $registry)
        );

        return new WktParser($cursor, $registry);
    }
}
