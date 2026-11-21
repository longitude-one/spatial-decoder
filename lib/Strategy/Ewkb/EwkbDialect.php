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
 * EWKB dimensional headers; explicit nested SRID metadata is not yet supported.
 *
 * @internal
 */
final class EwkbDialect implements BinaryDialectInterface
{
    private bool $root = true;

    private int $srid = 0;

    /**
     * Preserve an unsigned root SRID without interpreting it as a CRS operation.
     *
     * @param WkbReader $reader current bounded byte cursor
     */
    public function readHeader(WkbReader $reader): BinaryGeometryHeader
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
        if (!$this->root && $hasSrid) {
            throw new InvalidArgumentException('Nested EWKB SRID metadata is not supported yet.');
        }
        $this->root = false;
        $this->srid = $hasSrid ? $reader->readUnsignedInteger() : $this->srid;

        return new BinaryGeometryHeader($geometryType, $dimension, $this->srid);
    }
}
