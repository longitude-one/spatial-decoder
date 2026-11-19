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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Collection membership is structural, including for EMPTY members. */
final class WkbCollectionStructureTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function incompatibleDimensions(): iterable
    {
        $offsets = [0 => 2, 1000 => 3, 2000 => 3, 3000 => 4];
        foreach (array_keys($offsets) as $parentOffset) {
            foreach ($offsets as $childOffset => $ordinateCount) {
                if ($parentOffset === $childOffset) {
                    continue;
                }
                yield from self::dimensionMembers($parentOffset, $childOffset, $ordinateCount);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMembers(): iterable
    {
        $point = '0101000000000000000000f03f0000000000000040';
        foreach (['0104000000' => '010200000000000000', '0105000000' => $point, '0106000000' => '010500000000000000'] as $header => $wrong) {
            yield $header.' wrong member' => [$header.'01000000'.$wrong];
            yield $header.' wrong nested member' => ['010700000001000000'.$header.'02000000'.$wrong.$wrong];
        }
        foreach ([
            '0201000000000000000000f03f0000000000000040', // Invalid marker.
            '0101000080000000000000f03f0000000000000040', // EWKB flag.
            '010800000000000000', // Not yet supported CircularString.
            '010000000000000000', // Unknown type.
            '01a10f000000000000', // Unknown dimensional family.
            '01', // Truncated member header after a valid member.
            '0101000000000000000000f87f0000000000000040', // Mixed NaN point.
        ] as $index => $invalid) {
            yield 'invalid nested header or coordinates '.$index => ['010700000002000000'.$point.'000000000700000001'.$invalid];
        }
        foreach (['0104000000', '0105000000', '0106000000', '0107000000'] as $header) {
            yield $header.' huge count' => [$header.'ffffffff'];
            yield $header.' too few members' => [$header.'02000000'.$point];
            yield $header.' uncounted member' => [$header.'00000000'.$point];
            yield $header.' nested huge count' => ['010700000001000000'.$header.'ffffffff'];
            yield $header.' nested uncounted member' => ['010700000001000000'.$header.'00000000'.$point];
        }
        yield 'nested counter consumes root sibling' => ['010700000002000000010700000002000000'.$point.$point];
        yield 'nested point counter too large' => ['0107000000010000000105000000010000000102000000ffffffff'];
        yield 'nested ring counter too large' => ['0107000000010000000106000000010000000103000000ffffffff'];
    }

    /**
     * Exercise every collection at the root and inside another collection.
     *
     * @param int $parentOffset  parent dimensional offset
     * @param int $childOffset   member dimensional offset
     * @param int $ordinateCount member ordinate count
     *
     * @return iterable<string, array{string}>
     */
    private static function dimensionMembers(int $parentOffset, int $childOffset, int $ordinateCount): iterable
    {
        foreach ([4 => 1, 5 => 2, 6 => 3, 7 => 7] as $parentType => $childType) {
            $header = "\1".pack('V', $parentOffset + $parentType).pack('V', 1);
            $childHeader = "\0".pack('N', $childOffset + $childType);
            $emptyBody = self::emptyBody($childType, $ordinateCount);
            yield $parentOffset.' '.$childOffset.' '.$parentType.' EMPTY' => [$header.$childHeader.$emptyBody];
            // Also test a non-empty Point and mismatches below a matching intermediate collection.
            if (1 === $childType) {
                $coordinates = pack('E*', ...array_fill(0, $ordinateCount, 1.0));
                yield $parentOffset.' '.$childOffset.' populated' => [$header.$childHeader.$coordinates];
            }
            yield $parentOffset.' '.$childOffset.' '.$parentType.' nested' => ["\1".pack('V', $parentOffset + 7).pack('V', 1).$header.$childHeader.$emptyBody];
        }
    }

    /**
     * Express the distinct EMPTY Point and aggregate binary bodies.
     *
     * @param int $type          member base identifier
     * @param int $ordinateCount number of declared ordinates
     */
    private static function emptyBody(int $type, int $ordinateCount): string
    {
        return 1 === $type ? pack('E*', ...array_fill(0, $ordinateCount, \NAN)) : pack('N', 0);
    }

    /**
     * No ordinate or dimension conversion is inferred from a parent.
     *
     * @param string $binary incompatible nested bytes
     */
    #[DataProvider('incompatibleDimensions')]
    public function testIncompatibleDimensions(string $binary): void
    {
        $this->assertRejected($binary);
    }

    /**
     * Rejection preserves the caller's previous graph and decoder reuse.
     *
     * @param string $hex malformed hexadecimal fixture
     */
    #[DataProvider('invalidMembers')]
    public function testMalformedMember(string $hex): void
    {
        $this->assertRejected(pack('H*', $hex));
    }

    /**
     * Check every output mode without returning a partially built object.
     *
     * @param string $binary invalid bytes
     */
    private function assertRejected(string $binary): void
    {
        foreach ([false, true] as $mutable) {
            $decoder = new Decoder(new WkbDecoderStrategy(['mutable' => $mutable]));
            $previous = $decoder->decode(pack('H*', '0107000000010000000101000000000000000000f03f0000000000000040'));
            try {
                $decoder->decode($binary);
                self::fail('Expected incompatible collection rejection.');
            } catch (InvalidArgumentException) {
                self::assertSame([[1.0, 2.0]], $previous->toArray());
                self::assertSame([], $decoder->decode(pack('H*', '010700000000000000'))->toArray());
            }
        }
    }
}
