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

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolyhedralSurfaceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Malformed surface streams are rejected without geometric validity checks. */
final class WkbSurfaceStructureTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function inconsistentCounters(): iterable
    {
        foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
            $marker = 'V' === $integer ? "\1" : "\0";
            $triangle = $marker.pack($integer, 1017);
            $surface = $marker.pack($integer, 1015);
            $patch = $marker.pack($integer, 1003).pack($integer, 0);
            $point = pack($double, 1, 2, 3);
            yield $integer.' huge ring count' => [$triangle.pack($integer, 0xFFFFFFFF)];
            yield $integer.' ring count too large' => [$triangle.pack($integer, 2).pack($integer, 0)];
            yield $integer.' ring count too small' => [$triangle.pack($integer, 0).pack($integer, 0)];
            yield $integer.' huge point count' => [$triangle.pack($integer, 1).pack($integer, 0xFFFFFFFF).$point];
            yield $integer.' point count too large' => [$triangle.pack($integer, 1).pack($integer, 2).$point];
            yield $integer.' point count too small' => [$triangle.pack($integer, 1).pack($integer, 0).$point];
            yield $integer.' huge patch count' => [$surface.pack($integer, 0xFFFFFFFF).$patch];
            yield $integer.' patch count too large' => [$surface.pack($integer, 2).$patch];
            yield $integer.' patch count too small' => [$surface.pack($integer, 0).$patch];
            yield $integer.' patch ring count too large' => [$surface.pack($integer, 1).$marker.pack($integer, 1003).pack($integer, 1)];
            yield $integer.' mixed NaN vertex' => [$triangle.pack($integer, 1).pack($integer, 1).pack($double, 1, \NAN, 3)];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPatches(): iterable
    {
        foreach (['V' => "\1", 'N' => "\0"] as $integer => $marker) {
            foreach ([1015, 3015] as $surfaceType) {
                $surface = $marker.pack($integer, $surfaceType);
                $expectedPolygon = $surfaceType - 12;
                foreach ([3, 1003, 2003, 3003, 1017, 3017, 1001, 1002, 1006, 1015, 3015, 0, 4015] as $patchType) {
                    if ($expectedPolygon === $patchType) {
                        continue;
                    }
                    $patch = $marker.pack($integer, $patchType).pack($integer, 0);
                    $invalid = $surface.pack($integer, 1).$patch;
                    yield $integer.' '.$surfaceType.' wrong patch '.$patchType => [$invalid];
                    yield $integer.' '.$surfaceType.' nested wrong patch '.$patchType => [$marker.pack($integer, $surfaceType - 8).pack($integer, 1).$invalid];
                }
                yield $integer.' '.$surfaceType.' invalid patch marker' => [$surface.pack($integer, 1)."\2".pack($integer, $expectedPolygon).pack($integer, 0)];
            }
            foreach ([15, 2015] as $type) {
                $invalid = $marker.pack($integer, $type).pack($integer, 0);
                yield $integer.' unsupported EMPTY layout '.$type => [$invalid];
                yield $integer.' unsupported nested EMPTY layout '.$type => [$marker.pack($integer, $type - 8).pack($integer, 1).$invalid];
            }
        }
    }

    /** EMPTY rings and vertices remain components; short rings are not repaired. */
    public function testEmptyAndInvalidComponents(): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $triangle = $decoder->decode(pack('H*', '01f9030000030000000000000001000000000000000000f87f000000000000f87f000000000000f87f01000000000000000000f03f00000000000000400000000000000840'));
                self::assertInstanceOf(PolygonInterface::class, $triangle);
                self::assertSame(GeometryTypeEnum::TRIANGLE, $triangle->getType());
                self::assertSame([[], [[]], [[1.0, 2.0, 3.0]]], $triangle->toArray());
                $surface = $decoder->decode(pack('H*', '01f70300000200000001eb0300000000000000000003eb0000000200000000000000017ff80000000000007ff80000000000007ff8000000000000'));
                self::assertInstanceOf(PolyhedralSurfaceInterface::class, $surface);
                self::assertSame([[], [[], [[]]]], $surface->toArray());
                $rings = [...$triangle->getRings(), ...$surface->getPatches()[1]->getRings()];
                foreach ([$triangle, $surface, ...$surface->getPatches(), ...$rings, ...$rings[1]->getPoints(), ...$rings[2]->getPoints(), ...$rings[4]->getPoints()] as $node) {
                    self::assertSame(CoordinateDimensionEnum::XYZ, $node->getDimension());
                    self::assertSame($mode, $node->getFamily());
                    self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $node);
                }
            }
        }
    }

    /**
     * Reject wrong type/layout even when EMPTY, retaining previous graphs and reuse.
     *
     * @param string $input incompatible or malformed binary
     */
    #[DataProvider('invalidPatches')]
    #[DataProvider('inconsistentCounters')]
    public function testReject(string $input): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $binary = pack('H*', '01110000000100000001000000000000000000f03f0000000000000040');
                $previous = $decoder->decode($binary);
                try {
                    $decoder->decode($input);
                    self::fail('Expected surface rejection.');
                } catch (InvalidArgumentException) {
                    self::assertSame([[[1.0, 2.0]]], $previous->toArray());
                    self::assertEquals($previous, $decoder->decode($binary));
                }
            }
        }
    }
}
