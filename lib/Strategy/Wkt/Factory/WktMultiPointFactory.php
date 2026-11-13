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
use LongitudeOne\SpatialTypes\Interfaces\ImmutableMultiPointInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiPointImmutable as MultiPoint2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiPointImmutable as MultiPoint3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiPointImmutable as MultiPoint3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiPointImmutable as MultiPoint4D;

/**
 * Creates dimension-specific WKT multi-points.
 *
 * @internal
 */
final class WktMultiPointFactory
{
    /**
     * Construct a multi-point factory.
     *
     * @param WktPointFactory $pointFactory factory used to create point members
     */
    public function __construct(private WktPointFactory $pointFactory)
    {
    }

    /**
     * Create a multi-point from its dimension and ordered point ordinates.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the multi-point
     * @param list<list<float|int>>   $points    ordered point ordinates; an empty list represents an empty point member
     */
    public function createMultiPoint(CoordinateDimensionEnum $dimension, array $points): ImmutableMultiPointInterface
    {
        $members = array_map(
            fn (array $ordinates): ImmutablePointInterface => [] === $ordinates
                ? $this->pointFactory->createEmptyPoint($dimension)
                : $this->pointFactory->createPoint($dimension, $ordinates),
            $points
        );

        return match ($dimension) {
            CoordinateDimensionEnum::XY => new MultiPoint2D($members),
            CoordinateDimensionEnum::XYZ => new MultiPoint3DZ($members),
            CoordinateDimensionEnum::XYM => new MultiPoint3DM($members),
            CoordinateDimensionEnum::XYZM => new MultiPoint4D($members),
        };
    }
}
