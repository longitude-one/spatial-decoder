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

namespace LongitudeOne\SpatialDecoder\Strategy\MySQL;

use LongitudeOne\Core\Enum\ByteOrderEnum;
use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryDialectInterface;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryGeometryHeader;
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;

/**
 * MySQL little-endian XY geometry headers.
 *
 * @internal
 */
final class MySQLDialect implements BinaryDialectInterface
{
    /**
     * Validate the header and inspect the body for unsupported empty geometries.
     *
     * @param WkbReader $reader     current bounded byte cursor
     * @param int       $parentSrid SRID inherited from the column prefix
     */
    public function readHeader(WkbReader $reader, int $parentSrid = 0): BinaryGeometryHeader
    {
        $type = $reader->readHeader(ByteOrderEnum::LITTLE_ENDIAN);
        if ($type > 7) {
            throw new InvalidArgumentException('Unsupported MySQL geometry type.');
        }
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type)
            ?? throw new InvalidArgumentException('Unsupported MySQL geometry type.');
        if (GeometryTypeEnum::POINT !== $geometryType && GeometryTypeEnum::GEOMETRYCOLLECTION !== $geometryType && 0 === (clone $reader)->readUnsignedInteger()) {
            throw new InvalidArgumentException('Only MySQL GeometryCollection may be empty.');
        }

        return new BinaryGeometryHeader($geometryType, CoordinateDimensionEnum::XY, $parentSrid);
    }
}
