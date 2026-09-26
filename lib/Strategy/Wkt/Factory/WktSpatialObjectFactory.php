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
use LongitudeOne\SpatialTypes\Interfaces\MultiPolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;

/**
 * Creates supported spatial objects from parsed WKT coordinates.
 *
 * @internal
 */
final class WktSpatialObjectFactory
{
    private readonly WktLineStringFactory $lineStringFactory;

    private readonly WktMultiLineStringFactory $multiLineStringFactory;

    private readonly WktMultiPolygonFactory $multiPolygonFactory;

    private readonly WktPointFactory $pointFactory;

    private readonly WktPolygonFactory $polygonFactory;

    /**
     * Construct the spatial-object factory and its dimension-specific factories.
     */
    public function __construct()
    {
        $this->pointFactory = new WktPointFactory();
        $this->lineStringFactory = new WktLineStringFactory($this->pointFactory);
        $this->multiLineStringFactory = new WktMultiLineStringFactory($this->lineStringFactory);
        $this->polygonFactory = new WktPolygonFactory($this->lineStringFactory);
        $this->multiPolygonFactory = new WktMultiPolygonFactory($this->polygonFactory);
    }

    /**
     * Create an empty point with the requested coordinate dimension.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the point
     *
     * @return PointInterface the created empty point
     */
    public function createEmptyPoint(CoordinateDimensionEnum $dimension): PointInterface
    {
        return $this->pointFactory->createEmptyPoint($dimension);
    }

    /**
     * Create a line string from its dimension and coordinate sequence.
     *
     * @param CoordinateDimensionEnum $dimension   coordinate dimension of the line string
     * @param list<list<float|int>>   $coordinates ordered line-string coordinates
     */
    public function createLineString(CoordinateDimensionEnum $dimension, array $coordinates): LineStringInterface
    {
        return $this->lineStringFactory->createLineString($dimension, $coordinates);
    }

    /**
     * Create a multi-line string from its dimension and coordinate sequences.
     *
     * @param CoordinateDimensionEnum     $dimension   coordinate dimension of the multi-line string
     * @param list<list<list<float|int>>> $lineStrings ordered line-string coordinates
     */
    public function createMultiLineString(CoordinateDimensionEnum $dimension, array $lineStrings): MultiLineStringInterface
    {
        return $this->multiLineStringFactory->createMultiLineString($dimension, $lineStrings);
    }

    /**
     * Create a multi-polygon from its dimension and ordered polygon coordinates.
     *
     * @param CoordinateDimensionEnum           $dimension coordinate dimension of the multi-polygon
     * @param list<list<list<list<float|int>>>> $polygons  ordered polygon coordinates
     */
    public function createMultiPolygon(CoordinateDimensionEnum $dimension, array $polygons): MultiPolygonInterface
    {
        return $this->multiPolygonFactory->createMultiPolygon($dimension, $polygons);
    }

    /**
     * Create a point from its dimension and ordinates.
     *
     * @param CoordinateDimensionEnum $dimension coordinate dimension of the point
     * @param list<float|int>         $ordinates ordered point ordinates
     */
    public function createPoint(CoordinateDimensionEnum $dimension, array $ordinates): PointInterface
    {
        return $this->pointFactory->createPoint($dimension, $ordinates);
    }

    /**
     * Create a polygon from its dimension and ordered ring coordinates.
     *
     * @param CoordinateDimensionEnum     $dimension coordinate dimension of the polygon
     * @param list<list<list<float|int>>> $rings     ordered ring coordinates
     */
    public function createPolygon(CoordinateDimensionEnum $dimension, array $rings): PolygonInterface
    {
        return $this->polygonFactory->createPolygon($dimension, $rings);
    }
}
