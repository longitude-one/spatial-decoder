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
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Factory\SpatialFactory;
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Literal curve fixtures exercise SQL/MM headers independently of an encoder.
 *
 * All 48 same-order and three mixed-order fixtures matched independently built
 * spatial-encoder cfc59cd8792bb24a5bdd6bc29877d3fb6d21ba30 output in a separate process.
 * Running the tests requires no encoder package or autoloader.
 */
final class WkbCurveTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function fixtures(): iterable
    {
        foreach (self::layouts() as $name => [$headers, $one, $two, $three, $points, $marker, $text]) {
            [$lineHeader, $circularHeader, $compoundHeader, $collectionHeader] = $headers;
            $zero = '00000000';
            $circular = $circularHeader.$three.implode('', $points);
            $circularText = 'CIRCULARSTRING'.$marker.' ('.implode(',', $text).')';
            $compound = $compoundHeader.$three.$lineHeader.$two.$points[0].$points[1].$circular.$lineHeader.$one.$points[2];
            $compoundText = 'COMPOUNDCURVE'.$marker.' (('.$text[0].','.$text[1].'),CIRCULARSTRING ('.implode(',', $text).'),('.$text[2].'))';
            yield $name.' CircularString' => [$circular, $circularText];
            yield $name.' CompoundCurve' => [$compound, $compoundText];
            yield $name.' CircularString EMPTY' => [$circularHeader.$zero, 'CIRCULARSTRING'.$marker.' EMPTY'];
            yield $name.' CompoundCurve EMPTY' => [$compoundHeader.$zero, 'COMPOUNDCURVE'.$marker.' EMPTY'];
            yield $name.' EMPTY components' => [$compoundHeader.$three.$circularHeader.$zero.$circularHeader.$zero.$circular, 'COMPOUNDCURVE'.$marker.' (CIRCULARSTRING EMPTY,CIRCULARSTRING EMPTY,CIRCULARSTRING ('.implode(',', $text).'))'];
            yield $name.' nested GeometryCollection' => [
                $collectionHeader.$two.$circular.$collectionHeader.$three.$compound.$circularHeader.$zero.$compoundHeader.$zero,
                'GEOMETRYCOLLECTION'.$marker.' ('.$circularText.',GEOMETRYCOLLECTION ('.$compoundText.',CIRCULARSTRING EMPTY,COMPOUNDCURVE EMPTY))',
            ];
        }
    }

    /**
     * Explicit binary fields for all four layouts and both byte orders.
     *
     * @return iterable<string, array{list<string>, string, string, string, list<string>, string, list<string>}>
     */
    public static function layouts(): iterable
    {
        yield 'XY LE' => [['0102000000', '0108000000', '0109000000', '0107000000'], '01000000', '02000000', '03000000', ['000000000000f03f00000000000000c0', '00000000000010400000000000001440', '0000000000001c400000000000002040'], '', ['1.0 -2.0', '4.0 5.0', '7.0 8.0']];
        yield 'XY BE' => [['0000000002', '0000000008', '0000000009', '0000000007'], '00000001', '00000002', '00000003', ['3ff0000000000000c000000000000000', '40100000000000004014000000000000', '401c0000000000004020000000000000'], '', ['1.0 -2.0', '4.0 5.0', '7.0 8.0']];
        yield 'XYZ LE' => [['01ea030000', '01f0030000', '01f1030000', '01ef030000'], '01000000', '02000000', '03000000', ['000000000000f03f00000000000000c00000000000000c40', '000000000000104000000000000014400000000000001840', '0000000000001c4000000000000020400000000000002640'], ' Z', ['1.0 -2.0 3.5', '4.0 5.0 6.0', '7.0 8.0 11.0']];
        yield 'XYZ BE' => [['00000003ea', '00000003f0', '00000003f1', '00000003ef'], '00000001', '00000002', '00000003', ['3ff0000000000000c000000000000000400c000000000000', '401000000000000040140000000000004018000000000000', '401c00000000000040200000000000004026000000000000'], ' Z', ['1.0 -2.0 3.5', '4.0 5.0 6.0', '7.0 8.0 11.0']];
        yield 'XYM LE' => [['01d2070000', '01d8070000', '01d9070000', '01d7070000'], '01000000', '02000000', '03000000', ['000000000000f03f00000000000000c00000000000802240', '000000000000104000000000000014400000000000002440', '0000000000001c4000000000000020400000000000002840'], ' M', ['1.0 -2.0 9.25', '4.0 5.0 10.0', '7.0 8.0 12.0']];
        yield 'XYM BE' => [['00000007d2', '00000007d8', '00000007d9', '00000007d7'], '00000001', '00000002', '00000003', ['3ff0000000000000c0000000000000004022800000000000', '401000000000000040140000000000004024000000000000', '401c00000000000040200000000000004028000000000000'], ' M', ['1.0 -2.0 9.25', '4.0 5.0 10.0', '7.0 8.0 12.0']];
        yield 'XYZM LE' => [['01ba0b0000', '01c00b0000', '01c10b0000', '01bf0b0000'], '01000000', '02000000', '03000000', ['000000000000f03f00000000000000c00000000000000c400000000000802240', '0000000000001040000000000000144000000000000018400000000000002440', '0000000000001c40000000000000204000000000000026400000000000002840'], ' ZM', ['1.0 -2.0 3.5 9.25', '4.0 5.0 6.0 10.0', '7.0 8.0 11.0 12.0']];
        yield 'XYZM BE' => [['0000000bba', '0000000bc0', '0000000bc1', '0000000bbf'], '00000001', '00000002', '00000003', ['3ff0000000000000c000000000000000400c0000000000004022800000000000', '4010000000000000401400000000000040180000000000004024000000000000', '401c000000000000402000000000000040260000000000004028000000000000'], ' ZM', ['1.0 -2.0 3.5 9.25', '4.0 5.0 6.0 10.0', '7.0 8.0 11.0 12.0']];
    }

    /** @return iterable<string, array{string, string}> */
    public static function mixedByteOrders(): iterable
    {
        $lineLE = '010200000002000000000000000000f03f00000000000000c000000000000010400000000000001440';
        $circularBE = '0000000008000000033ff0000000000000c00000000000000040100000000000004014000000000000401c0000000000004020000000000000';
        $compound = '010900000003000000'.$circularBE.$lineLE.'000000000800000000';
        $text = 'COMPOUNDCURVE (CIRCULARSTRING (1.0 -2.0,4.0 5.0,7.0 8.0),(1.0 -2.0,4.0 5.0),CIRCULARSTRING EMPTY)';
        yield 'LE compound BE circle LE line BE EMPTY' => [$compound, $text];
        yield 'BE collection LE compound BE sibling' => ['000000000700000002'.$compound.$circularBE, 'GEOMETRYCOLLECTION ('.$text.',CIRCULARSTRING (1.0 -2.0,4.0 5.0,7.0 8.0))'];
        yield 'BE compound LE line BE circle' => ['000000000900000002'.$lineLE.$circularBE, 'COMPOUNDCURVE ((1.0 -2.0,4.0 5.0),CIRCULARSTRING (1.0 -2.0,4.0 5.0,7.0 8.0))'];
    }

    /**
     * Preserve type, control-point order, dimensions and options at every node.
     *
     * @param string $hex literal WKB fields
     * @param string $wkt equivalent WKT curve graph
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
     * Reject every truncated prefix and trailing bytes, including within curves.
     *
     * @param string $hex complete curve fixture
     * @param string $wkt equivalent WKT curve graph
     */
    #[DataProvider('fixtures')]
    #[DataProvider('mixedByteOrders')]
    public function testLengths(string $hex, string $wkt): void
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
                self::fail('Expected malformed curve rejection.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $decoder->decode(pack('H*', '010900000000000000'))->toArray());
            }
        }
        self::assertSame(new WktDecoderStrategy()->decode($wkt)->toArray(), $decoder->decode($binary)->toArray());
    }

    /**
     * Verify the complete graph without flattening compound components.
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
     * Walk both curve representations and containing collections.
     *
     * @param SpatialInterface $spatial current graph node
     *
     * @return list<SpatialInterface>
     */
    private function children(SpatialInterface $spatial): array
    {
        return array_values(match (true) {
            $spatial instanceof CompoundCurveInterface => $spatial->getCurves(),
            $spatial instanceof CircularStringInterface, $spatial instanceof LineStringInterface => $spatial->getPoints(),
            $spatial instanceof CollectionInterface => $spatial->getElements(),
            default => [],
        });
    }
}
