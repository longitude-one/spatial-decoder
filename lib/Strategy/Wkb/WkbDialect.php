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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkb;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryDialectInterface;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryGeometryHeader;

/**
 * Standard WKB geometry identifiers and dimensional offsets.
 *
 * @internal
 */
final class WkbDialect implements BinaryDialectInterface
{
    /**
     * Resolve SQL/MM offsets without accepting EWKB flag bits at any depth.
     *
     * @param WkbReader $reader     current bounded byte cursor
     * @param int       $parentSrid standard WKB context remains zero throughout the graph
     */
    public function readHeader(WkbReader $reader, int $parentSrid = 0): BinaryGeometryHeader
    {
        $type = $reader->readHeader();
        $dimension = CoordinateDimensionEnum::tryFromWkbCode($type - $type % 1000)
            ?? throw new InvalidArgumentException('Unsupported WKB type identifier.');
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type % 1000)
            ?? throw new InvalidArgumentException('Unsupported WKB type identifier.');

        return new BinaryGeometryHeader($geometryType, $dimension, $parentSrid);
    }
}
