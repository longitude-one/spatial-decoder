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

use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;

/**
 * Isolate representation-specific header interpretation from geometry bodies.
 *
 * @internal
 */
interface BinaryDialectInterface
{
    /**
     * Interpret every root/member header using the selected representation.
     *
     * @param WkbReader $reader     current bounded byte cursor
     * @param int       $parentSrid immediate parent's effective spatial reference, or 0 at the root
     */
    public function readHeader(WkbReader $reader, int $parentSrid = 0): BinaryGeometryHeader;
}
