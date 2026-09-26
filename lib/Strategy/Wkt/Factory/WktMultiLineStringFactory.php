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
use LongitudeOne\SpatialTypes\Interfaces\MultiLineStringInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\MultiLineString as MultiLineString2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\MultiLineString as MultiLineString3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\MultiLineString as MultiLineString3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\MultiLineString as MultiLineString4D;

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
    public function createMultiLineString(CoordinateDimensionEnum $dimension, array $lineStrings): MultiLineStringInterface
    {
        // Build every member with the multi-line string's coordinate dimension.
        $members = array_map(
            fn (array $coordinates): LineStringInterface => $this->lineStringFactory->createLineString($dimension, $coordinates),
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
