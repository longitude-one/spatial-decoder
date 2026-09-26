<?php
/**
 * This file is part of the spatial-decoder project.
 *
 * PHP 8.4 | 8.5
 *
 * Copyright Alexandre Tranchant <alexandre.tranchant@gmail.com> 2026
 * Copyright Longitude One 2026
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

declare(strict_types=1);

namespace LongitudeOne\SpatialDecoder\Tests\Unit;

use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\PolygonWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPolygonFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktSpatialObjectFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class PolygonWktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyPolygonWkts(): iterable
    {
        yield 'XY' => ['POLYGON EMPTY', false, false];
        yield 'XYZ' => ['POLYGON Z EMPTY', true, false];
        yield 'XYM' => ['POLYGON M EMPTY', false, true];
        yield 'XYZM' => ['POLYGON ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPolygonWkts(): iterable
    {
        yield 'empty parentheses' => ['POLYGON ()'];
        yield 'unclosed ring' => ['POLYGON ((0 0, 4 0, 4 4, 1 1))'];
        yield 'too few coordinates' => ['POLYGON ((0 0, 4 0, 0 0))'];
        yield 'empty ring' => ['POLYGON ((0 0, 4 0, 4 4, 0 0), EMPTY)'];
        yield 'inconsistent dimensions' => ['POLYGON ((0 0, 4 0, 4 4, 0 0), (1 1 2, 2 1 3, 2 2 4, 1 1 2))'];
        yield 'unmarked XYZ' => ['POLYGON ((0 0 1, 4 0 2, 4 4 3, 0 0 1))'];
        yield 'trailing ring comma' => ['POLYGON ((0 0, 4 0, 4 4, 0 0),)'];
    }

    /**
     * @return iterable<string, array{string, list<list<list<int|float>>>, bool, bool}>
     */
    public static function polygonWkts(): iterable
    {
        yield 'XY exterior and interior rings' => [
            'POLYGON ((0 0, 4 0, 4 4, 0 0), (1 1, 2 1, 2 2, 1 1))',
            [[[0, 0], [4, 0], [4, 4], [0, 0]], [[1, 1], [2, 1], [2, 2], [1, 1]]],
            false,
            false,
        ];
        yield 'XYZ' => ['POLYGON Z ((0 0 1, 4 0 2, 4 4 3, 0 0 1))', [[[0, 0, 1], [4, 0, 2], [4, 4, 3], [0, 0, 1]]], true, false];
        yield 'XYM' => ['POLYGON M ((0 0 5, 4 0 6, 4 4 7, 0 0 5))', [[[0, 0, 5], [4, 0, 6], [4, 4, 7], [0, 0, 5]]], false, true];
        yield 'XYZM' => ['POLYGON ZM ((0 0 1 5, 4 0 2 6, 4 4 3 7, 0 0 1 5))', [[[0, 0, 1, 5], [4, 0, 2, 6], [4, 4, 3, 7], [0, 0, 1, 5]]], true, true];
    }

    /**
     * Test preservation of coordinate dimensions on empty polygons.
     *
     * @param string $wkt  WKT representation of an empty polygon
     * @param bool   $hasZ whether the polygon has a Z ordinate
     * @param bool   $hasM whether the polygon has an M ordinate
     */
    #[DataProvider('emptyPolygonWkts')]
    public function testDecodePreservesEmptyPolygonDimension(string $wkt, bool $hasZ, bool $hasM): void
    {
        $polygon = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(PolygonInterface::class, $polygon);
        self::assertTrue($polygon->isEmpty());
        self::assertSame($hasZ, $polygon->hasZ());
        self::assertSame($hasM, $polygon->hasM());
    }

    /** Test that exterior and interior rings remain ordered line strings. */
    public function testDecodePreservesExteriorAndInteriorRingsAsLineStrings(): void
    {
        $polygon = (new WktDecoderStrategy())->decode('POLYGON ((0 0, 4 0, 4 4, 0 0), (1 1, 2 1, 2 2, 1 1))');

        self::assertInstanceOf(PolygonInterface::class, $polygon);
        $rings = $polygon->getRings();

        self::assertCount(2, $rings);
        self::assertContainsOnlyInstancesOf(LineStringInterface::class, $rings);
        self::assertSame(
            [
                [[0, 0], [4, 0], [4, 4], [0, 0]],
                [[1, 1], [2, 1], [2, 2], [1, 1]],
            ],
            array_map(static fn (LineStringInterface $ring): array => $ring->toArray(), $rings)
        );
    }

    /**
     * Test rejection of malformed polygon representations.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedPolygonWkts')]
    public function testDecodeRejectsMalformedPolygonRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding ordered rings in every coordinate dimension.
     *
     * @param string                      $wkt                 WKT representation of the polygon
     * @param list<list<list<int|float>>> $expectedCoordinates ordered ring coordinates
     * @param bool                        $hasZ                whether the polygon has a Z ordinate
     * @param bool                        $hasM                whether the polygon has an M ordinate
     */
    #[DataProvider('polygonWkts')]
    public function testDecodeSupportsEveryPolygonDimension(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $polygon = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(PolygonInterface::class, $polygon);
        self::assertSame($expectedCoordinates, $polygon->toArray());
        self::assertSame($hasZ, $polygon->hasZ());
        self::assertSame($hasM, $polygon->hasM());
    }
}
