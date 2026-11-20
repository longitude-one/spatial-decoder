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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Cross-family rejection must preserve successful decoding on the same instance. */
final class WkbRejectionMatrixTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function geometries(): iterable
    {
        foreach (['V' => 'e*', 'N' => 'E*'] as $integer => $double) {
            foreach ([0 => 2, 1000 => 3, 2000 => 3, 3000 => 4] as $offset => $ordinates) {
                $header = 'V' === $integer ? "\1" : "\0";
                $point = pack($double, ...array_fill(0, $ordinates, 1.0));
                $count = pack($integer, 1);
                $pointGeometry = $header.pack($integer, $offset + 1).$point;
                $line = $header.pack($integer, $offset + 2).$count.$point;
                $polygon = $header.pack($integer, $offset + 3).$count.$count.$point;
                $bodies = [1 => $point, 2 => $count.$point, 3 => $count.$count.$point,
                    4 => $count.$pointGeometry, 5 => $count.$line, 6 => $count.$polygon,
                    7 => $count.$pointGeometry, 8 => $count.$point, 9 => $count.$line,
                    17 => $count.$count.$point];
                if (1000 === $offset || 3000 === $offset) {
                    $bodies[15] = $count.$polygon;
                }
                foreach ($bodies as $type => $body) {
                    yield $integer.' type '.($offset + $type) => [$header.pack($integer, $offset + $type).$body, $integer, $offset];
                }
            }
        }
    }

    /**
     * Mutated headers are rejected at the root and after valid nested members.
     *
     * @param string $binary  valid reference geometry
     * @param string $integer integer packing format
     * @param int    $offset  dimensional identifier offset
     */
    #[DataProvider('geometries')]
    public function testIdentifiersAndCounters(string $binary, string $integer, int $offset): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        $marker = 'V' === $integer ? "\1" : "\0";
        $collection = $marker.pack($integer, $offset + 7).pack($integer, 2).$binary;
        $nested = $marker.pack($integer, $offset + 7).pack($integer, 1);
        foreach ([0, 10, 11, 12, 16, 18, 999, 4001, 0x80000001, 0x40000001, 0x20000001, 0xE0000001] as $type) {
            $invalid = $marker.pack($integer, $type).substr($binary, 5);
            $this->assertRejected($decoder, $invalid, $binary, 'Unsupported WKB type');
            $this->assertRejected($decoder, $collection.$nested.$invalid, $binary, 'Unsupported WKB type');
        }
        $this->assertRejected($decoder, "\2".substr($binary, 1), $binary, 'byte-order');
        $identifier = unpack($integer.'type', substr($binary, 1, 4));
        if (1 !== ($identifier['type'] ?? 0) % 1000) {
            $invalid = substr($binary, 0, 5).pack($integer, 0xFFFFFFFF);
            $this->assertRejected($decoder, $invalid, $binary, 'counter exceeds');
            $this->assertRejected($decoder, $collection.$nested.$invalid, $binary, 'counter exceeds');
        }
    }

    /**
     * Every byte boundary covers headers, counters, coordinates and nested bodies.
     *
     * @param string $binary  independently constructed, deliberately short geometry
     * @param string $integer integer packing format
     * @param int    $offset  dimensional identifier offset
     */
    #[DataProvider('geometries')]
    public function testTruncationAndReuse(string $binary, string $integer, int $offset): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                $previous = $decoder->decode($binary);
                $expected = $previous->toArray();
                for ($length = 0; $length < \strlen($binary); ++$length) {
                    $this->assertRejected($decoder, substr($binary, 0, $length), $binary);
                }
                $this->assertRejected($decoder, $binary."\0", $binary, 'Additional bytes');
                $marker = 'V' === $integer ? "\1" : "\0";
                $collection = $marker.pack($integer, $offset + 7).pack($integer, 2);
                // A complete first member must never escape when a later nested member fails.
                $nested = $collection.$binary.$marker.pack($integer, $offset + 7).pack($integer, 1);
                $this->assertRejected($decoder, $nested.$binary."\0", $binary, 'Additional bytes');
                $this->assertRejected($decoder, $nested."\2".substr($binary, 1), $binary, 'byte-order');
                self::assertSame($expected, $previous->toArray());
            }
        }
    }

    /**
     * Verify the public exception contract, cause and subsequent successful call.
     *
     * @param Decoder $decoder configured public decoder retaining the same strategy
     * @param string  $invalid malformed input
     * @param string  $valid   complete reference input
     * @param string  $cause   expected diagnostic fragment, when relevant
     */
    private function assertRejected(Decoder $decoder, string $invalid, string $valid, string $cause = 'WKB'): void
    {
        $expected = $decoder->decode($valid);
        try {
            $decoder->decode($invalid);
            self::fail('Expected malformed WKB rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertStringContainsString($cause, $exception->getMessage());
        }
        self::assertEquals($expected, $decoder->decode($valid));
    }
}
