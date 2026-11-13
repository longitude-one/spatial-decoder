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
use LongitudeOne\SpatialTypes\Interfaces\ImmutableMultiLineStringInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiLineStringImmutable as MultiLineString2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiLineStringImmutable as MultiLineString3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiLineStringImmutable as MultiLineString3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiLineStringImmutable as MultiLineString4D;

/**
 * Creates dimension-specific WKT multi-line strings.
 *
 * @internal
 */
final class WktMultiLineStringFactory
{
    /**
     * Construct a multi-line-string factory.
     *
     * @param WktLineStringFactory $lineStringFactory factory used to create members
     */
    public function __construct(private WktLineStringFactory $lineStringFactory)
    {
    }

    /**
     * Create a multi-line string from its dimension and coordinate sequences.
     *
     * @param CoordinateDimensionEnum     $dimension   coordinate dimension of the multi-line string
     * @param list<list<list<float|int>>> $lineStrings ordered line-string coordinates
     */
    public function createMultiLineString(CoordinateDimensionEnum $dimension, array $lineStrings): ImmutableMultiLineStringInterface
    {
        // Build every member with the multi-line string's coordinate dimension.
        $members = array_map(
            fn (array $coordinates): ImmutableLineStringInterface => $this->lineStringFactory->createLineString($dimension, $coordinates),
            $lineStrings
        );

        return match ($dimension) {
            CoordinateDimensionEnum::XY => new MultiLineString2D($members),
            CoordinateDimensionEnum::XYZ => new MultiLineString3DZ($members),
            CoordinateDimensionEnum::XYM => new MultiLineString3DM($members),
            CoordinateDimensionEnum::XYZM => new MultiLineString4D($members),
        };
    }
}
