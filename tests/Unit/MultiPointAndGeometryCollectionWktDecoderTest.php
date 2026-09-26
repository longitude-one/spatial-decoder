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

use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\LogicException;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MultiPointInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\GeometryCollectionWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\MultiPointWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPointFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktGeometryCollectionFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class MultiPointAndGeometryCollectionWktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyGeometryCollectionWkts(): iterable
    {
        yield 'XY' => ['GEOMETRYCOLLECTION EMPTY', false, false];
        yield 'XYZ' => ['GEOMETRYCOLLECTION Z EMPTY', true, false];
        yield 'XYM' => ['GEOMETRYCOLLECTION M EMPTY', false, true];
        yield 'XYZM' => ['GEOMETRYCOLLECTION ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyMultiPointWkts(): iterable
    {
        yield 'XY' => ['MULTIPOINT EMPTY', false, false];
        yield 'XYZ' => ['MULTIPOINT Z EMPTY', true, false];
        yield 'XYM' => ['MULTIPOINT M EMPTY', false, true];
        yield 'XYZM' => ['MULTIPOINT ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function geometryCollectionWkts(): iterable
    {
        yield 'XY nested and heterogeneous' => [
            'GEOMETRYCOLLECTION (POINT (1 2), LINESTRING (0 0, 1 1), GEOMETRYCOLLECTION (MULTIPOINT ((3 4), (5 6)), POLYGON ((0 0, 2 0, 2 2, 0 0))))',
            false,
            false,
        ];
        yield 'XYZ inherited by nested members' => [
            'GEOMETRYCOLLECTION Z (POINT (1 2 3), GEOMETRYCOLLECTION (POINT (4 5 6), MULTIPOINT (7 8 9, 10 11 12)))',
            true,
            false,
        ];
        yield 'XYM' => ['GEOMETRYCOLLECTION M (POINT (1 2 3), LINESTRING (4 5 6, 7 8 9))', false, true];
        yield 'XYZM' => ['GEOMETRYCOLLECTION ZM (POINT (1 2 3 4), GEOMETRYCOLLECTION (POINT (5 6 7 8)))', true, true];
        yield 'empty members' => ['GEOMETRYCOLLECTION (POINT EMPTY, MULTIPOINT EMPTY)', false, false];
        yield 'empty nested collection member' => ['GEOMETRYCOLLECTION (GEOMETRYCOLLECTION EMPTY, POINT EMPTY)', false, false];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedGeometryCollectionWkts(): iterable
    {
        yield 'empty parentheses' => ['GEOMETRYCOLLECTION ()'];
        yield 'trailing member comma' => ['GEOMETRYCOLLECTION (POINT (1 2),)'];
        yield 'inconsistent member dimensions' => ['GEOMETRYCOLLECTION (POINT (1 2), POINT Z (3 4 5))'];
        yield 'nested marker mismatch' => ['GEOMETRYCOLLECTION Z (GEOMETRYCOLLECTION M EMPTY)'];
        yield 'coordinate dimension mismatch' => ['GEOMETRYCOLLECTION Z (POINT (1 2))'];
        yield 'unsupported member type' => ['GEOMETRYCOLLECTION (TRIANGLE EMPTY)'];
        yield 'trailing input' => ['GEOMETRYCOLLECTION EMPTY trailing'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedMultiPointWkts(): iterable
    {
        yield 'empty parentheses' => ['MULTIPOINT ()'];
        yield 'mixed point-member syntax' => ['MULTIPOINT ((0 1), 2 3)'];
        yield 'inconsistent dimensions' => ['MULTIPOINT ((0 1), (2 3 4))'];
        yield 'marked dimension mismatch' => ['MULTIPOINT Z (0 1)'];
        yield 'trailing comma' => ['MULTIPOINT (0 1,)'];
        yield 'trailing input' => ['MULTIPOINT EMPTY trailing'];
    }

    /**
     * @return iterable<string, array{string, list<list<int|float>>, bool, bool}>
     */
    public static function multiPointWkts(): iterable
    {
        yield 'XY bare' => ['MULTIPOINT (0 1, 2 3)', [[0, 1], [2, 3]], false, false];
        yield 'XY parenthesized' => ['MULTIPOINT ((0 1), (2 3))', [[0, 1], [2, 3]], false, false];
        yield 'XYZ' => ['MULTIPOINT Z ((0 1 2), (3 4 5))', [[0, 1, 2], [3, 4, 5]], true, false];
        yield 'XYM' => ['MULTIPOINT M (0 1 2, 3 4 5)', [[0, 1, 2], [3, 4, 5]], false, true];
        yield 'XYZM' => ['MULTIPOINT ZM ((0 1 2 3), (4 5 6 7))', [[0, 1, 2, 3], [4, 5, 6, 7]], true, true];
        yield 'empty point members' => ['MULTIPOINT (EMPTY, 2 3)', [[], [2, 3]], false, false];
        yield 'typed empty point members' => ['MULTIPOINT Z (EMPTY, 2 3 4)', [[], [2, 3, 4]], true, false];
    }

    /**
     * Test preserving dimensions on empty geometry collections.
     *
     * @param string $wkt  WKT representation of an empty geometry collection
     * @param bool   $hasZ whether the collection has a Z ordinate
     * @param bool   $hasM whether the collection has an M ordinate
     */
    #[DataProvider('emptyGeometryCollectionWkts')]
    public function testDecodePreservesEmptyGeometryCollectionDimensions(string $wkt, bool $hasZ, bool $hasM): void
    {
        $collection = $this->decodeCollection($wkt);

        self::assertTrue($collection->isEmpty());
        self::assertSame($hasZ, $collection->hasZ());
        self::assertSame($hasM, $collection->hasM());
    }

    /**
     * Test preserving dimensions on empty multi-points.
     *
     * @param string $wkt  WKT representation of an empty multi-point
     * @param bool   $hasZ whether the multi-point has a Z ordinate
     * @param bool   $hasM whether the multi-point has an M ordinate
     */
    #[DataProvider('emptyMultiPointWkts')]
    public function testDecodePreservesEmptyMultiPointDimensions(string $wkt, bool $hasZ, bool $hasM): void
    {
        $multiPoint = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiPointInterface::class, $multiPoint);
        self::assertTrue($multiPoint->isEmpty());
        self::assertSame($hasZ, $multiPoint->hasZ());
        self::assertSame($hasM, $multiPoint->hasM());
    }

    /** Test retaining concrete heterogeneous and nested collection members. */
    public function testDecodePreservesHeterogeneousMemberTypesAndCoordinates(): void
    {
        $collection = $this->decodeCollection(
            'GEOMETRYCOLLECTION (POINT (1 2), LINESTRING (0 0, 1 1), GEOMETRYCOLLECTION (POINT (3 4)))'
        );
        $elements = $collection->getElements();

        self::assertCount(3, $elements);
        self::assertInstanceOf(PointInterface::class, $elements[0]);
        self::assertInstanceOf(LineStringInterface::class, $elements[1]);
        self::assertInstanceOf(CollectionInterface::class, $elements[2]);
        self::assertSame([[1, 2], [[0, 0], [1, 1]], [[3, 4]]], $collection->toArray());
    }

    /**
     * Test rejection of malformed or inconsistent geometry collections.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedGeometryCollectionWkts')]
    public function testDecodeRejectsMalformedGeometryCollectionRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test rejection of malformed multi-point representations.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedMultiPointWkts')]
    public function testDecodeRejectsMalformedMultiPointRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding both supported multi-point member syntaxes without losing ordinates.
     *
     * @param string                $wkt                 WKT representation of the multi-point
     * @param list<list<int|float>> $expectedCoordinates expected ordered coordinates
     * @param bool                  $hasZ                whether the multi-point has a Z ordinate
     * @param bool                  $hasM                whether the multi-point has an M ordinate
     */
    #[DataProvider('multiPointWkts')]
    public function testDecodeSupportsMultiPointRepresentations(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $multiPoint = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiPointInterface::class, $multiPoint);
        self::assertSame($expectedCoordinates, $multiPoint->toArray());
        self::assertSame($hasZ, $multiPoint->hasZ());
        self::assertSame($hasM, $multiPoint->hasM());
    }

    /**
     * Test recursively decoding heterogeneous collections with one consistent dimension.
     *
     * @param string $wkt  WKT representation of the geometry collection
     * @param bool   $hasZ whether the collection has a Z ordinate
     * @param bool   $hasM whether the collection has an M ordinate
     */
    #[DataProvider('geometryCollectionWkts')]
    public function testDecodeSupportsNestedHeterogeneousGeometryCollections(string $wkt, bool $hasZ, bool $hasM): void
    {
        $collection = $this->decodeCollection($wkt);

        self::assertSame(GeometryTypeEnum::GEOMETRYCOLLECTION, $collection->getType());
        self::assertSame($hasZ, $collection->hasZ());
        self::assertSame($hasM, $collection->hasM());
        self::assertNotEmpty($collection->getElements());
    }

    /**
     * Decode a geometry collection and expose its collection-specific API to the test.
     *
     * @param string $wkt WKT representation of the geometry collection
     */
    private function decodeCollection(string $wkt): CollectionInterface
    {
        $collection = (new WktDecoderStrategy())->decode($wkt);
        if (!$collection instanceof CollectionInterface) {
            throw new LogicException('The decoded value must be a geometry collection.');
        }

        return $collection;
    }
}
