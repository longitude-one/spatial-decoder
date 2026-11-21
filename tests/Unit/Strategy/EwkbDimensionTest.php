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
use LongitudeOne\SpatialDecoder\Strategy\EwkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Manually specified header/ordinate hex fixtures, independent of any encoder. */
final class EwkbDimensionTest extends TestCase
{
    /** @return iterable<string, array{string, CoordinateDimensionEnum, list<float>, int}> */
    public static function fixtures(): iterable
    {
        $layouts = [
            ['00000001', '20000001', '01000000', '01000020', CoordinateDimensionEnum::XY, [1.0, -2.0], ''],
            ['80000001', 'a0000001', '01000080', '010000a0', CoordinateDimensionEnum::XYZ, [1.0, -2.0, 3.5], '400c000000000000'],
            ['40000001', '60000001', '01000040', '01000060', CoordinateDimensionEnum::XYM, [1.0, -2.0, 9.25], '4022800000000000'],
            ['c0000001', 'e0000001', '010000c0', '010000e0', CoordinateDimensionEnum::XYZM, [1.0, -2.0, 3.5, 9.25], '400c0000000000004022800000000000'],
        ];
        foreach ($layouts as [$bigWord, $bigSrid, $littleWord, $littleSrid, $dimension, $values, $extra]) {
            $tuple = '3ff0000000000000c000000000000000'.$extra;
            $littleTuple = implode('', array_map(static fn (string $double): string => implode('', array_reverse(str_split($double, 2))), str_split($tuple, 16)));
            foreach ([['00', $bigWord, $bigSrid.'000010e6', $tuple, '7ff8000000000000'],
                ['01', $littleWord, $littleSrid.'e6100000', $littleTuple, '000000000000f87f']] as [$marker, $word, $withSrid, $ordinates, $nan]) {
                foreach ([$word => 0, $withSrid => 4326] as $header => $srid) {
                    $key = $dimension->name.' '.$marker.' '.$srid;
                    yield $key => [$marker.$header.$ordinates, $dimension, $values, $srid];
                    yield $key.' EMPTY' => [$marker.$header.str_repeat($nan, $dimension->coordinateDimension()), $dimension, [], $srid];
                }
            }
        }
    }

    /**
     * Z/M remain distinct, including EMPTY and every output configuration.
     *
     * @param string                  $hex       manually specified bytes
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param array                   $values    expected coordinate sequence
     * @param int                     $srid      expected spatial reference
     *
     * @phpstan-param list<float> $values
     */
    #[DataProvider('fixtures')]
    public function testDimensions(string $hex, CoordinateDimensionEnum $dimension, array $values, int $srid): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $point = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode(pack('H*', $hex));
                self::assertSame($values, $point->toArray());
                self::assertSame($dimension, $point->getDimension());
                self::assertSame($srid, $point->getSrid());
                self::assertSame($mode, $point->getFamily());
                self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $point);
            }
        }
    }

    /** All flag/offset combinations use the approved logical union, including redundant declarations. */
    public function testDimensionUnion(): void
    {
        $layouts = [
            CoordinateDimensionEnum::XY, CoordinateDimensionEnum::XYZ,
            CoordinateDimensionEnum::XYM, CoordinateDimensionEnum::XYZM,
        ];
        $unions = [[0, 1, 2, 3], [1, 1, 3, 3], [2, 3, 2, 3], [3, 3, 3, 3]];
        $tuples = [[1.0, -2.0], [1.0, -2.0, 3.5], [1.0, -2.0, 9.25], [1.0, -2.0, 3.5, 9.25]];
        foreach ([0, 1000, 2000, 3000] as $index => $offset) {
            foreach ([0, 0x80000000, 0x40000000, 0xC0000000] as $flagIndex => $flags) {
                $expected = $layouts[$unions[$index][$flagIndex]];
                $values = $tuples[$unions[$index][$flagIndex]];
                foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
                    $data = ('V' === $integer ? "\1" : "\0").pack($integer, ($offset + 1) | $flags | 0x20000000).pack($integer, 4326).pack($double, ...$values);
                    $point = new Decoder(new EwkbDecoderStrategy())->decode($data);
                    self::assertSame($expected, $point->getDimension());
                    self::assertSame($values, $point->toArray());
                    self::assertSame(4326, $point->getSrid());
                }
            }
        }
    }

    /** Mixed NaN tuples, unsupported layouts and unknown bits fail through the public exception model. */
    public function testInvalidDimensions(): void
    {
        foreach ([0x80000001 => 3, 0x40000001 => 3, 0xC0000001 => 4] as $word => $size) {
            foreach (range(0, $size - 1) as $position) {
                $values = array_fill(0, $size, 2.0);
                $values[$position] = \NAN;
                foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
                    $binary = ('V' === $integer ? "\1" : "\0").pack($integer, $word).pack($double, ...$values);
                    try {
                        new Decoder(new EwkbDecoderStrategy())->decode($binary);
                        self::fail('Expected mixed NaN rejection.');
                    } catch (InvalidArgumentException $exception) {
                        self::assertStringContainsString('Mixed NaN', $exception->getMessage());
                    }
                }
            }
        }
        foreach ([0x10000001, 0x90000001, 4001, 0x80000FA1, 15, 0x4000000F] as $word) {
            foreach (['V', 'N'] as $integer) {
                try {
                    new Decoder(new EwkbDecoderStrategy())->decode(('V' === $integer ? "\1" : "\0").pack($integer, $word).str_repeat("\0", 32));
                    self::fail('Expected unsupported dimension/type rejection.');
                } catch (InvalidArgumentException $exception) {
                    self::assertNotSame('', $exception->getMessage());
                }
            }
        }
    }

    /**
     * Every truncated prefix must fail rather than supplying missing ordinates.
     *
     * @param string                  $hex       complete fixture
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param array                   $values    expected coordinates
     * @param int                     $srid      expected reference
     *
     * @phpstan-param list<float> $values
     */
    #[DataProvider('fixtures')]
    public function testTruncation(string $hex, CoordinateDimensionEnum $dimension, array $values, int $srid): void
    {
        $binary = pack('H*', $hex);
        $decoder = new Decoder(new EwkbDecoderStrategy());
        for ($length = 0; $length < \strlen($binary); ++$length) {
            try {
                $decoder->decode(substr($binary, 0, $length));
                self::fail('Expected truncation rejection.');
            } catch (InvalidArgumentException) {
                $point = $decoder->decode($binary);
                self::assertSame($dimension, $point->getDimension());
                self::assertSame($values, $point->toArray());
                self::assertSame($srid, $point->getSrid());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $decoder->decode($binary."\0");
    }
}
