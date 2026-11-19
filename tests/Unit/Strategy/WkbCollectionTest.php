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
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Factory\SpatialFactory;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Literal binary fields cover collection graphs independently of an encoder.
 *
 * All 64 same-order fixtures were also matched against independently constructed
 * spatial-encoder cfc59cd8792bb24a5bdd6bc29877d3fb6d21ba30 output in a separate process.
 * No encoder package or autoloader is required to run these tests.
 */
final class WkbCollectionTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function fixtures(): iterable
    {
        // Headers, counters and ordinates are explicit; concatenation only exposes the nested layout.
        foreach (self::layouts() as $name => [$headers, $zero, $one, $two, $three, $coordinates, $nan, $marker, $pointText]) {
            [$pointHeader, $lineHeader, $polygonHeader, $multiPointHeader, $multiLineHeader, $multiPolygonHeader, $collectionHeader] = $headers;
            $point = $pointHeader.$coordinates;
            $emptyPoint = $pointHeader.$nan;
            $line = $lineHeader.$one.$coordinates;
            $polygon = $polygonHeader.$one.$one.$coordinates;
            $multiPoint = $multiPointHeader.$three.$point.$emptyPoint.$point;
            $multiLine = $multiLineHeader.$three.$line.$lineHeader.$zero.$line;
            $multiPolygon = $multiPolygonHeader.$three.$polygon.$polygonHeader.$zero.$polygon;
            yield $name.' MultiPoint' => [$multiPoint, 'MULTIPOINT'.$marker.' (('.$pointText.'),EMPTY,('.$pointText.'))'];
            yield $name.' MultiLineString' => [$multiLine, 'MULTILINESTRING'.$marker.' (('.$pointText.'),EMPTY,('.$pointText.'))'];
            yield $name.' MultiPolygon' => [$multiPolygon, 'MULTIPOLYGON'.$marker.' ((('.$pointText.')),EMPTY,(('.$pointText.')))'];
            $nested = $collectionHeader.$two.$collectionHeader.$zero.$point;
            yield $name.' nested collection' => [
                $collectionHeader.$three.$multiPoint.$collectionHeader.$three.$multiLine.$multiPolygon.$nested.$polygon,
                'GEOMETRYCOLLECTION'.$marker.' (MULTIPOINT (('.$pointText.'),EMPTY,('.$pointText.')),GEOMETRYCOLLECTION (MULTILINESTRING (('.$pointText.'),EMPTY,('.$pointText.')),MULTIPOLYGON ((('.$pointText.')),EMPTY,(('.$pointText.'))),GEOMETRYCOLLECTION (GEOMETRYCOLLECTION EMPTY,POINT ('.$pointText.'))),POLYGON (('.$pointText.')))',
            ];
            foreach ([$multiPointHeader => 'MULTIPOINT', $multiLineHeader => 'MULTILINESTRING', $multiPolygonHeader => 'MULTIPOLYGON', $collectionHeader => 'GEOMETRYCOLLECTION'] as $header => $type) {
                yield $name.' '.$type.' EMPTY' => [$header.$zero, $type.$marker.' EMPTY'];
            }
        }
    }

    /**
     * Eight literal layouts: XY/XYZ/XYM/XYZM, little and big endian.
     *
     * @return iterable<string, array{list<string>, string, string, string, string, string, string, string, string}>
     */
    public static function layouts(): iterable
    {
        yield 'XY LE' => [['0101000000', '0102000000', '0103000000', '0104000000', '0105000000', '0106000000', '0107000000'], '00000000', '01000000', '02000000', '03000000', '000000000000f03f0000000000000040', '000000000000f87f000000000000f87f', '', '1.0 2.0'];
        yield 'XY BE' => [['0000000001', '0000000002', '0000000003', '0000000004', '0000000005', '0000000006', '0000000007'], '00000000', '00000001', '00000002', '00000003', '3ff00000000000004000000000000000', '7ff80000000000007ff8000000000000', '', '1.0 2.0'];
        yield 'XYZ LE' => [['01e9030000', '01ea030000', '01eb030000', '01ec030000', '01ed030000', '01ee030000', '01ef030000'], '00000000', '01000000', '02000000', '03000000', '000000000000f03f00000000000000400000000000000840', '000000000000f87f000000000000f87f000000000000f87f', ' Z', '1.0 2.0 3.0'];
        yield 'XYZ BE' => [['00000003e9', '00000003ea', '00000003eb', '00000003ec', '00000003ed', '00000003ee', '00000003ef'], '00000000', '00000001', '00000002', '00000003', '3ff000000000000040000000000000004008000000000000', '7ff80000000000007ff80000000000007ff8000000000000', ' Z', '1.0 2.0 3.0'];
        yield 'XYM LE' => [['01d1070000', '01d2070000', '01d3070000', '01d4070000', '01d5070000', '01d6070000', '01d7070000'], '00000000', '01000000', '02000000', '03000000', '000000000000f03f00000000000000400000000000002240', '000000000000f87f000000000000f87f000000000000f87f', ' M', '1.0 2.0 9.0'];
        yield 'XYM BE' => [['00000007d1', '00000007d2', '00000007d3', '00000007d4', '00000007d5', '00000007d6', '00000007d7'], '00000000', '00000001', '00000002', '00000003', '3ff000000000000040000000000000004022000000000000', '7ff80000000000007ff80000000000007ff8000000000000', ' M', '1.0 2.0 9.0'];
        yield 'XYZM LE' => [['01b90b0000', '01ba0b0000', '01bb0b0000', '01bc0b0000', '01bd0b0000', '01be0b0000', '01bf0b0000'], '00000000', '01000000', '02000000', '03000000', '000000000000f03f000000000000004000000000000008400000000000002240', '000000000000f87f000000000000f87f000000000000f87f000000000000f87f', ' ZM', '1.0 2.0 3.0 9.0'];
        yield 'XYZM BE' => [['0000000bb9', '0000000bba', '0000000bbb', '0000000bbc', '0000000bbd', '0000000bbe', '0000000bbf'], '00000000', '00000001', '00000002', '00000003', '3ff0000000000000400000000000000040080000000000004022000000000000', '7ff80000000000007ff80000000000007ff80000000000007ff8000000000000', ' ZM', '1.0 2.0 3.0 9.0'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function mixedByteOrders(): iterable
    {
        $pointLE = '0101000000000000000000f03f0000000000000040';
        $pointBE = '000000000140080000000000004010000000000000';
        yield 'LE parent BE then LE points' => ['010400000002000000'.$pointBE.$pointLE, 'MULTIPOINT ((3.0 4.0),(1.0 2.0))'];
        yield 'BE parent LE then BE points' => ['000000000400000002'.$pointLE.$pointBE, 'MULTIPOINT ((1.0 2.0),(3.0 4.0))'];
        $lineBE = '00000000020000000140080000000000004010000000000000';
        $polygonLE = '01030000000100000001000000000000000000f03f0000000000000040';
        yield 'alternating nested headers and following sibling' => [
            '010700000003000000000000000700000002'.$lineBE.'010700000000000000'.$polygonLE.$pointBE,
            'GEOMETRYCOLLECTION (GEOMETRYCOLLECTION (LINESTRING (3.0 4.0),GEOMETRYCOLLECTION EMPTY),POLYGON ((1.0 2.0)),POINT (3.0 4.0))',
        ];
        yield 'mixed line members' => ['010500000002000000'.$lineBE.'010200000001000000000000000000f03f0000000000000040', 'MULTILINESTRING ((3.0 4.0),(1.0 2.0))'];
        yield 'mixed polygon members' => ['000000000600000002'.$polygonLE.'0000000003000000010000000140080000000000004010000000000000', 'MULTIPOLYGON (((1.0 2.0)),((3.0 4.0)))'];
    }

    /**
     * Compare both the full object graph and its exact coordinate data.
     *
     * @param string $hex literal nested WKB
     * @param string $wkt equivalent text graph
     */
    #[DataProvider('fixtures')]
    #[DataProvider('mixedByteOrders')]
    public function testDecode(string $hex, string $wkt): void
    {
        $expected = new WktDecoderStrategy()->decode($wkt);
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                SpatialFactory::mutable();
                try {
                    $actual = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode(pack('H*', $hex));
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
     * Every truncated prefix and extra root byte is rejected atomically.
     *
     * @param string $hex literal nested WKB
     * @param string $wkt equivalent text graph
     */
    #[DataProvider('fixtures')]
    #[DataProvider('mixedByteOrders')]
    public function testLengths(string $hex, string $wkt): void
    {
        $binary = pack('H*', $hex);
        $decoder = new Decoder(new WkbDecoderStrategy());
        $invalid = [$binary."\0", $binary.$binary];
        foreach (range(0, \strlen($binary) - 1) as $length) {
            $invalid[] = substr($binary, 0, $length);
        }
        foreach ($invalid as $input) {
            $this->assertRejectedAndReusable($decoder, $input);
        }
        self::assertSame(new WktDecoderStrategy()->decode($wkt)->toArray(), $decoder->decode($binary)->toArray());
    }

    /**
     * Verify type, order, dimensions, family and representation at every level.
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
     * A failed member never leaks a graph or poisons the next call.
     *
     * @param Decoder $decoder reusable decoder
     * @param string  $input   malformed bytes
     */
    private function assertRejectedAndReusable(Decoder $decoder, string $input): void
    {
        try {
            $decoder->decode($input);
            self::fail('Expected malformed collection rejection.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $decoder->decode(pack('H*', '010700000000000000'))->toArray());
        }
    }

    /**
     * List the immediate children without flattening nested collections.
     *
     * @param SpatialInterface $spatial current graph node
     *
     * @return list<SpatialInterface>
     */
    private function children(SpatialInterface $spatial): array
    {
        return array_values(match (true) {
            $spatial instanceof PolygonInterface => $spatial->getRings(),
            $spatial instanceof LineStringInterface => $spatial->getPoints(),
            $spatial instanceof CollectionInterface => $spatial->getElements(),
            default => [],
        });
    }
}
