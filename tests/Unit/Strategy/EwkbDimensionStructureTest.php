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
use LongitudeOne\SpatialTypes\Interfaces\MultiPointInterface;
use PHPUnit\Framework\TestCase;

/** Dimensioned sequence/surface fixtures use explicit wire codes and IEEE-754 bytes. */
final class EwkbDimensionStructureTest extends TestCase
{
    /** EMPTY retains type, layout and SRID for all supported count-based families. */
    public function testCountBasedEmpty(): void
    {
        foreach ([0 => CoordinateDimensionEnum::XY, 0x80000000 => CoordinateDimensionEnum::XYZ,
            0x40000000 => CoordinateDimensionEnum::XYM, 0xC0000000 => CoordinateDimensionEnum::XYZM] as $flag => $dimension) {
            foreach ([2, 3, 4, 5, 6, 7, 8, 9, 15, 17] as $code) {
                foreach (['V', 'N'] as $integer) {
                    $binary = ('V' === $integer ? "\1" : "\0").pack($integer, $code | $flag | 0x20000000).pack($integer, 4326).pack($integer, 0);
                    $this->assertEmptyGeometry($binary, $dimension, $code);
                }
            }
        }
    }

    /** EMPTY members retain their positions and inherited metadata; mismatched layouts still fail. */
    public function testEmptyMembers(): void
    {
        $empty = '000000000000f87f000000000000f87f000000000000f87f';
        $point = '000000000000f03f00000000000000c00000000000802240';
        $binary = pack('H*', '0104000060e6100000020000000101000040'.$empty.'0101000040'.$point);
        $spatial = new Decoder(new EwkbDecoderStrategy())->decode($binary);
        self::assertInstanceOf(MultiPointInterface::class, $spatial);
        self::assertSame([[], [1.0, -2.0, 9.25]], $spatial->toArray());
        foreach ($spatial->getElements() as $member) {
            self::assertSame(CoordinateDimensionEnum::XYM, $member->getDimension());
            self::assertSame(4326, $member->getSrid());
        }
        $this->expectException(InvalidArgumentException::class);
        new Decoder(new EwkbDecoderStrategy())->decode(pack('H*', '0104000060e6100000010000000101000080'.$empty));
    }

    /** Every body dispatch reads XYZM vertices and propagates root metadata to generated components. */
    public function testPopulatedBodies(): void
    {
        foreach (['V' => '000000000000f03f00000000000000c00000000000000c400000000000802240',
            'N' => '3ff0000000000000c000000000000000400c0000000000004022800000000000'] as $integer => $hex) {
            $marker = 'V' === $integer ? "\1" : "\0";
            $tuple = pack('H*', $hex);
            $count = pack($integer, 1);
            $headers = [];
            foreach ([1, 2, 3, 8] as $code) {
                $headers[$code] = $marker.pack($integer, $code | 0xC0000000);
            }
            $bodies = [
                2 => [$count.$tuple, [[1.0, -2.0, 3.5, 9.25]]],
                8 => [$count.$tuple, [[1.0, -2.0, 3.5, 9.25]]],
                3 => [$count.$count.$tuple, [[[1.0, -2.0, 3.5, 9.25]]]],
                17 => [$count.$count.$tuple, [[[1.0, -2.0, 3.5, 9.25]]]],
                4 => [$count.$headers[1].$tuple, [[1.0, -2.0, 3.5, 9.25]]],
                5 => [$count.$headers[2].$count.$tuple, [[[1.0, -2.0, 3.5, 9.25]]]],
                6 => [$count.$headers[3].$count.$count.$tuple, [[[[1.0, -2.0, 3.5, 9.25]]]]],
                7 => [$count.$headers[1].$tuple, [[1.0, -2.0, 3.5, 9.25]]],
                9 => [$count.$headers[8].$count.$tuple, [[[1.0, -2.0, 3.5, 9.25]]]],
                15 => [$count.$headers[3].$count.$count.$tuple, [[[[1.0, -2.0, 3.5, 9.25]]]]],
            ];
            foreach ($bodies as $code => [$body, $expected]) {
                foreach (SpatialModelEnum::cases() as $mode) {
                    foreach ([false, true] as $mutable) {
                        $binary = $marker.pack($integer, $code | 0xE0000000).pack($integer, 4326).$body;
                        $spatial = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode($binary);
                        self::assertSame($expected, $spatial->toArray());
                        self::assertSame(CoordinateDimensionEnum::XYZM, $spatial->getDimension());
                        self::assertSame(4326, $spatial->getSrid());
                    }
                }
            }
        }
    }

    /**
     * Check empty geometry output and reject unavailable surface layouts.
     *
     * @param string                  $binary    complete wire fixture
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param int                     $code      expected geometry type code
     */
    private function assertEmptyGeometry(string $binary, CoordinateDimensionEnum $dimension, int $code): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                if (15 === $code && !$dimension->hasZ()) {
                    try {
                        $decoder->decode($binary);
                        self::fail('Expected unsupported surface layout rejection.');
                    } catch (InvalidArgumentException $exception) {
                        self::assertStringContainsString('PolyhedralSurface', $exception->getMessage());
                    }
                    continue;
                }
                $spatial = $decoder->decode($binary);
                self::assertSame([], $spatial->toArray());
                self::assertSame($code, $spatial->getType()->wkbCode());
                self::assertSame($dimension, $spatial->getDimension());
                self::assertSame(4326, $spatial->getSrid());
                self::assertSame($mode, $spatial->getFamily());
            }
        }
    }
}
