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
use LongitudeOne\SpatialTypes\Interfaces\ImmutableCollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\GeometryCollectionImmutable as GeometryCollection2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\GeometryCollectionImmutable as GeometryCollection3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\GeometryCollectionImmutable as GeometryCollection3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\GeometryCollectionImmutable as GeometryCollection4D;

/**
 * Creates dimension-specific WKT geometry collections.
 *
 * @internal
 */
final class WktGeometryCollectionFactory
{
    /**
     * Create a geometry collection from same-dimension spatial objects.
     *
     * @param CoordinateDimensionEnum     $dimension coordinate dimension of the collection
     * @param ImmutableSpatialInterface[] $elements  ordered collection members
     */
    public function createGeometryCollection(CoordinateDimensionEnum $dimension, array $elements): ImmutableCollectionInterface
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => new GeometryCollection2D(0, $elements),
            CoordinateDimensionEnum::XYZ => new GeometryCollection3DZ(0, $elements),
            CoordinateDimensionEnum::XYM => new GeometryCollection3DM(0, $elements),
            CoordinateDimensionEnum::XYZM => new GeometryCollection4D(0, $elements),
        };
    }
}
