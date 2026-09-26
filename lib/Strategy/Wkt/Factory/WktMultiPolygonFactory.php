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
use LongitudeOne\SpatialTypes\Interfaces\MultiPolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiPolygon as MultiPolygon2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiPolygon as MultiPolygon3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiPolygon as MultiPolygon3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiPolygon as MultiPolygon4D;

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
    public function createMultiPolygon(CoordinateDimensionEnum $dimension, array $polygons): MultiPolygonInterface
    {
        $members = array_map(
            fn (array $rings): PolygonInterface => $this->polygonFactory->createPolygon($dimension, $rings),
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
