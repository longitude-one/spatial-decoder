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
use LongitudeOne\SpatialTypes\Interfaces\MultiPolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\MultiPolygonWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\PolygonWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPolygonFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPolygonFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class MultiPolygonWktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyMultiPolygonWkts(): iterable
    {
        yield 'XY' => ['MULTIPOLYGON EMPTY', false, false];
        yield 'XYZ' => ['MULTIPOLYGON Z EMPTY', true, false];
        yield 'XYM' => ['MULTIPOLYGON M EMPTY', false, true];
        yield 'XYZM' => ['MULTIPOLYGON ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedMultiPolygonWkts(): iterable
    {
        yield 'empty parentheses' => ['MULTIPOLYGON ()'];
        yield 'unclosed polygon member' => ['MULTIPOLYGON (((0 0, 4 0, 4 4, 1 1)))'];
        yield 'missing polygon parentheses' => ['MULTIPOLYGON ((0 0, 4 0, 4 4, 0 0))'];
        yield 'inconsistent dimensions' => ['MULTIPOLYGON (((0 0, 4 0, 4 4, 0 0)), ((1 1 2, 2 1 3, 2 2 4, 1 1 2)))'];
        yield 'trailing polygon comma' => ['MULTIPOLYGON (((0 0, 4 0, 4 4, 0 0)),)'];
        yield 'trailing input' => ['MULTIPOLYGON EMPTY trailing'];
    }

    /**
     * @return iterable<string, array{string, list<list<list<list<int|float>>>>, bool, bool}>
     */
    public static function multiPolygonWkts(): iterable
    {
        yield 'XY with interior ring' => [
            'MULTIPOLYGON (((0 0, 4 0, 4 4, 0 0), (1 1, 2 1, 2 2, 1 1)), ((5 5, 8 5, 8 8, 5 5)))',
            [[[[0, 0], [4, 0], [4, 4], [0, 0]], [[1, 1], [2, 1], [2, 2], [1, 1]]], [[[5, 5], [8, 5], [8, 8], [5, 5]]]],
            false,
            false,
        ];
        yield 'XYZ' => ['MULTIPOLYGON Z (((0 0 1, 4 0 2, 4 4 3, 0 0 1)))', [[[[0, 0, 1], [4, 0, 2], [4, 4, 3], [0, 0, 1]]]], true, false];
        yield 'XYM' => ['MULTIPOLYGON M (((0 0 5, 4 0 6, 4 4 7, 0 0 5)))', [[[[0, 0, 5], [4, 0, 6], [4, 4, 7], [0, 0, 5]]]], false, true];
        yield 'XYZM' => ['MULTIPOLYGON ZM (((0 0 1 5, 4 0 2 6, 4 4 3 7, 0 0 1 5)))', [[[[0, 0, 1, 5], [4, 0, 2, 6], [4, 4, 3, 7], [0, 0, 1, 5]]]], true, true];
    }

    /**
     * Test preservation of coordinate dimensions on empty multi-polygons.
     *
     * @param string $wkt  WKT representation of an empty multi-polygon
     * @param bool   $hasZ whether the multi-polygon has a Z ordinate
     * @param bool   $hasM whether the multi-polygon has an M ordinate
     */
    #[DataProvider('emptyMultiPolygonWkts')]
    public function testDecodePreservesEmptyMultiPolygonDimension(string $wkt, bool $hasZ, bool $hasM): void
    {
        $multiPolygon = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiPolygonInterface::class, $multiPolygon);
        self::assertTrue($multiPolygon->isEmpty());
        self::assertSame($hasZ, $multiPolygon->hasZ());
        self::assertSame($hasM, $multiPolygon->hasM());
    }

    /** Test preservation of empty polygons inside a multi-polygon. */
    public function testDecodePreservesEmptyPolygonMembers(): void
    {
        $multiPolygon = (new WktDecoderStrategy())->decode('MULTIPOLYGON Z (EMPTY, ((0 0 1, 4 0 2, 4 4 3, 0 0 1)))');

        self::assertInstanceOf(MultiPolygonInterface::class, $multiPolygon);
        self::assertSame([[], [[[0, 0, 1], [4, 0, 2], [4, 4, 3], [0, 0, 1]]]], $multiPolygon->toArray());
        self::assertTrue($multiPolygon->getPolygons()[0]->isEmpty());
        self::assertTrue($multiPolygon->getPolygons()[0]->hasZ());
    }

    /** Test a four-member multi-polygon that contains one empty polygon. */
    public function testDecodePreservesFourPolygonMembersIncludingAnEmptyPolygon(): void
    {
        $multiPolygon = (new WktDecoderStrategy())->decode(
            'MULTIPOLYGON (((0 0, 4 0, 4 4, 0 0)), EMPTY, ((5 5, 8 5, 8 8, 5 5)), ((10 10, 12 10, 12 12, 10 10)))'
        );

        self::assertInstanceOf(MultiPolygonInterface::class, $multiPolygon);
        $polygons = $multiPolygon->getPolygons();

        self::assertCount(4, $polygons);
        self::assertContainsOnlyInstancesOf(PolygonInterface::class, $polygons);
        self::assertTrue($polygons[1]->isEmpty());
        self::assertSame(
            [
                [[[0, 0], [4, 0], [4, 4], [0, 0]]],
                [],
                [[[5, 5], [8, 5], [8, 8], [5, 5]]],
                [[[10, 10], [12, 10], [12, 12], [10, 10]]],
            ],
            $multiPolygon->toArray()
        );
    }

    /** Test that a polygon member with a hole retains two ordered line-string rings. */
    public function testDecodePreservesHoleInsideOneOfTwoPolygons(): void
    {
        $multiPolygon = (new WktDecoderStrategy())->decode(
            'MULTIPOLYGON (((0 0, 6 0, 6 6, 0 0), (1 1, 2 1, 2 2, 1 1)), ((10 10, 14 10, 14 14, 10 10)))'
        );

        self::assertInstanceOf(MultiPolygonInterface::class, $multiPolygon);
        $polygons = $multiPolygon->getPolygons();

        self::assertCount(2, $polygons);
        self::assertContainsOnlyInstancesOf(PolygonInterface::class, $polygons);
        self::assertCount(2, $polygons[0]->getRings());
        self::assertCount(1, $polygons[1]->getRings());
        self::assertContainsOnlyInstancesOf(LineStringInterface::class, $polygons[0]->getRings());
        self::assertSame(
            [
                [
                    [[0, 0], [6, 0], [6, 6], [0, 0]],
                    [[1, 1], [2, 1], [2, 2], [1, 1]],
                ],
                [
                    [[10, 10], [14, 10], [14, 14], [10, 10]],
                ],
            ],
            $multiPolygon->toArray()
        );
    }

    /**
     * Test rejection of malformed multi-polygon representations.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedMultiPolygonWkts')]
    public function testDecodeRejectsMalformedMultiPolygonRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding ordered polygons and rings in every coordinate dimension.
     *
     * @param string                            $wkt                 WKT representation of the multi-polygon
     * @param list<list<list<list<int|float>>>> $expectedCoordinates expected ordered polygon coordinates
     * @param bool                              $hasZ                whether the multi-polygon has a Z ordinate
     * @param bool                              $hasM                whether the multi-polygon has an M ordinate
     */
    #[DataProvider('multiPolygonWkts')]
    public function testDecodeSupportsEveryMultiPolygonDimension(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $multiPolygon = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiPolygonInterface::class, $multiPolygon);
        self::assertSame($expectedCoordinates, $multiPolygon->toArray());
        self::assertSame($hasZ, $multiPolygon->hasZ());
        self::assertSame($hasM, $multiPolygon->hasM());
    }
}
