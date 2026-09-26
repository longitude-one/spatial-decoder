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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\LineStringWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPointFactory
 */
class LineStringWktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyLineStringWkts(): iterable
    {
        yield 'XY' => ['LINESTRING EMPTY', false, false];
        yield 'XYZ' => ['LINESTRING Z EMPTY', true, false];
        yield 'XYM' => ['LINESTRING M EMPTY', false, true];
        yield 'XYZM' => ['LINESTRING ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string, list<list<int|float>>, bool, bool}>
     */
    public static function lineStringWkts(): iterable
    {
        yield 'XY' => ['LINESTRING (1 2, 3.5 4)', [[1, 2], [3.5, 4]], false, false];
        yield 'XYZ' => ['LINESTRING Z (1 2 3, 4 5 6)', [[1, 2, 3], [4, 5, 6]], true, false];
        yield 'XYM' => ['LINESTRING M (1 2 3, 4 5 6)', [[1, 2, 3], [4, 5, 6]], false, true];
        yield 'XYZM' => ['LINESTRING ZM (1 2 3 4, 5 6 7 8)', [[1, 2, 3, 4], [5, 6, 7, 8]], true, true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedLineStringWkts(): iterable
    {
        yield 'singleton line string' => ['LINESTRING (1 2)'];
        yield 'inconsistent coordinates' => ['LINESTRING (1 2, 3 4 5)'];
        yield 'unmarked XYZ' => ['LINESTRING (1 2 3, 4 5 6)'];
        yield 'unmarked XYZM' => ['LINESTRING (1 2 3 4, 5 6 7 8)'];
        yield 'empty parentheses' => ['LINESTRING ()'];
    }

    /**
     * Test preservation of coordinate dimensions on empty line strings.
     *
     * @param string $wkt  WKT representation of an empty line string
     * @param bool   $hasZ whether the line string has a Z ordinate
     * @param bool   $hasM whether the line string has an M ordinate
     */
    #[DataProvider('emptyLineStringWkts')]
    public function testDecodePreservesEmptyLineStringDimension(string $wkt, bool $hasZ, bool $hasM): void
    {
        $lineString = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(LineStringInterface::class, $lineString);
        self::assertTrue($lineString->isEmpty());
        self::assertSame($hasZ, $lineString->hasZ());
        self::assertSame($hasM, $lineString->hasM());
    }

    /**
     * Test rejection of malformed line-string representations.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedLineStringWkts')]
    public function testDecodeRejectsMalformedLineStringRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding of a line string in the supplied coordinate dimension.
     *
     * @param string                $wkt                 WKT representation of the line string
     * @param list<list<int|float>> $expectedCoordinates expected ordered coordinates
     * @param bool                  $hasZ                whether the line string has a Z ordinate
     * @param bool                  $hasM                whether the line string has an M ordinate
     */
    #[DataProvider('lineStringWkts')]
    public function testDecodeSupportsEveryLineStringDimension(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $lineString = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(LineStringInterface::class, $lineString);
        self::assertSame($expectedCoordinates, $lineString->toArray());
        self::assertSame($hasZ, $lineString->hasZ());
        self::assertSame($hasM, $lineString->hasM());
    }
}
