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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableCompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableCurveInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\CompoundCurveImmutable as CompoundCurve2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\CompoundCurveImmutable as CompoundCurve3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\CompoundCurveImmutable as CompoundCurve3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\CompoundCurveImmutable as CompoundCurve4D;

/**
 * Creates dimension-specific WKT compound curves.
 *
 * @internal
 */
final class WktCompoundCurveFactory
{
    /**
     * Create a compound curve from its dimension and ordered components.
     *
     * @param CoordinateDimensionEnum   $dimension coordinate dimension of the compound curve
     * @param ImmutableCurveInterface[] $curves    ordered line-string and circular-string components
     */
    public function createCompoundCurve(CoordinateDimensionEnum $dimension, array $curves): ImmutableCompoundCurveInterface
    {
        try {
            return match ($dimension) {
                CoordinateDimensionEnum::XY => new CompoundCurve2D($curves),
                CoordinateDimensionEnum::XYZ => new CompoundCurve3DZ($curves),
                CoordinateDimensionEnum::XYM => new CompoundCurve3DM($curves),
                CoordinateDimensionEnum::XYZM => new CompoundCurve4D($curves),
            };
        } catch (SpatialTypeExceptionInterface $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }
}
