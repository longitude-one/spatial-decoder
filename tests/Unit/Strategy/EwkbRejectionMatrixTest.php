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
use LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwkbDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Manually constructed inputs isolate malformed fields from canonical PostGIS output. */
final class EwkbRejectionMatrixTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function geometries(): iterable
    {
        foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
            foreach ([0 => 2, 0x80000000 => 3, 0x40000000 => 3, 0xC0000000 => 4] as $flags => $size) {
                $marker = 'V' === $integer ? "\1" : "\0";
                $point = pack($double, ...array_fill(0, $size, 1.0));
                $count = pack($integer, 1);
                $header = static fn (int $type): string => $marker.pack($integer, $type | $flags | 0x20000000).pack($integer, 3857);
                $bodies = [
                    1 => $point, 2 => $count.$point, 3 => $count.$count.$point,
                    4 => $count.$header(1).$point, 5 => $count.$header(2).$count.$point,
                    6 => $count.$header(3).$count.$count.$point,
                    7 => $count.$header(1).$point, 8 => $count.$point,
                    9 => $count.$header(8).$count.$point, 17 => $count.$count.$point,
                ];
                if (0 !== ($flags & 0x80000000)) {
                    $bodies[15] = $count.$header(3).$count.$count.$point;
                }
                foreach ($bodies as $type => $body) {
                    yield $integer.'-'.$flags.'-'.$type => [$header($type).$body, $integer, $flags];
                }
            }
        }
    }

    /**
     * Invalid identifiers and counters fail at root and below completed siblings.
     *
     * @param string $binary  complete short reference geometry
     * @param string $integer integer byte order
     * @param int    $flags   dimensional flags
     */
    #[DataProvider('geometries')]
    public function testHeadersAndCounts(string $binary, string $integer, int $flags): void
    {
        $decoder = new Decoder(new EwkbDecoderStrategy());
        $marker = 'V' === $integer ? "\1" : "\0";
        $prefix = $marker.pack($integer, 7 | $flags | 0x20000000).pack($integer, 4326).pack($integer, 2).$binary;
        foreach ([0, 10, 11, 12, 13, 14, 16, 18, 999, 4001, 0x10000001, 0xF0000001, 0x08000001] as $word) {
            $invalid = $marker.pack($integer, $word).substr($binary, 5);
            $this->assertRejected($decoder, $invalid, $binary, 'type identifier');
            $this->assertRejected($decoder, $prefix.$invalid, $binary, 'type identifier');
        }
        foreach (["\2", "\xFF"] as $invalidMarker) {
            $invalid = $invalidMarker.substr($binary, 1);
            $this->assertRejected($decoder, $invalid, $binary, 'byte-order');
            $this->assertRejected($decoder, $prefix.$invalid, $binary, 'byte-order');
        }
        $word = unpack($integer.'type', substr($binary, 1, 4));
        if (1 !== (($word['type'] ?? 0) & 0xFF)) {
            $invalid = substr($binary, 0, 9).pack($integer, 0xFFFFFFFF);
            $this->assertRejected($decoder, $invalid, $binary, 'counter exceeds');
            $this->assertRejected($decoder, $prefix.$invalid, $binary, 'counter exceeds');
        }
    }

    /** Nested wrong types and dimensions must never be silently repaired. */
    public function testInconsistentMembersAndUnsafeSuffix(): void
    {
        $valid = pack('H*', '0101000020e6100000000000000000f03f0000000000000040');
        $point = substr($valid, 0, 1).pack('V', 0xE0000001).substr($valid, 5).pack('e*', 3.0, 4.0);
        $invalid = [
            // Wrong members: Point in MultiLineString/MultiPolygon/CompoundCurve/PolyhedralSurface.
            [0x20000005, $valid, 'member type'], [0x20000006, $valid, 'member type'],
            [0x20000009, $valid, 'member type'], [0xE000000F, $point, 'member type'],
            // XYZ EMPTY under XY, including a root with an earlier successfully decoded sibling.
            [0x20000007, pack('H*', '010200008000000000'), 'coordinate dimension'],
            [0x20000004, pack('H*', '0101000080000000000000f87f000000000000f87f000000000000f87f'), 'coordinate dimension'],
        ];
        $decoder = new Decoder(new EwkbDecoderStrategy());
        foreach ($invalid as [$word, $child, $cause]) {
            $binary = "\1".pack('V', $word).pack('V', 4326).pack('V', 1).$child;
            $this->assertRejected($decoder, $binary, $valid, $cause);
            $nested = "\1".pack('V', 0x20000007).pack('V', 4326).pack('V', 2).$valid.$binary;
            $this->assertRejected($decoder, $nested, $valid, $cause);
        }
        $suffix = "\0\xFF\n\r\033<script>hostile-input</script>";
        $this->assertRejected($decoder, $valid.$suffix, $valid, 'Additional bytes');
    }

    /**
     * Exercise every truncated header, SRID, counter, ordinate and nested body.
     *
     * @param string $binary  complete fixture
     * @param string $integer integer byte order
     * @param int    $flags   dimensional flags
     */
    #[DataProvider('geometries')]
    public function testTruncationAndRecovery(string $binary, string $integer, int $flags): void
    {
        $marker = 'V' === $integer ? "\1" : "\0";
        $prefix = $marker.pack($integer, 7 | $flags | 0x20000000).pack($integer, 4326).pack($integer, 2).$binary;
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $previous = $decoder->decode($binary);
                $expected = $previous->toArray();
                for ($length = 0; $length < \strlen($binary); ++$length) {
                    $partial = substr($binary, 0, $length);
                    $this->assertRejected($decoder, $partial, $binary, 'WKB');
                    $this->assertRejected($decoder, $prefix.$partial, $binary, 'WKB');
                }
                $this->assertRejected($decoder, $binary."\0", $binary, 'Additional bytes');
                $this->assertRejected($decoder, $prefix.$binary."\0", $binary, 'Additional bytes');
                self::assertSame($expected, $previous->toArray());
            }
        }
    }

    /**
     * Assert the public exception, a safe feature diagnostic and complete recovery.
     *
     * @param Decoder $decoder public decoder reusing one strategy
     * @param string  $invalid rejected bytes
     * @param string  $valid   successful input following each failure
     * @param string  $cause   feature that the diagnostic must identify
     */
    private function assertRejected(Decoder $decoder, string $invalid, string $valid, string $cause): void
    {
        $expected = $decoder->decode($valid);
        try {
            $decoder->decode($invalid);
            self::fail('Expected EWKB rejection.');
        } catch (DecoderExceptionInterface $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertStringContainsString($cause, $exception->getMessage());
            self::assertStringNotContainsString('hostile-input', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F-\xFF]/', $exception->getMessage());
        }
        self::assertEquals($expected, $decoder->decode($valid));
    }
}
