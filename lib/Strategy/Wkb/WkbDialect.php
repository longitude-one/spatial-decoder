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
 * Standard WKB identifiers and existing LongitudeOne Point EMPTY policy.
 *
 * @internal
 */
final class WkbDialect implements BinaryDialectInterface
{
    /**
     * @param WkbReader               $reader    current bounded byte cursor
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     *
     * @return list<float>
     */
    public function readCoordinates(WkbReader $reader, CoordinateDimensionEnum $dimension): array
    {
        return $reader->readCoordinates($dimension);
    }

    /**
     * Resolve SQL/MM offsets without accepting EWKB flag bits at any depth.
     *
     * @param WkbReader $reader current bounded byte cursor
     */
    public function readHeader(WkbReader $reader): BinaryGeometryHeader
    {
        $type = $reader->readHeader();
        $dimension = CoordinateDimensionEnum::tryFromWkbCode($type - $type % 1000)
            ?? throw new InvalidArgumentException('Unsupported WKB type identifier.');
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type % 1000)
            ?? throw new InvalidArgumentException('Unsupported WKB type identifier.');

        return new BinaryGeometryHeader($geometryType, $dimension);
    }
}
