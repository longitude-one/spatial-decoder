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
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\LineString as LineString2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\LineString as LineString3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\LineString as LineString3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\LineString as LineString4D;

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
    public function createLineString(CoordinateDimensionEnum $dimension, array $coordinates): LineStringInterface
    {
        // Build dimension-specific points before assembling the line string.
        $points = array_map(
            fn (array $ordinates): PointInterface => $this->pointFactory->createPoint($dimension, $ordinates),
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
