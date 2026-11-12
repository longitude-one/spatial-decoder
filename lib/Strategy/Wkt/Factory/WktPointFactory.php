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
use LongitudeOne\SpatialDecoder\Exception\LogicException;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\PointImmutable as Point2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\PointImmutable as Point3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\PointImmutable as Point3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\PointImmutable as Point4D;

/**
 * Creates dimension-specific WKT points.
 *
 * @internal
 */
final class WktPointFactory
{
    /**
     * Create an empty point with the requested coordinate dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the point
     *
     * @return ImmutablePointInterface the created empty point
     */
    public function createEmptyPoint(CoordinateDimensionEnum $dimension): ImmutablePointInterface
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => new Point2D(),
            CoordinateDimensionEnum::XYZ => new Point3DZ(),
            CoordinateDimensionEnum::XYM => new Point3DM(),
            CoordinateDimensionEnum::XYZM => new Point4D(),
        };
    }

    /**
     * Create a point from its dimension and ordinates.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the point
     * @param list<float|int>         $ordinates ordered point ordinates
     */
    public function createPoint(CoordinateDimensionEnum $dimension, array $ordinates): ImmutablePointInterface
    {
        return match ($dimension) {
            CoordinateDimensionEnum::XY => new Point2D($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1)),
            CoordinateDimensionEnum::XYZ => new Point3DZ($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2)),
            CoordinateDimensionEnum::XYM => new Point3DM($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2)),
            CoordinateDimensionEnum::XYZM => new Point4D($this->ordinateAt($ordinates, 0), $this->ordinateAt($ordinates, 1), $this->ordinateAt($ordinates, 2), $this->ordinateAt($ordinates, 3)),
        };
    }

    /**
     * Retrieve one ordinate from a validated coordinate.
     *
     * @param list<float|int> $ordinates point ordinates
     * @param int             $index     ordinate index
     *
     * @return float|int the ordinate at the requested index
     */
    private function ordinateAt(array $ordinates, int $index): float|int
    {
        $ordinate = $ordinates[$index] ?? null;
        if (null === $ordinate) {
            throw new LogicException('A validated WKT coordinate is missing an ordinate.');
        }

        return $ordinate;
    }
}
