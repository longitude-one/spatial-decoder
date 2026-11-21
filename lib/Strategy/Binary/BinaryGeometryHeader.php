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

namespace LongitudeOne\SpatialDecoder\Strategy\Binary;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;

/**
 * Representation-independent geometry dispatch metadata.
 *
 * @internal
 */
final readonly class BinaryGeometryHeader
{
    /**
     * @param GeometryTypeEnum        $type      declared geometry family
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     * @param int                     $srid      effective root spatial reference
     */
    public function __construct(public GeometryTypeEnum $type, public CoordinateDimensionEnum $dimension, public int $srid = 0)
    {
    }
}
