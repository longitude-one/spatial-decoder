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
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;

/**
 * Isolate header interpretation and ordinate/EMPTY policy from geometry bodies.
 *
 * @internal
 */
interface BinaryDialectInterface
{
    /**
     * @param WkbReader               $reader    current bounded byte cursor
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     *
     * @return list<float>
     */
    public function readCoordinates(WkbReader $reader, CoordinateDimensionEnum $dimension): array;

    /**
     * Interpret every root/member header using the selected representation.
     *
     * @param WkbReader $reader current bounded byte cursor
     */
    public function readHeader(WkbReader $reader): BinaryGeometryHeader;
}
