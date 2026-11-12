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

use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

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
    public function parse(): ImmutableSpatialInterface
    {
        $geometry = $this->registry->parseNext(null);
        $this->cursor->assertEnd();

        return $geometry;
    }
}
