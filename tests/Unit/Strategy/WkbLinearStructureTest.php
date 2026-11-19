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

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Structural boundaries do not add geometric validation. */
final class WkbLinearStructureTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function constructibleFixtures(): iterable
    {
        yield 'single point LE' => [
            '010200000001000000000000000000f03f0000000000000040',
            'LINESTRING (1.0 2.0)',
        ];
        yield 'single point BE' => [
            '0000000002000000013ff00000000000004000000000000000',
            'LINESTRING (1.0 2.0)',
        ];
        yield 'three ordered rings LE' => [
            '010300000003000000040000000000000000000000000000000000000000000000000020400000000000000000000000000000204000000000000020400000000000000000000000000000000004000000000000000000f03f000000000000f03f0000000000000040000000000000f03f000000000000f03f0000000000000040000000000000f03f000000000000f03f0400000000000000000010400000000000001040000000000000144000000000000010400000000000001040000000000000144000000000000010400000000000001040',
            'POLYGON ((0.0 0.0,8.0 0.0,8.0 8.0,0.0 0.0),(1.0 1.0,2.0 1.0,1.0 2.0,1.0 1.0),(4.0 4.0,5.0 4.0,4.0 5.0,4.0 4.0))',
        ];
        yield 'three ordered rings BE' => [
            '0000000003000000030000000400000000000000000000000000000000402000000000000000000000000000004020000000000000402000000000000000000000000000000000000000000000000000043ff00000000000003ff000000000000040000000000000003ff00000000000003ff000000000000040000000000000003ff00000000000003ff00000000000000000000440100000000000004010000000000000401400000000000040100000000000004010000000000000401400000000000040100000000000004010000000000000',
            'POLYGON ((0.0 0.0,8.0 0.0,8.0 8.0,0.0 0.0),(1.0 1.0,2.0 1.0,1.0 2.0,1.0 1.0),(4.0 4.0,5.0 4.0,4.0 5.0,4.0 4.0))',
        ];
        yield 'unclosed ring LE' => [
            '0103000000010000000300000000000000000000000000000000000000000000000000f03f0000000000000000000000000000f03f000000000000f03f',
            'POLYGON ((0.0 0.0,1.0 0.0,1.0 1.0))',
        ];
        yield 'unclosed ring BE' => [
            '00000000030000000100000003000000000000000000000000000000003ff000000000000000000000000000003ff00000000000003ff0000000000000',
            'POLYGON ((0.0 0.0,1.0 0.0,1.0 1.0))',
        ];
        yield 'self crossing LE' => [
            '010300000001000000050000000000000000000000000000000000000000000000000000400000000000000040000000000000000000000000000000400000000000000040000000000000000000000000000000000000000000000000',
            'POLYGON ((0.0 0.0,2.0 2.0,0.0 2.0,2.0 0.0,0.0 0.0))',
        ];
        yield 'self crossing BE' => [
            '000000000300000001000000050000000000000000000000000000000040000000000000004000000000000000000000000000000040000000000000004000000000000000000000000000000000000000000000000000000000000000',
            'POLYGON ((0.0 0.0,2.0 2.0,0.0 2.0,2.0 0.0,0.0 0.0))',
        ];
        yield 'outside hole LE' => [
            '01030000000200000004000000000000000000000000000000000000000000000000000040000000000000000000000000000000400000000000000040000000000000000000000000000000000400000000000000000014400000000000001440000000000000184000000000000014400000000000001840000000000000184000000000000014400000000000001440',
            'POLYGON ((0.0 0.0,2.0 0.0,2.0 2.0,0.0 0.0),(5.0 5.0,6.0 5.0,6.0 6.0,5.0 5.0))',
        ];
        yield 'outside hole BE' => [
            '00000000030000000200000004000000000000000000000000000000004000000000000000000000000000000040000000000000004000000000000000000000000000000000000000000000000000000440140000000000004014000000000000401800000000000040140000000000004018000000000000401800000000000040140000000000004014000000000000',
            'POLYGON ((0.0 0.0,2.0 0.0,2.0 2.0,0.0 0.0),(5.0 5.0,6.0 5.0,6.0 6.0,5.0 5.0))',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function inconsistentCounters(): iterable
    {
        foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
            $marker = 'V' === $integer ? "\1" : "\0";
            $point = pack($double, 1, 2);
            $line = $marker.pack($integer, 2);
            $polygon = $marker.pack($integer, 3);
            yield $integer.' absent line count' => [$line];
            yield $integer.' huge point count' => [$line.pack($integer, 0xFFFFFFFF).$point];
            yield $integer.' point count too large' => [$line.pack($integer, 2).$point];
            yield $integer.' point count too small' => [$line.pack($integer, 0).$point];
            yield $integer.' huge ring count' => [$polygon.pack($integer, 0xFFFFFFFF)];
            yield $integer.' absent ring count' => [$polygon.pack($integer, 1)];
            yield $integer.' ring count too large' => [$polygon.pack($integer, 2).pack($integer, 0)];
            yield $integer.' ring count too small' => [$polygon.pack($integer, 0).pack($integer, 0)];
            yield $integer.' huge ring point count' => [$polygon.pack($integer, 1).pack($integer, 0xFFFFFFFF).$point];
            yield $integer.' ring point count too large' => [$polygon.pack($integer, 1).pack($integer, 2).$point];
            yield $integer.' ring point count too small' => [$polygon.pack($integer, 1).pack($integer, 0).$point];
            yield $integer.' mixed NaN line vertex' => [$line.pack($integer, 1).pack($double, \NAN, 2)];
            yield $integer.' mixed NaN ring vertex' => [$polygon.pack($integer, 1).pack($integer, 1).pack($double, 1, \NAN)];
        }
    }

    /**
     * Compare the graph with WKT construction without repairing invalid examples.
     *
     * @param string $hex explicit binary input
     * @param string $wkt equivalent text input
     */
    #[DataProvider('constructibleFixtures')]
    public function testConstructibleGeometries(string $hex, string $wkt): void
    {
        $actual = new Decoder(new WkbDecoderStrategy())->decode(pack('H*', $hex));
        $expected = new WktDecoderStrategy()->decode($wkt);
        self::assertEquals($expected, $actual);
        self::assertSame($expected->toArray(), $actual->toArray());
    }

    /** EMPTY components from the encoder are preserved as components. */
    public function testEmptyComponents(): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        $line = $decoder->decode(pack('H*', '010200000001000000000000000000f87f000000000000f87f'));
        self::assertSame([[]], $line->toArray());
        $polygon = $decoder->decode(pack('H*', '0103000000020000000000000001000000000000000000f87f000000000000f87f'));
        self::assertSame([[], [[]]], $polygon->toArray());
    }

    /**
     * Reject impossible declarations before traversal, then allow reuse.
     *
     * @param string $input malformed binary input
     */
    #[DataProvider('inconsistentCounters')]
    public function testInconsistentCounter(string $input): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        try {
            $decoder->decode($input);
            self::fail('Expected malformed WKB rejection.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $decoder->decode(pack('H*', '010200000000000000'))->toArray());
        }
    }
}
