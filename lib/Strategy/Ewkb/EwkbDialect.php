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
 * Initial XY EWKB header support; nested SRID metadata is not yet supported.
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
        $type = $word & ~0x20000000;
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type)
            ?? throw new InvalidArgumentException('Unsupported EWKB XY type identifier.');
        if (!$this->root && $hasSrid) {
            throw new InvalidArgumentException('Nested EWKB SRID metadata is not supported yet.');
        }
        $this->root = false;
        $this->srid = $hasSrid ? $reader->readUnsignedInteger() : $this->srid;

        return new BinaryGeometryHeader($geometryType, CoordinateDimensionEnum::XY, $this->srid);
    }
}
