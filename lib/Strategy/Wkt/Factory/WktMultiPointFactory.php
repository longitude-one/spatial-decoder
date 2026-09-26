<?php
/**
 * This file is part of the spatial-decoder project.
 *
 * PHP 8.4 | 8.5
 *
 * Copyright Alexandre Tranchant <alexandre.tranchant@gmail.com> 2026
 * Copyright Longitude One 2026
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

declare(strict_types=1);

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialTypes\Interfaces\MultiPointInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiPoint as MultiPoint2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiPoint as MultiPoint3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiPoint as MultiPoint3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiPoint as MultiPoint4D;

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
    public function createMultiPoint(CoordinateDimensionEnum $dimension, array $points): MultiPointInterface
    {
        $members = array_map(
            fn (array $ordinates): PointInterface => [] === $ordinates
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
