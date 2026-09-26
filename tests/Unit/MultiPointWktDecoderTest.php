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
use LongitudeOne\SpatialTypes\Interfaces\MultiPointInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\MultiPointWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiPointFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class MultiPointWktDecoderTest extends TestCase
{
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
}
