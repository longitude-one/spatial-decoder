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
use LongitudeOne\SpatialTypes\Interfaces\ImmutableLineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\LineStringImmutable as LineString2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\LineStringImmutable as LineString3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\LineStringImmutable as LineString3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\LineStringImmutable as LineString4D;

/**
 * Creates dimension-specific WKT line strings.
 *
 * @internal
 */
final class WktLineStringFactory
{
    /**
     * Construct a line-string factory.
     *
     * @param WktPointFactory $pointFactory factory used to create line vertices
     */
    public function __construct(private WktPointFactory $pointFactory)
    {
    }

    /**
     * Create a line string from its dimension and coordinate sequence.
     *
     * @param CoordinateDimensionEnum $dimension   coordinate dimension of the line string
     * @param list<list<float|int>>   $coordinates ordered line-string coordinates
     */
    public function createLineString(CoordinateDimensionEnum $dimension, array $coordinates): ImmutableLineStringInterface
    {
        // Build dimension-specific points before assembling the line string.
        $points = array_map(
            fn (array $ordinates): ImmutablePointInterface => $this->pointFactory->createPoint($dimension, $ordinates),
            $coordinates
        );

        return match ($dimension) {
            CoordinateDimensionEnum::XY => new LineString2D($points),
            CoordinateDimensionEnum::XYZ => new LineString3DZ($points),
            CoordinateDimensionEnum::XYM => new LineString3DM($points),
            CoordinateDimensionEnum::XYZM => new LineString4D($points),
        };
    }
}
