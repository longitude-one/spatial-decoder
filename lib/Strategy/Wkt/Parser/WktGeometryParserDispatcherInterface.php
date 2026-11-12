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
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

/**
 * Dispatches the next geometry from a WKT token stream.
 *
 * @internal
 */
interface WktGeometryParserDispatcherInterface
{
    /**
     * Parse the next geometry, optionally inheriting a collection dimension.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension collection dimension to apply to unmarked members
     *
     * @return ImmutableSpatialInterface the decoded geometry
     */
    public function parseNext(?CoordinateDimensionEnum $inheritedDimension): ImmutableSpatialInterface;
}
