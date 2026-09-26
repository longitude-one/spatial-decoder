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

use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Dispatches supported WKT geometry types to their parsers.
 *
 * @internal
 */
final class WktParser
{
    /**
     * Construct a root parser over the shared token stream.
     *
     * @param WktTokenCursor                       $cursor   lexer cursor for the WKT input
     * @param WktGeometryParserDispatcherInterface $registry geometry dispatcher for the input
     */
    public function __construct(private WktTokenCursor $cursor, private WktGeometryParserDispatcherInterface $registry)
    {
    }

    /**
     * Parse a geometry from the supported WKT subset.
     */
    public function parse(): SpatialInterface
    {
        $geometry = $this->registry->parseNext(null);
        $this->cursor->assertEnd();

        return $geometry;
    }
}
