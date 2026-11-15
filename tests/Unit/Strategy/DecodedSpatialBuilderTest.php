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

namespace LongitudeOne\SpatialDecoder\Tests\Unit\Strategy;

use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\DecodedSpatialBuilder;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\TestCase;

/** Representation choices apply recursively without changing parsing or shared factory state. */
final class DecodedSpatialBuilderTest extends TestCase
{
    /** XYZM values and reference identity survive reconstruction. */
    public function testFourDimensionalReference(): void
    {
        $source = new WktDecoderStrategy()->decode('POINT ZM (1 2 3 4)');
        self::assertInstanceOf(ImmutableSpatialInterface::class, $source);
        $source = $source->withSrid(4326);
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOMETRY, true)->build($source);
        self::assertSame([1, 2, 3, 4], $result->toArray());
        self::assertSame($source->getSpatialReference(), $result->getSpatialReference());
    }

    /** Representation selection does not introduce geometric validation. */
    public function testGeographyPreservesCoordinatesWithoutValidation(): void
    {
        $source = new WktDecoderStrategy()->decode('POINT (200 100)');
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOGRAPHY, false)->build($source);
        self::assertSame([200, 100], $result->toArray());
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
    }

    /** Default geometry construction remains immutable. */
    public function testImmutableGeometry(): void
    {
        $source = new WktDecoderStrategy()->decode('POINT (1 2)');
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOMETRY, false)->build($source);
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOMETRY, $result->getFamily());
        self::assertSame([1, 2], $result->toArray());
    }

    /** XYM must not be interpreted as XYZ during reconstruction. */
    public function testMeasuredPoint(): void
    {
        $source = new WktDecoderStrategy()->decode('POINT M (1 2 7)');
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOGRAPHY, false)->build($source);
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame($source->getDimension(), $result->getDimension());
        self::assertSame([1, 2, 7], $result->toArray());
    }

    /** An empty point remains empty in mutable mode. */
    public function testMutableEmpty(): void
    {
        $source = new WktDecoderStrategy()->decode('POINT EMPTY');
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOMETRY, true)->build($source);
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame([], $result->toArray());
    }

    /** Mixed collection members and their points use the selected representation. */
    public function testMutableGeographyCollection(): void
    {
        $source = new WktDecoderStrategy()->decode('GEOMETRYCOLLECTION (POINT (1 2), LINESTRING (3 4, 5 6))');
        self::assertInstanceOf(CollectionInterface::class, $source);
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOGRAPHY, true)->build($source);
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertInstanceOf(CollectionInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        self::assertInstanceOf(MutableSpatialInterface::class, $result->getElements()[0]);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getElements()[0]->getFamily());
        $line = $result->getElements()[1];
        self::assertInstanceOf(LineStringInterface::class, $line);
        self::assertInstanceOf(MutableSpatialInterface::class, $line);
        self::assertInstanceOf(MutableSpatialInterface::class, $line->getPoints()[0]);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $line->getPoints()[0]->getFamily());
        self::assertSame($source->toArray(), $result->toArray());
        self::assertSame([1, 2], $source->getElements()[0]->toArray());
    }

    /** Homogeneous collections retain their type and recurse through their constructors. */
    public function testMutableMultiPoint(): void
    {
        $source = new WktDecoderStrategy()->decode('MULTIPOINT ((1 2), (3 4))');
        $result = new DecodedSpatialBuilder(SpatialModelEnum::GEOMETRY, true)->build($source);
        self::assertSame($source->getType(), $result->getType());
        self::assertSame($source->toArray(), $result->toArray());
        self::assertInstanceOf(CollectionInterface::class, $result);
        self::assertInstanceOf(MutableSpatialInterface::class, $result->getElements()[0]);
    }
}
