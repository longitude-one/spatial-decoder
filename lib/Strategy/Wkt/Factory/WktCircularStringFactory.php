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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\CircularString as CircularString2D;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\CircularString as CircularString3DM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\CircularString as CircularString3DZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\CircularString as CircularString4D;

/**
 * Creates dimension-specific WKT circular strings.
 *
 * @internal
 */
final class WktCircularStringFactory
{
    /**
     * Construct a circular-string factory.
     *
     * @param WktPointFactory $pointFactory factory used to create control points
     */
    public function __construct(private WktPointFactory $pointFactory)
    {
    }

    /**
     * Create a circular string from its dimension and coordinate sequence.
     *
     * @param CoordinateDimensionEnum $dimension   coordinate dimension of the circular string
     * @param list<list<float|int>>   $coordinates ordered circular-string coordinates
     */
    public function createCircularString(CoordinateDimensionEnum $dimension, array $coordinates): CircularStringInterface
    {
        try {
            // Build dimension-specific points before assembling the circular string.
            $points = array_map(
                fn (array $ordinates): PointInterface => $this->pointFactory->createPoint($dimension, $ordinates),
                $coordinates
            );

            return match ($dimension) {
                CoordinateDimensionEnum::XY => new CircularString2D($points),
                CoordinateDimensionEnum::XYZ => new CircularString3DZ($points),
                CoordinateDimensionEnum::XYM => new CircularString3DM($points),
                CoordinateDimensionEnum::XYZM => new CircularString4D($points),
            };
        } catch (SpatialTypeExceptionInterface $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }
}
