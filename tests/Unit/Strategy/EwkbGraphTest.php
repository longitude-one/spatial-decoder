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
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\TestCase;

/** Independent wire fixtures exercise every header and generated model component. */
final class EwkbGraphTest extends TestCase
{
    /** A root without metadata remains zero after explicit child references, including all-NaN EMPTY. */
    public function testAbsentRootAndEmptyPoint(): void
    {
        $binary = pack('H*', implode('', [
            '010700000003000000',
            '002000000100000f113ff00000000000004000000000000000',
            '0101000000000000000000f03f0000000000000040',
            '0020000001000000007ff80000000000007ff8000000000000',
        ]));
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $spatial = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode($binary);
                self::assertInstanceOf(CollectionInterface::class, $spatial);
                self::assertSame(0, $spatial->getSrid());
                self::assertSame([[1.0, 2.0], [1.0, 2.0], []], $spatial->toArray());
                foreach ($spatial->getElements() as $index => $member) {
                    self::assertSame(0 === $index ? 3857 : 0, $member->getSrid());
                    self::assertSame(CoordinateDimensionEnum::XY, $member->getDimension());
                    self::assertSame($mode, $member->getFamily());
                    self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $member);
                }
            }
        }
    }

    /** Explicit references affect descendants, never siblings, including EMPTY nodes. */
    public function testNestedReferenceScopes(): void
    {
        foreach (CoordinateDimensionEnum::cases() as $dimension) {
            foreach ([false, true] as $bigEndian) {
                $point = $this->header(1, $dimension, !$bigEndian).$this->tuple($dimension, !$bigEndian);
                $empty = $this->header(2, $dimension, $bigEndian, 0).pack($bigEndian ? 'N' : 'V', 0);
                $zero = $this->header(7, $dimension, $bigEndian, 0).pack($bigEndian ? 'N' : 'V', 1).$point;
                $inherited = $this->header(7, $dimension, $bigEndian).pack($bigEndian ? 'N' : 'V', 1).$point;
                $nested = $this->header(7, $dimension, !$bigEndian, 3857).pack($bigEndian ? 'V' : 'N', 5)
                    .$point.$empty.$zero.$inherited.$point;
                $matching = $this->header(1, $dimension, !$bigEndian, 4326).$this->tuple($dimension, !$bigEndian);
                $binary = $this->header(7, $dimension, $bigEndian, 4326).pack($bigEndian ? 'N' : 'V', 3).$nested.$point.$matching;
                $expected = [7, 4326, [[7, 3857, [[1, 3857, []], [2, 0, []], [7, 0, [[1, 0, []]]],
                    [7, 3857, [[1, 3857, []]]], [1, 3857, []]]], [1, 4326, []], [1, 4326, []]]];
                $this->assertModes($binary, $dimension, $expected);
            }
        }
    }

    /** Rejected nested inputs cannot leak spatial-reference, dimension or byte-order state. */
    public function testRecoveryAfterRejectedGraph(): void
    {
        $prefix = '0107000020e6100000020000000101000020110f0000000000000000f03f0000000000000040';
        $invalid = [
            $prefix.'002000000100', // Truncated big-endian SRID after a completed sibling.
            $prefix.'0101000080000000000000f03f00000000000000400000000000000840', // XYZ under XY.
            $prefix.'0201000000000000000000f03f0000000000000040', // Invalid member byte order.
            '0104000020e6100000010000000102000020110f000000000000', // LineString in MultiPoint.
            '0107000020e610000001000000010f000020110f000000000000', // XY PolyhedralSurface.
            '0107000020e610000001000000010a000020110f000000000000', // Unsupported CurvePolygon.
        ];
        $decoder = new Decoder(new EwkbDecoderStrategy());
        foreach ($invalid as $hex) {
            try {
                $decoder->decode(pack('H*', $hex));
                self::fail('Expected nested input rejection.');
            } catch (InvalidArgumentException) {
                $point = $decoder->decode(pack('H*', '00000000013ff00000000000004000000000000000'));
                self::assertSame(0, $point->getSrid());
                self::assertSame(CoordinateDimensionEnum::XY, $point->getDimension());
                self::assertSame([1.0, 2.0], $point->toArray());
            }
        }
    }

    /** Every aggregate preserves member metadata, including rings and vertices without headers. */
    public function testSupportedStructures(): void
    {
        foreach (CoordinateDimensionEnum::cases() as $dimension) {
            foreach ([false, true] as $bigEndian) {
                $integer = $bigEndian ? 'V' : 'N';
                $tuple = $this->tuple($dimension, !$bigEndian);
                $sequence = pack($integer, 1).$tuple;
                foreach ([4 => 1, 5 => 2, 6 => 3, 9 => 8, 15 => 3] as $parent => $child) {
                    if (15 === $parent && !$dimension->hasZ()) {
                        continue;
                    }
                    $body = $this->body($child, $integer, $tuple);
                    $member = $this->header($child, $dimension, !$bigEndian, 3857).$body;
                    $inherited = $this->header($child, $dimension, !$bigEndian).$body;
                    $binary = $this->header($parent, $dimension, $bigEndian, 4326).pack($bigEndian ? 'N' : 'V', 2).$member.$inherited;
                    $this->assertModes($binary, $dimension, [$parent, 4326, [
                        $this->component($child, 3857), $this->component($child, 4326),
                    ]]);
                }
                // Heterogeneous collections retain curves and triangles as their actual types.
                $curve = $this->header(8, $dimension, !$bigEndian, 3857).$sequence;
                $triangle = $this->header(17, $dimension, !$bigEndian).pack($integer, 1).$sequence;
                $binary = $this->header(7, $dimension, $bigEndian, 4326).pack($bigEndian ? 'N' : 'V', 2).$curve.$triangle;
                $this->assertModes($binary, $dimension, [7, 4326, [$this->component(8, 3857), $this->component(17, 4326)]]);
            }
        }
    }

    /**
     * Check every member, generated ring and vertex without metadata propagation afterwards.
     *
     * @param SpatialInterface        $spatial   decoded node
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param array                   $expected  type, effective SRID and recursive children
     * @param SpatialModelEnum        $mode      selected spatial family
     * @param bool                    $mutable   selected mutability
     *
     * @phpstan-param array{int, int, list<mixed>} $expected
     */
    private function assertGraph(SpatialInterface $spatial, CoordinateDimensionEnum $dimension, array $expected, SpatialModelEnum $mode, bool $mutable): void
    {
        self::assertSame($expected[0], $spatial->getType()->wkbCode());
        self::assertSame($expected[1], $spatial->getSrid());
        self::assertSame($dimension, $spatial->getDimension());
        self::assertSame($mode, $spatial->getFamily());
        self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $spatial);
        if ($spatial instanceof PointInterface) {
            $coordinates = [1.0, 2.0];
            if ($dimension->hasZ()) {
                $coordinates[] = 3.0;
            }
            if ($dimension->hasM()) {
                $coordinates[] = 4.0;
            }
            self::assertSame($coordinates, $spatial->toArray());

            return;
        }
        self::assertTrue(method_exists($spatial, 'getElements'));
        $children = $spatial->getElements();
        self::assertIsArray($children);
        self::assertCount(\count($expected[2]), $children);
        foreach ($children as $index => $child) {
            self::assertInstanceOf(SpatialInterface::class, $child);
            $node = $expected[2][$index];
            self::assertIsArray($node);
            self::assertArrayHasKey(0, $node);
            self::assertArrayHasKey(1, $node);
            self::assertArrayHasKey(2, $node);
            self::assertIsInt($node[0]);
            self::assertIsInt($node[1]);
            self::assertIsArray($node[2]);
            self::assertIsList($node[2]);
            $this->assertGraph($child, $dimension, [$node[0], $node[1], $node[2]], $mode, $mutable);
        }
    }

    /**
     * Assert output options recursively, rather than only at the graph root.
     *
     * @param string                  $binary    manually constructed wire bytes
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param array                   $expected  type, effective SRID and recursive children
     *
     * @phpstan-param array{int, int, list<mixed>} $expected
     */
    private function assertModes(string $binary, CoordinateDimensionEnum $dimension, array $expected): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $spatial = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode($binary);
                $this->assertGraph($spatial, $dimension, $expected, $mode, $mutable);
            }
        }
    }

    /**
     * Build an independent populated body with one vertex, or one ring containing that vertex.
     *
     * @param int    $type    wire geometry code
     * @param string $integer fixture unsigned-integer byte order
     * @param string $tuple   fixed IEEE-754 tuple
     */
    private function body(int $type, string $integer, string $tuple): string
    {
        $sequence = pack($integer, 1).$tuple;

        return match ($type) {
            1 => $tuple,
            3 => pack($integer, 1).$sequence,
            default => $sequence,
        };
    }

    /**
     * Expected generated nodes have their containing geometry's effective SRID.
     *
     * @param int $type wire geometry code
     * @param int $srid effective spatial reference
     *
     * @return array{int, int, list<mixed>}
     */
    private function component(int $type, int $srid): array
    {
        $point = [1, $srid, []];
        $children = match ($type) {
            1 => [],
            3, 17 => [[2, $srid, [$point]]],
            default => [$point],
        };

        return [$type, $srid, $children];
    }

    /**
     * Explicit numeric wire values keep fixtures independent from decoder enums and encoder code.
     *
     * @param int                     $type      wire geometry code
     * @param CoordinateDimensionEnum $dimension selected fixture layout
     * @param bool                    $bigEndian fixture byte order
     * @param int|null                $srid      null omits the SRID field; zero writes it explicitly
     */
    private function header(int $type, CoordinateDimensionEnum $dimension, bool $bigEndian, ?int $srid = null): string
    {
        $flags = match ($dimension) {
            CoordinateDimensionEnum::XY => 0,
            CoordinateDimensionEnum::XYZ => 0x80000000,
            CoordinateDimensionEnum::XYM => 0x40000000,
            CoordinateDimensionEnum::XYZM => 0xC0000000,
        };
        $integer = $bigEndian ? 'N' : 'V';

        return ($bigEndian ? "\0" : "\1").pack($integer, $type | $flags | (null === $srid ? 0 : 0x20000000))
            .(null === $srid ? '' : pack($integer, $srid));
    }

    /**
     * Fixed IEEE-754 tuples are independent of the production coordinate reader.
     *
     * @param CoordinateDimensionEnum $dimension selected fixture layout
     * @param bool                    $bigEndian fixture byte order
     */
    private function tuple(CoordinateDimensionEnum $dimension, bool $bigEndian): string
    {
        $ordinates = $bigEndian
            ? ['3ff0000000000000', '4000000000000000', '4008000000000000', '4010000000000000']
            : ['000000000000f03f', '0000000000000040', '0000000000000840', '0000000000001040'];

        return pack('H*', $ordinates[0].$ordinates[1].($dimension->hasZ() ? $ordinates[2] : '').($dimension->hasM() ? $ordinates[3] : ''));
    }
}
