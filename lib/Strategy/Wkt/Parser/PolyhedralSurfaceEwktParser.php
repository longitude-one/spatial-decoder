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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutablePolyhedralSurfaceInterface;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\PolyhedralSurfaceImmutable as PolyhedralSurfaceXYZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\PolyhedralSurfaceImmutable as PolyhedralSurfaceXYZM;

/**
 * Decode an EWKT polyhedral surface using the shared polygonal grammar.
 *
 * @internal
 */
final class PolyhedralSurfaceEwktParser implements WktGeometryParserInterface
{
    /**
     * Construct the parser over the shared polygonal grammar.
     *
     * @param MultiPolygonWktParser $parser parser for the polygonal body
     */
    public function __construct(private MultiPolygonWktParser $parser)
    {
    }

    /**
     * Parse the surface representation without checking geometric validity.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a collection
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutablePolyhedralSurfaceInterface
    {
        $geometry = $this->parser->parse($inheritedDimension);

        try {
            return match ($geometry->getDimension()) {
                CoordinateDimensionEnum::XYZ => new PolyhedralSurfaceXYZ($geometry->getElements()),
                CoordinateDimensionEnum::XYZM => new PolyhedralSurfaceXYZM($geometry->getElements()),
                default => throw new InvalidArgumentException('EWKT POLYHEDRALSURFACE requires a coordinate layout containing Z.'),
            };
        } catch (SpatialTypeExceptionInterface $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }
}
