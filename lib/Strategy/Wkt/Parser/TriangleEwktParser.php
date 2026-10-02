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
use LongitudeOne\SpatialTypes\Interfaces\TriangleInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\Triangle as TriangleXY;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\Triangle as TriangleXYM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\Triangle as TriangleXYZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\Triangle as TriangleXYZM;

/**
 * Decode an EWKT triangle using the shared polygonal grammar.
 *
 * @internal
 */
final class TriangleEwktParser implements WktGeometryParserInterface
{
    /**
     * Construct the parser over the shared polygonal grammar.
     *
     * @param PolygonWktParser $parser parser for the polygonal body
     */
    public function __construct(private PolygonWktParser $parser)
    {
    }

    /**
     * Parse and validate the concrete surface before returning it.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a collection
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): TriangleInterface
    {
        $geometry = $this->parser->parse($inheritedDimension);

        try {
            return match ($geometry->getDimension()) {
                CoordinateDimensionEnum::XY => new TriangleXY($geometry->getRings()),
                CoordinateDimensionEnum::XYZ => new TriangleXYZ($geometry->getRings()),
                CoordinateDimensionEnum::XYM => new TriangleXYM($geometry->getRings()),
                CoordinateDimensionEnum::XYZM => new TriangleXYZM($geometry->getRings()),
            };
        } catch (SpatialTypeExceptionInterface $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }
}
