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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableMultiPolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePolygonInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiPolygonImmutable as MultiPolygon2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiPolygonImmutable as MultiPolygon3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiPolygonImmutable as MultiPolygon3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiPolygonImmutable as MultiPolygon4D;

/**
 * Creates dimension-specific WKT multi-polygons.
 *
 * @internal
 */
final class WktMultiPolygonFactory
{
    /**
     * Construct a multi-polygon factory.
     *
     * @param WktPolygonFactory $polygonFactory factory used to create members
     */
    public function __construct(private WktPolygonFactory $polygonFactory)
    {
    }

    /**
     * Create a multi-polygon from its dimension and ordered polygon coordinates.
     *
     * @param CoordinateDimensionEnum           $dimension coordinate dimension of the multi-polygon
     * @param list<list<list<list<float|int>>>> $polygons  ordered polygon coordinates
     */
    public function createMultiPolygon(CoordinateDimensionEnum $dimension, array $polygons): ImmutableMultiPolygonInterface
    {
        $members = array_map(
            fn (array $rings): ImmutablePolygonInterface => $this->polygonFactory->createPolygon($dimension, $rings),
            $polygons
        );

        return match ($dimension) {
            CoordinateDimensionEnum::XY => new MultiPolygon2D($members),
            CoordinateDimensionEnum::XYZ => new MultiPolygon3DZ($members),
            CoordinateDimensionEnum::XYM => new MultiPolygon3DM($members),
            CoordinateDimensionEnum::XYZM => new MultiPolygon4D($members),
        };
    }
}
