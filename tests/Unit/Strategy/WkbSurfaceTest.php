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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Factory\SpatialFactory;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolyhedralSurfaceInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Explicit surface fields are independent of any encoder runtime dependency.
 *
 * All 48 same-order and three mixed-order fixtures matched independently built
 * spatial-encoder 0ab48d219cb453caf6c6d0acdf70a8efbb0a93f6 output in a separate process.
 */
final class WkbSurfaceTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function fixtures(): iterable
    {
        foreach (self::layouts() as $name => [$headers, $one, $two, $four, $points, $marker, $text]) {
            [$triangleHeader, $polygonHeader, $surfaceHeader, $collectionHeader] = $headers;
            $ring = $four.$points[0].$points[1].$points[2].$points[0];
            $ringText = '('.implode(',', [...$text, $text[0]]).')';
            $triangle = $triangleHeader.$one.$ring;
            $triangleText = 'TRIANGLE'.$marker.' ('.$ringText.')';
            yield $name.' Triangle' => [$triangle, $triangleText];
            yield $name.' Triangle EMPTY' => [$triangleHeader.'00000000', 'TRIANGLE'.$marker.' EMPTY'];
            yield $name.' Triangle rings' => [$triangleHeader.$two.$ring.$four.$points[2].$points[1].$points[0].$points[2], 'TRIANGLE'.$marker.' ('.$ringText.',('.implode(',', [$text[2], $text[1], $text[0], $text[2]]).'))'];
            yield $name.' nested Triangle' => [$collectionHeader.$two.$triangle.$collectionHeader.$one.$triangleHeader.'00000000', 'GEOMETRYCOLLECTION'.$marker.' ('.$triangleText.',GEOMETRYCOLLECTION (TRIANGLE EMPTY))'];
            if (!str_contains($marker, 'Z')) {
                continue;
            }
            $patch = $polygonHeader.$one.$ring;
            $patchWithHole = $polygonHeader.$two.$ring.$four.$points[2].$points[1].$points[0].$points[2];
            $surface = $surfaceHeader.$two.$patch.$patchWithHole;
            $surfaceText = 'POLYHEDRALSURFACE'.$marker.' (('.$ringText.'),('.$ringText.',('.implode(',', [$text[2], $text[1], $text[0], $text[2]]).')))';
            yield $name.' multiple patches and rings' => [$surface, $surfaceText];
            yield $name.' PolyhedralSurface EMPTY' => [$surfaceHeader.'00000000', 'POLYHEDRALSURFACE'.$marker.' EMPTY'];
            yield $name.' EMPTY patches' => [$surfaceHeader.$two.$polygonHeader.'00000000'.$patch, 'POLYHEDRALSURFACE'.$marker.' (EMPTY,('.$ringText.'))'];
            yield $name.' nested surfaces' => [$collectionHeader.$two.$surface.$collectionHeader.$two.$triangle.$surfaceHeader.'00000000', 'GEOMETRYCOLLECTION'.$marker.' ('.$surfaceText.',GEOMETRYCOLLECTION ('.$triangleText.',POLYHEDRALSURFACE EMPTY))'];
        }
    }

    /**
     * Literal Triangle, Polygon, PolyhedralSurface and collection headers.
     *
     * @return iterable<string, array{list<string>, string, string, string, list<string>, string, list<string>}>
     */
    public static function layouts(): iterable
    {
        yield 'XY LE' => [['0111000000', '0103000000', '010f000000', '0107000000'], '01000000', '02000000', '04000000', ['000000000000f03f00000000000000c0', '00000000000010400000000000001440', '0000000000001c400000000000002040'], '', ['1.0 -2.0', '4.0 5.0', '7.0 8.0']];
        yield 'XY BE' => [['0000000011', '0000000003', '000000000f', '0000000007'], '00000001', '00000002', '00000004', ['3ff0000000000000c000000000000000', '40100000000000004014000000000000', '401c0000000000004020000000000000'], '', ['1.0 -2.0', '4.0 5.0', '7.0 8.0']];
        yield 'XYZ LE' => [['01f9030000', '01eb030000', '01f7030000', '01ef030000'], '01000000', '02000000', '04000000', ['000000000000f03f00000000000000c00000000000000c40', '000000000000104000000000000014400000000000001840', '0000000000001c4000000000000020400000000000002640'], ' Z', ['1.0 -2.0 3.5', '4.0 5.0 6.0', '7.0 8.0 11.0']];
        yield 'XYZ BE' => [['00000003f9', '00000003eb', '00000003f7', '00000003ef'], '00000001', '00000002', '00000004', ['3ff0000000000000c000000000000000400c000000000000', '401000000000000040140000000000004018000000000000', '401c00000000000040200000000000004026000000000000'], ' Z', ['1.0 -2.0 3.5', '4.0 5.0 6.0', '7.0 8.0 11.0']];
        yield 'XYM LE' => [['01e1070000', '01d3070000', '01df070000', '01d7070000'], '01000000', '02000000', '04000000', ['000000000000f03f00000000000000c00000000000802240', '000000000000104000000000000014400000000000002440', '0000000000001c4000000000000020400000000000002840'], ' M', ['1.0 -2.0 9.25', '4.0 5.0 10.0', '7.0 8.0 12.0']];
        yield 'XYM BE' => [['00000007e1', '00000007d3', '00000007df', '00000007d7'], '00000001', '00000002', '00000004', ['3ff0000000000000c0000000000000004022800000000000', '401000000000000040140000000000004024000000000000', '401c00000000000040200000000000004028000000000000'], ' M', ['1.0 -2.0 9.25', '4.0 5.0 10.0', '7.0 8.0 12.0']];
        yield 'XYZM LE' => [['01c90b0000', '01bb0b0000', '01c70b0000', '01bf0b0000'], '01000000', '02000000', '04000000', ['000000000000f03f00000000000000c00000000000000c400000000000802240', '0000000000001040000000000000144000000000000018400000000000002440', '0000000000001c40000000000000204000000000000026400000000000002840'], ' ZM', ['1.0 -2.0 3.5 9.25', '4.0 5.0 6.0 10.0', '7.0 8.0 11.0 12.0']];
        yield 'XYZM BE' => [['0000000bc9', '0000000bbb', '0000000bc7', '0000000bbf'], '00000001', '00000002', '00000004', ['3ff0000000000000c000000000000000400c0000000000004022800000000000', '4010000000000000401400000000000040180000000000004024000000000000', '401c000000000000402000000000000040260000000000004028000000000000'], ' ZM', ['1.0 -2.0 3.5 9.25', '4.0 5.0 6.0 10.0', '7.0 8.0 11.0 12.0']];
    }

    /** @return iterable<string, array{string, string}> */
    public static function mixedByteOrders(): iterable
    {
        $patchLE = '01eb0300000100000001000000000000000000f03f00000000000000c00000000000000c40';
        $patchBE = '00000003eb0000000100000001401000000000000040140000000000004018000000000000';
        $surface = '01f703000002000000'.$patchBE.$patchLE;
        $text = 'POLYHEDRALSURFACE Z (((4.0 5.0 6.0)),((1.0 -2.0 3.5)))';
        yield 'LE surface BE patch LE patch' => [$surface, $text];
        yield 'BE surface LE patch BE patch' => ['00000003f700000002'.$patchLE.$patchBE, 'POLYHEDRALSURFACE Z (((1.0 -2.0 3.5)),((4.0 5.0 6.0)))'];
        yield 'BE collection LE surface BE triangle' => ['00000003ef00000002'.$surface.'00000003f900000000', 'GEOMETRYCOLLECTION Z ('.$text.',TRIANGLE EMPTY)'];
    }

    /**
     * Match EWKT construction and verify options recursively at every graph node.
     *
     * @param string $hex  independent WKB fields
     * @param string $ewkt equivalent EWKT geometry
     */
    #[DataProvider('fixtures')]
    #[DataProvider('mixedByteOrders')]
    public function testDecode(string $hex, string $ewkt): void
    {
        $expected = new EwktDecoderStrategy()->decode($ewkt);
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                SpatialFactory::mutable();
                try {
                    $actual = new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable])->decode(pack('H*', $hex));
                    self::assertSame($expected->toArray(), $actual->toArray());
                    $this->assertGraph($expected, $actual, $mode, $mutable);
                    if (SpatialModelEnum::GEOMETRY === $mode && !$mutable) {
                        self::assertEquals($expected, $actual);
                    }
                    self::assertTrue(SpatialFactory::isMutable());
                } finally {
                    SpatialFactory::immutable();
                }
            }
        }
    }

    /**
     * Reject every truncated prefix and extra root bytes; a failure leaves no state.
     *
     * @param string $hex  complete surface bytes
     * @param string $ewkt equivalent EWKT geometry
     */
    #[DataProvider('fixtures')]
    #[DataProvider('mixedByteOrders')]
    public function testLengths(string $hex, string $ewkt): void
    {
        $binary = pack('H*', $hex);
        $decoder = new WkbDecoderStrategy();
        $invalid = [$binary."\0", $binary.$binary];
        foreach (range(0, \strlen($binary) - 1) as $length) {
            $invalid[] = substr($binary, 0, $length);
        }
        foreach ($invalid as $input) {
            try {
                $decoder->decode($input);
                self::fail('Expected malformed surface rejection.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $decoder->decode(pack('H*', '01f703000000000000'))->toArray());
            }
        }
        self::assertSame(new EwktDecoderStrategy()->decode($ewkt)->toArray(), $decoder->decode($binary)->toArray());
    }

    /**
     * Check type, dimension, family, mutability and child order without flattening.
     *
     * @param SpatialInterface $expected expected node
     * @param SpatialInterface $actual   decoded node
     * @param SpatialModelEnum $mode     requested family
     * @param bool             $mutable  requested representation
     */
    private function assertGraph(SpatialInterface $expected, SpatialInterface $actual, SpatialModelEnum $mode, bool $mutable): void
    {
        self::assertSame($expected->getType(), $actual->getType());
        self::assertSame($expected->getDimension(), $actual->getDimension());
        self::assertSame($mode, $actual->getFamily());
        self::assertSame(0, $actual->getSrid());
        self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $actual);
        $expectedChildren = $this->children($expected);
        $actualChildren = $this->children($actual);
        self::assertCount(\count($expectedChildren), $actualChildren);
        foreach ($expectedChildren as $index => $child) {
            $this->assertGraph($child, $actualChildren[$index], $mode, $mutable);
        }
    }

    /**
     * Traverse surface patches, rings, vertices and containing collections.
     *
     * @param SpatialInterface $spatial current graph node
     *
     * @return list<SpatialInterface>
     */
    private function children(SpatialInterface $spatial): array
    {
        return array_values(match (true) {
            $spatial instanceof PolyhedralSurfaceInterface => $spatial->getPatches(),
            $spatial instanceof PolygonInterface => $spatial->getRings(),
            $spatial instanceof LineStringInterface => $spatial->getPoints(),
            $spatial instanceof CollectionInterface => $spatial->getElements(),
            default => [],
        });
    }
}
