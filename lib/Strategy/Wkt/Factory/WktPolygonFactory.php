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
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\Polygon as Polygon2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\Polygon as Polygon3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\Polygon as Polygon3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\Polygon as Polygon4D;

/**
 * Creates dimension-specific WKT polygons.
 *
 * @internal
 */
final class WktPolygonFactory
{
    /**
     * Construct a polygon factory.
     *
     * @param WktLineStringFactory $lineStringFactory factory used to create rings
     */
    public function __construct(private WktLineStringFactory $lineStringFactory)
    {
    }

    /**
     * Create a polygon from its dimension and ordered ring coordinates.
     *
     * @param CoordinateDimensionEnum     $dimension coordinate dimension of the polygon
     * @param list<list<list<float|int>>> $rings     ordered ring coordinates
     */
    public function createPolygon(CoordinateDimensionEnum $dimension, array $rings): PolygonInterface
    {
        $lineStrings = array_map(
            fn (array $coordinates): LineStringInterface => $this->lineStringFactory->createLineString($dimension, $coordinates),
            $rings
        );

        return match ($dimension) {
            CoordinateDimensionEnum::XY => new Polygon2D($lineStrings),
            CoordinateDimensionEnum::XYZ => new Polygon3DZ($lineStrings),
            CoordinateDimensionEnum::XYM => new Polygon3DM($lineStrings),
            CoordinateDimensionEnum::XYZM => new Polygon4D($lineStrings),
        };
    }
}
