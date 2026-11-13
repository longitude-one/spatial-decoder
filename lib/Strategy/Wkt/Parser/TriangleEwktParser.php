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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableTriangleInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\TriangleImmutable as TriangleXY;
use LongitudeOne\SpatialTypes\Types\Dimension3m\Geometry\TriangleImmutable as TriangleXYM;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\TriangleImmutable as TriangleXYZ;
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\TriangleImmutable as TriangleXYZM;

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
     * Parse the surface representation without checking geometric validity.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a collection
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutableTriangleInterface
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
