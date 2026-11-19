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
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Fixtures independently specified and matched against spatial-encoder cfc59cd8792bb24a5bdd6bc29877d3fb6d21ba30. */
final class WkbPointDimensionTest extends TestCase
{
    /** @return iterable<string, array{string, CoordinateDimensionEnum, list<float>}> */
    public static function fixtures(): iterable
    {
        yield 'XY LE' => ['0101000000000000000000f03f00000000000000c0', CoordinateDimensionEnum::XY, [1.0, -2.0]];
        yield 'XY LE EMPTY' => ['0101000000000000000000f87f000000000000f87f', CoordinateDimensionEnum::XY, []];
        yield 'XY BE' => ['00000000013ff0000000000000c000000000000000', CoordinateDimensionEnum::XY, [1.0, -2.0]];
        yield 'XY BE EMPTY' => ['00000000017ff80000000000007ff8000000000000', CoordinateDimensionEnum::XY, []];
        yield 'XYZ LE' => ['01e9030000000000000000f03f00000000000000c00000000000000c40', CoordinateDimensionEnum::XYZ, [1.0, -2.0, 3.5]];
        yield 'XYZ LE EMPTY' => ['01e9030000000000000000f87f000000000000f87f000000000000f87f', CoordinateDimensionEnum::XYZ, []];
        yield 'XYZ BE' => ['00000003e93ff0000000000000c000000000000000400c000000000000', CoordinateDimensionEnum::XYZ, [1.0, -2.0, 3.5]];
        yield 'XYZ BE EMPTY' => ['00000003e97ff80000000000007ff80000000000007ff8000000000000', CoordinateDimensionEnum::XYZ, []];
        yield 'XYM LE' => ['01d1070000000000000000f03f00000000000000c00000000000802240', CoordinateDimensionEnum::XYM, [1.0, -2.0, 9.25]];
        yield 'XYM LE EMPTY' => ['01d1070000000000000000f87f000000000000f87f000000000000f87f', CoordinateDimensionEnum::XYM, []];
        yield 'XYM BE' => ['00000007d13ff0000000000000c0000000000000004022800000000000', CoordinateDimensionEnum::XYM, [1.0, -2.0, 9.25]];
        yield 'XYM BE EMPTY' => ['00000007d17ff80000000000007ff80000000000007ff8000000000000', CoordinateDimensionEnum::XYM, []];
        yield 'XYZM LE' => ['01b90b0000000000000000f03f00000000000000c00000000000000c400000000000802240', CoordinateDimensionEnum::XYZM, [1.0, -2.0, 3.5, 9.25]];
        yield 'XYZM LE EMPTY' => ['01b90b0000000000000000f87f000000000000f87f000000000000f87f000000000000f87f', CoordinateDimensionEnum::XYZM, []];
        yield 'XYZM BE' => ['0000000bb93ff0000000000000c000000000000000400c0000000000004022800000000000', CoordinateDimensionEnum::XYZM, [1.0, -2.0, 3.5, 9.25]];
        yield 'XYZM BE EMPTY' => ['0000000bb97ff80000000000007ff80000000000007ff80000000000007ff8000000000000', CoordinateDimensionEnum::XYZM, []];
    }

    /**
     * Preserve layouts and all output options, including EMPTY dimensions.
     *
     * @param string                  $hex         explicit binary fixture
     * @param CoordinateDimensionEnum $dimension   expected layout
     * @param array                   $coordinates expected ordinates
     *
     * @phpstan-param list<float> $coordinates
     */
    #[DataProvider('fixtures')]
    public function testDecode(string $hex, CoordinateDimensionEnum $dimension, array $coordinates): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $point = $decoder->decode(pack('H*', $hex));
                self::assertSame($coordinates, $point->toArray());
                self::assertSame($dimension, $point->getDimension());
                self::assertSame($dimension->hasZ(), $point->hasZ());
                self::assertSame($dimension->hasM(), $point->hasM());
                self::assertSame($mode, $point->getFamily());
                self::assertSame(0, $point->getSrid());
                self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $point);
            }
        }
    }

    /**
     * Every truncated prefix and trailing byte fails without poisoning reuse.
     *
     * @param string                  $hex         complete fixture
     * @param CoordinateDimensionEnum $dimension   declared layout
     * @param array                   $coordinates expected ordinates
     *
     * @phpstan-param list<float> $coordinates
     */
    #[DataProvider('fixtures')]
    public function testLengths(string $hex, CoordinateDimensionEnum $dimension, array $coordinates): void
    {
        $binary = pack('H*', $hex);
        $decoder = new Decoder(new WkbDecoderStrategy());
        $invalid = [$binary."\0"];
        foreach (range(0, \strlen($binary) - 1) as $length) {
            $invalid[] = substr($binary, 0, $length);
        }
        foreach ($invalid as $input) {
            try {
                $decoder->decode($input);
                self::fail('Expected length rejection.');
            } catch (InvalidArgumentException) {
                $point = $decoder->decode($binary);
                self::assertSame($dimension, $point->getDimension());
                self::assertSame($coordinates, $point->toArray());
            }
        }
    }

    /** A partial EMPTY representation must never discard non-NaN values. */
    public function testMixedNan(): void
    {
        foreach ([1 => 2, 1001 => 3, 2001 => 3, 3001 => 4] as $type => $count) {
            foreach (['e*' => 'V', 'E*' => 'N'] as $doubleFormat => $integerFormat) {
                foreach (range(0, $count - 1) as $position) {
                    $coordinates = array_fill(0, $count, 2.0);
                    $coordinates[$position] = \NAN;
                    $data = ('V' === $integerFormat ? "\1" : "\0").pack($integerFormat, $type).pack($doubleFormat, ...$coordinates);
                    try {
                        new WkbDecoderStrategy()->decode($data);
                        self::fail('Expected mixed NaN rejection.');
                    } catch (InvalidArgumentException $exception) {
                        self::assertStringContainsString('Mixed NaN', $exception->getMessage());
                    }
                }
            }
        }
    }

    /** Unsupported type families must fail in both byte orders at plausible lengths. */
    public function testUnsupportedTypes(): void
    {
        foreach ([1000, 1004, 2000, 2004, 3000, 3004, 4001, 0x80000001, 0x40000001, 0xC0000001, 0x20000001, 0xA0000001, 0xE0000001] as $type) {
            foreach (['V', 'N'] as $format) {
                foreach ([2, 3, 4] as $count) {
                    $input = ('V' === $format ? "\1" : "\0").pack($format, $type).str_repeat("\0", 8 * $count);
                    try {
                        new WkbDecoderStrategy()->decode($input);
                        self::fail('Expected unsupported type rejection.');
                    } catch (InvalidArgumentException $exception) {
                        self::assertStringContainsString('Unsupported WKB type', $exception->getMessage());
                    }
                }
            }
        }
    }
}
