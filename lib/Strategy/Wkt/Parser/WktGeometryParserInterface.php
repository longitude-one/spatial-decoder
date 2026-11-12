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
 * Parses one geometry body from the shared WKT token stream.
 *
 * @internal
 */
interface WktGeometryParserInterface
{
    /**
     * Parse a geometry, optionally inheriting a collection dimension.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return ImmutableSpatialInterface the decoded geometry
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutableSpatialInterface;
}
