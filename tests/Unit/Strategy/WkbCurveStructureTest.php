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
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Curve membership is structural; geometric validity remains a separate concern. */
final class WkbCurveStructureTest extends TestCase
{
    /**
     * All eight fixtures matched separately constructed spatial-encoder cfc59cd output.
     *
     * @return iterable<string, array{string, CoordinateDimensionEnum}>
     */
    public static function emptyLinearComponents(): iterable
    {
        foreach ([0 => CoordinateDimensionEnum::XY, 1000 => CoordinateDimensionEnum::XYZ, 2000 => CoordinateDimensionEnum::XYM, 3000 => CoordinateDimensionEnum::XYZM] as $offset => $dimension) {
            foreach (['V', 'N'] as $format) {
                $marker = 'V' === $format ? "\1" : "\0";
                $binary = $marker.pack($format.'2', 9 + $offset, 2).$marker.pack($format.'2', 2 + $offset, 0).$marker.pack($format.'2', 8 + $offset, 0);
                yield $dimension->value.' '.$format => [$binary, $dimension];
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function incompatibleDimensions(): iterable
    {
        foreach ([0, 1000, 2000, 3000] as $parentOffset) {
            foreach ([0, 1000, 2000, 3000] as $childOffset) {
                if ($parentOffset === $childOffset) {
                    continue;
                }
                foreach ([2, 8] as $memberType) {
                    $compound = "\1".pack('V2', 9 + $parentOffset, 1)."\0".pack('N2', $memberType + $childOffset, 0);
                    yield $parentOffset.' '.$childOffset.' '.$memberType.' EMPTY' => [bin2hex($compound)];
                    yield $parentOffset.' '.$childOffset.' '.$memberType.' nested' => [bin2hex("\0".pack('N2', 7 + $parentOffset, 1).$compound)];
                    $ordinateCount = match ($childOffset) {
                        0 => 2, 3000 => 4, default => 3
                    };
                    $populated = "\1".pack('V2', 9 + $parentOffset, 1)."\0".pack('N2', $memberType + $childOffset, 1).pack('E*', ...array_fill(0, $ordinateCount, 1.0));
                    yield $parentOffset.' '.$childOffset.' '.$memberType.' populated' => [bin2hex($populated)];
                }
                foreach ([8, 9] as $curveType) {
                    yield $parentOffset.' '.$childOffset.' collection '.$curveType => [bin2hex("\1".pack('V2', 7 + $parentOffset, 1)."\0".pack('N2', $curveType + $childOffset, 0))];
                }
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMembers(): iterable
    {
        foreach ([
            '0101000000000000000000f03f0000000000000040', // Point.
            '010300000000000000', // EMPTY Polygon.
            '010400000000000000', // EMPTY MultiPoint.
            '010500000000000000', // EMPTY MultiLineString.
            '010600000000000000', // EMPTY MultiPolygon.
            '010700000000000000', // EMPTY GeometryCollection.
            '010900000000000000', // Nested CompoundCurve.
            '020800000000000000', // Invalid circular byte-order marker.
            '010800008000000000', // EWKB circular type flags.
            '010000000000000000', // Unknown component type.
            '01a80f000000000000', // Unsupported dimensional family.
            '0108000000ffffffff', // Impossible circular point count.
            '010800000001000000000000000000f87f0000000000000040', // Mixed NaN.
        ] as $index => $member) {
            yield 'incompatible component '.$index => ['010900000001000000'.$member];
            yield 'nested incompatible component '.$index => ['010700000001000000010900000002000000010200000000000000'.$member];
        }
        foreach (['0108000000', '0109000000'] as $header) {
            yield $header.' huge count' => [$header.'ffffffff'];
            yield $header.' uncounted data' => [$header.'0000000000000000'];
            yield $header.' nested huge count' => ['010700000001000000'.$header.'ffffffff'];
        }
        yield 'compound too few components' => ['010900000002000000010200000000000000'];
        yield 'compound uncounted component' => ['010900000001000000010200000000000000010800000000000000'];
        yield 'circular too few coordinates' => ['010800000002000000000000000000f03f0000000000000040'];
        yield 'component count consumes root sibling' => ['010700000002000000010900000002000000010200000000000000010800000000000000'];
    }

    /**
     * Preserve EMPTY linear components even though WKT has no matching component syntax.
     *
     * @param string                  $binary    complete compound curve
     * @param CoordinateDimensionEnum $dimension declared layout
     */
    #[DataProvider('emptyLinearComponents')]
    public function testEmptyLinearComponents(string $binary, CoordinateDimensionEnum $dimension): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $curve = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode($binary);
                self::assertInstanceOf(CompoundCurveInterface::class, $curve);
                self::assertSame([[], []], $curve->toArray());
                self::assertSame([GeometryTypeEnum::LINESTRING, GeometryTypeEnum::CIRCULARSTRING], array_map(static fn ($component) => $component->getType(), $curve->getCurves()));
                foreach ([$curve, ...$curve->getCurves()] as $node) {
                    self::assertSame($dimension, $node->getDimension());
                    self::assertSame($mode, $node->getFamily());
                    self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $node);
                }
            }
        }
    }

    /** An even, short circular sequence is constructible and must not be repaired. */
    public function testNoGeometricValidation(): void
    {
        $binary = pack('H*', '010800000002000000000000000000f03f000000000000004000000000000008400000000000001040');
        foreach ([false, true] as $mutable) {
            $curve = new Decoder(new WkbDecoderStrategy(['mutable' => $mutable]))->decode($binary);
            self::assertInstanceOf(CircularStringInterface::class, $curve);
            self::assertSame([[1.0, 2.0], [3.0, 4.0]], $curve->toArray());
        }
    }

    /**
     * Preserve a previously returned graph and allow reuse after every rejection.
     *
     * @param string $hex incompatible or malformed WKB fields
     */
    #[DataProvider('invalidMembers')]
    #[DataProvider('incompatibleDimensions')]
    public function testReject(string $hex): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $previous = $decoder->decode(pack('H*', '010900000001000000010200000001000000000000000000f03f0000000000000040'));
                try {
                    $decoder->decode(pack('H*', $hex));
                    self::fail('Expected incompatible curve rejection.');
                } catch (InvalidArgumentException) {
                    self::assertSame([[[1.0, 2.0]]], $previous->toArray());
                    self::assertSame([], $decoder->decode(pack('H*', '010800000000000000'))->toArray());
                }
            }
        }
    }
}
