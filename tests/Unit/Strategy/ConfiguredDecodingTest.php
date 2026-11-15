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
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/** Representation options are applied at the public decoding boundary. */
final class ConfiguredDecodingTest extends TestCase
{
    /** Curve component types survive recursive construction. */
    public function testCompoundCurve(): void
    {
        $result = new WktDecoderStrategy(['mutable' => true])->decode('COMPOUNDCURVE ((0 0, 1 0), CIRCULARSTRING (1 0, 2 1, 3 0))');
        self::assertInstanceOf(CompoundCurveInterface::class, $result);
        self::assertInstanceOf(MutableSpatialInterface::class, $result->getCurves()[0]);
        self::assertInstanceOf(MutableSpatialInterface::class, $result->getCurves()[1]);
        self::assertSame('CircularString', $result->getCurves()[1]->getType()->value);
    }

    /** EWKT defaults retain their root spatial reference. */
    public function testEwktDefaults(): void
    {
        $result = new EwktDecoderStrategy()->decode('SRID=4326;POINT (1 2)');
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOMETRY, $result->getFamily());
        self::assertSame(4326, $result->getSrid());
    }

    /** EWKT uses the family option with or without a prefix. */
    public function testImmutableEwktGeography(): void
    {
        $strategy = new EwktDecoderStrategy(['mode' => SpatialModelEnum::GEOGRAPHY]);
        $result = $strategy->decode('SRID=4326;POINT (1 2)');
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        self::assertSame(4326, $result->getSrid());
        $result = $strategy->decode('POINT (1 2)');
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        self::assertSame(0, $result->getSrid());
    }

    /** Geography and mutability are independent choices. */
    public function testImmutableWktGeography(): void
    {
        $result = new WktDecoderStrategy(['mode' => SpatialModelEnum::GEOGRAPHY])->decode('POINT M (1 2 7)');
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        self::assertSame([1, 2, 7], $result->toArray());
    }

    /** One strategy's configuration cannot change another instance's defaults. */
    public function testInstanceIsolation(): void
    {
        $mutable = new WktDecoderStrategy(['mutable' => true]);
        $default = new WktDecoderStrategy();
        self::assertInstanceOf(MutableSpatialInterface::class, $mutable->decode('POINT (1 2)'));
        self::assertInstanceOf(ImmutableSpatialInterface::class, $default->decode('POINT (1 2)'));
    }

    /** Arbitrary strings are not spatial model enum values. */
    public function testInvalidMode(): void
    {
        $this->expectException(InvalidOptionsException::class);
        new WktDecoderStrategy(['mode' => 'Geography']);
    }

    /** A numeric mutable flag is rejected at construction. */
    public function testInvalidMutable(): void
    {
        $this->expectException(InvalidOptionsException::class);
        new EwktDecoderStrategy(['mutable' => 1]);
    }

    /** Polygon rings and points retain the root reference and selected mode. */
    public function testMutableEwktGeography(): void
    {
        $result = new EwktDecoderStrategy(['mode' => SpatialModelEnum::GEOGRAPHY, 'mutable' => true])
            ->decode('SRID=4326;POLYGON ((0 0, 1 0, 0 1, 0 0))');
        self::assertInstanceOf(PolygonInterface::class, $result);
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        $ring = $result->getRings()[0];
        self::assertInstanceOf(MutableSpatialInterface::class, $ring);
        self::assertSame(4326, $ring->getSrid());
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $ring->getFamily());
        self::assertInstanceOf(MutableSpatialInterface::class, $ring->getPoints()[0]);
        self::assertSame(4326, $ring->getPoints()[0]->getSrid());
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $ring->getPoints()[0]->getFamily());
    }

    /** EWKT carries XYZM and SRID through mutable reconstruction. */
    public function testMutableEwktGeometry(): void
    {
        $result = new EwktDecoderStrategy(['mutable' => true])->decode('SRID=4326;POINT ZM (1 2 3 4)');
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOMETRY, $result->getFamily());
        self::assertSame([1, 2, 3, 4], $result->toArray());
        self::assertSame(4326, $result->getSrid());
    }

    /** The unprefixed EWKT path also applies mutability. */
    public function testMutableUnprefixedEwkt(): void
    {
        $result = new EwktDecoderStrategy(['mutable' => true])->decode('POINT EMPTY');
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame([], $result->toArray());
    }

    /** Nested collections and their component points share the selected representation. */
    public function testMutableWktGeography(): void
    {
        $result = new WktDecoderStrategy(['mode' => SpatialModelEnum::GEOGRAPHY, 'mutable' => true])
            ->decode('GEOMETRYCOLLECTION (POINT EMPTY, GEOMETRYCOLLECTION (LINESTRING (1 2, 3 4)))');
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $result->getFamily());
        self::assertInstanceOf(CollectionInterface::class, $result);
        $empty = $result->getElements()[0];
        self::assertInstanceOf(MutableSpatialInterface::class, $empty);
        self::assertSame([], $empty->toArray());
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $empty->getFamily());
        $nested = $result->getElements()[1];
        self::assertInstanceOf(CollectionInterface::class, $nested);
        self::assertInstanceOf(MutableSpatialInterface::class, $nested);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $nested->getFamily());
        $line = $nested->getElements()[0];
        self::assertInstanceOf(LineStringInterface::class, $line);
        self::assertInstanceOf(MutableSpatialInterface::class, $line);
        self::assertInstanceOf(MutableSpatialInterface::class, $line->getPoints()[0]);
        self::assertSame(SpatialModelEnum::GEOGRAPHY, $line->getPoints()[0]->getFamily());
    }

    /** Selecting mutable Geometry does not change coordinates. */
    public function testMutableWktGeometry(): void
    {
        $result = new WktDecoderStrategy(['mutable' => true])->decode('POINT Z (1 2 3)');
        self::assertInstanceOf(MutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOMETRY, $result->getFamily());
        self::assertSame([1, 2, 3], $result->toArray());
    }

    /** EWKT uses the same option validation as WKT. */
    public function testUnknownEwktOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        new EwktDecoderStrategy(['srid' => 4326]);
    }

    /** Unsupported options are rejected before parsing. */
    public function testUnknownWktOption(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        new WktDecoderStrategy(['precision' => 2]);
    }

    /** No options retain immutable Geometry output. */
    public function testWktDefaults(): void
    {
        $result = new Decoder(new WktDecoderStrategy())->decode('POINT (1 2)');
        self::assertInstanceOf(ImmutableSpatialInterface::class, $result);
        self::assertSame(SpatialModelEnum::GEOMETRY, $result->getFamily());
        self::assertSame([1, 2], $result->toArray());
    }
}
