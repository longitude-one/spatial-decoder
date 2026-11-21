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

namespace LongitudeOne\SpatialDecoder\Strategy\Ewkb;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryDialectInterface;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryGeometryHeader;
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;

/**
 * EWKB dimensional headers and spatial references scoped to each geometry.
 *
 * @internal
 */
final class EwkbDialect implements BinaryDialectInterface
{
    /**
     * Preserve explicit SRIDs and inherit only from the immediate parent.
     *
     * @param WkbReader $reader     current bounded byte cursor
     * @param int       $parentSrid immediate parent's effective spatial reference, or 0 at the root
     */
    public function readHeader(WkbReader $reader, int $parentSrid = 0): BinaryGeometryHeader
    {
        $word = $reader->readHeader();
        $hasSrid = 0 !== ($word & 0x20000000);
        $type = $word & ~0xE0000000;
        $offset = CoordinateDimensionEnum::tryFromWkbCode($type - $type % 1000)
            ?? throw new InvalidArgumentException('Unsupported EWKB type identifier.');
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type % 1000)
            ?? throw new InvalidArgumentException('Unsupported EWKB type identifier.');
        $hasZ = $offset->hasZ() || 0 !== ($word & 0x80000000);
        $hasM = $offset->hasM() || 0 !== ($word & 0x40000000);
        $dimension = match (true) {
            $hasZ && $hasM => CoordinateDimensionEnum::XYZM,
            $hasZ => CoordinateDimensionEnum::XYZ,
            $hasM => CoordinateDimensionEnum::XYM,
            default => CoordinateDimensionEnum::XY,
        };
        $srid = $hasSrid ? $reader->readUnsignedInteger() : $parentSrid;

        return new BinaryGeometryHeader($geometryType, $dimension, $srid);
    }
}
