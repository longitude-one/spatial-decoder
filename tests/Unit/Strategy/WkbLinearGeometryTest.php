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
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Factory\SpatialFactory;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Explicit fixtures matched against spatial-encoder cfc59cd8792bb24a5bdd6bc29877d3fb6d21ba30. */
final class WkbLinearGeometryTest extends TestCase
{
    /** @return iterable<string, array{string, CoordinateDimensionEnum, GeometryTypeEnum, array<mixed>}> */
    public static function fixtures(): iterable
    {
        yield 'LineString XY LE' => ['010200000002000000000000000000f03f00000000000000c000000000000010400000000000001440', CoordinateDimensionEnum::XY, GeometryTypeEnum::LINESTRING, [[1.0, -2.0], [4.0, 5.0]]];
        yield 'LineString XY LE EMPTY' => ['010200000000000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XY LE' => ['01030000000100000002000000000000000000f03f00000000000000c000000000000010400000000000001440', CoordinateDimensionEnum::XY, GeometryTypeEnum::POLYGON, [[[1.0, -2.0], [4.0, 5.0]]]];
        yield 'Polygon XY LE EMPTY' => ['010300000000000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XY BE' => ['0000000002000000023ff0000000000000c00000000000000040100000000000004014000000000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::LINESTRING, [[1.0, -2.0], [4.0, 5.0]]];
        yield 'LineString XY BE EMPTY' => ['000000000200000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XY BE' => ['000000000300000001000000023ff0000000000000c00000000000000040100000000000004014000000000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::POLYGON, [[[1.0, -2.0], [4.0, 5.0]]]];
        yield 'Polygon XY BE EMPTY' => ['000000000300000000', CoordinateDimensionEnum::XY, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYZ LE' => [
            '01ea03000002000000000000000000f03f00000000000000c00000000000000c40000000000000104000000000000014400000000000001840', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 3.5], [4.0, 5.0, 6.0]]];
        yield 'LineString XYZ LE EMPTY' => ['01ea03000000000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYZ LE' => [
            '01eb0300000100000002000000000000000000f03f00000000000000c00000000000000c40000000000000104000000000000014400000000000001840', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 3.5], [4.0, 5.0, 6.0]]]];
        yield 'Polygon XYZ LE EMPTY' => ['01eb03000000000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYZ BE' => [
            '00000003ea000000023ff0000000000000c000000000000000400c000000000000401000000000000040140000000000004018000000000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 3.5], [4.0, 5.0, 6.0]]];
        yield 'LineString XYZ BE EMPTY' => ['00000003ea00000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYZ BE' => [
            '00000003eb00000001000000023ff0000000000000c000000000000000400c000000000000401000000000000040140000000000004018000000000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 3.5], [4.0, 5.0, 6.0]]]];
        yield 'Polygon XYZ BE EMPTY' => ['00000003eb00000000', CoordinateDimensionEnum::XYZ, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYM LE' => [
            '01d207000002000000000000000000f03f00000000000000c00000000000802240000000000000104000000000000014400000000000002440', CoordinateDimensionEnum::XYM, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 9.25], [4.0, 5.0, 10.0]]];
        yield 'LineString XYM LE EMPTY' => ['01d207000000000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYM LE' => [
            '01d30700000100000002000000000000000000f03f00000000000000c00000000000802240000000000000104000000000000014400000000000002440', CoordinateDimensionEnum::XYM, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 9.25], [4.0, 5.0, 10.0]]]];
        yield 'Polygon XYM LE EMPTY' => ['01d307000000000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYM BE' => [
            '00000007d2000000023ff0000000000000c0000000000000004022800000000000401000000000000040140000000000004024000000000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 9.25], [4.0, 5.0, 10.0]]];
        yield 'LineString XYM BE EMPTY' => ['00000007d200000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYM BE' => [
            '00000007d300000001000000023ff0000000000000c0000000000000004022800000000000401000000000000040140000000000004024000000000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 9.25], [4.0, 5.0, 10.0]]]];
        yield 'Polygon XYM BE EMPTY' => ['00000007d300000000', CoordinateDimensionEnum::XYM, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYZM LE' => [
            '01ba0b000002000000000000000000f03f00000000000000c00000000000000c4000000000008022400000000000001040000000000000144000000000000018400000000000002440', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 3.5, 9.25], [4.0, 5.0, 6.0, 10.0]]];
        yield 'LineString XYZM LE EMPTY' => ['01ba0b000000000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYZM LE' => [
            '01bb0b00000100000002000000000000000000f03f00000000000000c00000000000000c4000000000008022400000000000001040000000000000144000000000000018400000000000002440', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 3.5, 9.25], [4.0, 5.0, 6.0, 10.0]]]];
        yield 'Polygon XYZM LE EMPTY' => ['01bb0b000000000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::POLYGON, []];
        yield 'LineString XYZM BE' => [
            '0000000bba000000023ff0000000000000c000000000000000400c00000000000040228000000000004010000000000000401400000000000040180000000000004024000000000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::LINESTRING, [[1.0, -2.0, 3.5, 9.25], [4.0, 5.0, 6.0, 10.0]]];
        yield 'LineString XYZM BE EMPTY' => ['0000000bba00000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::LINESTRING, []];
        yield 'Polygon XYZM BE' => [
            '0000000bbb00000001000000023ff0000000000000c000000000000000400c00000000000040228000000000004010000000000000401400000000000040180000000000004024000000000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::POLYGON, [[[1.0, -2.0, 3.5, 9.25], [4.0, 5.0, 6.0, 10.0]]]];
        yield 'Polygon XYZM BE EMPTY' => ['0000000bbb00000000', CoordinateDimensionEnum::XYZM, GeometryTypeEnum::POLYGON, []];
    }

    /**
     * Preserve type, order and options throughout the constructed graph.
     *
     * @param string                  $hex         explicit WKB bytes
     * @param CoordinateDimensionEnum $dimension   expected layout
     * @param GeometryTypeEnum        $type        expected geometry type
     * @param array<mixed>            $coordinates expected coordinates
     */
    #[DataProvider('fixtures')]
    public function testDecode(string $hex, CoordinateDimensionEnum $dimension, GeometryTypeEnum $type, array $coordinates): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                // The process-wide factory must not override the local decoder options.
                SpatialFactory::mutable();
                try {
                    $spatial = new Decoder(new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode(pack('H*', $hex));
                    self::assertSame($type, $spatial->getType());
                    self::assertSame($coordinates, $spatial->toArray());
                    $this->assertGraph($spatial, $dimension, $mode, $mutable);
                    self::assertTrue(SpatialFactory::isMutable());
                } finally {
                    SpatialFactory::immutable();
                }
            }
        }
    }

    /**
     * Every truncated prefix and trailing bytes fail without poisoning reuse.
     *
     * @param string                  $hex         explicit WKB bytes
     * @param CoordinateDimensionEnum $dimension   expected layout
     * @param GeometryTypeEnum        $type        expected geometry type
     * @param array<mixed>            $coordinates expected coordinates
     */
    #[DataProvider('fixtures')]
    public function testLengths(string $hex, CoordinateDimensionEnum $dimension, GeometryTypeEnum $type, array $coordinates): void
    {
        $binary = pack('H*', $hex);
        $decoder = new Decoder(new WkbDecoderStrategy());
        $invalid = [$binary."\0", $binary.$binary];
        foreach (range(0, \strlen($binary) - 1) as $length) {
            $invalid[] = substr($binary, 0, $length);
        }
        foreach ($invalid as $input) {
            try {
                $decoder->decode($input);
                self::fail('Expected structural rejection.');
            } catch (InvalidArgumentException) {
                $spatial = $decoder->decode($binary);
                self::assertSame($dimension, $spatial->getDimension());
                self::assertSame($type, $spatial->getType());
                self::assertSame($coordinates, $spatial->toArray());
            }
        }
    }

    /**
     * Check representation options recursively on rings and vertices.
     *
     * @param SpatialInterface        $spatial   decoded object
     * @param CoordinateDimensionEnum $dimension declared layout
     * @param SpatialModelEnum        $mode      selected output family
     * @param bool                    $mutable   selected mutability
     */
    private function assertGraph(SpatialInterface $spatial, CoordinateDimensionEnum $dimension, SpatialModelEnum $mode, bool $mutable): void
    {
        self::assertSame($dimension, $spatial->getDimension());
        self::assertSame($mode, $spatial->getFamily());
        self::assertSame(0, $spatial->getSrid());
        self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $spatial);
        $components = match (true) {
            $spatial instanceof PolygonInterface => $spatial->getRings(),
            $spatial instanceof LineStringInterface => $spatial->getPoints(),
            default => [],
        };
        foreach ($components as $component) {
            $this->assertGraph($component, $dimension, $mode, $mutable);
        }
    }
}
