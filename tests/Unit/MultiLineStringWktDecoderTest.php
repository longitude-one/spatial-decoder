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
use LongitudeOne\SpatialTypes\Interfaces\MultiLineStringInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\MultiLineStringWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiLineStringFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPointFactory
 */
class MultiLineStringWktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyMultiLineStringWkts(): iterable
    {
        yield 'XY' => ['MULTILINESTRING EMPTY', false, false];
        yield 'XYZ' => ['MULTILINESTRING Z EMPTY', true, false];
        yield 'XYM' => ['MULTILINESTRING M EMPTY', false, true];
        yield 'XYZM' => ['MULTILINESTRING ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedMultiLineStringWkts(): iterable
    {
        yield 'empty parentheses' => ['MULTILINESTRING ()'];
        yield 'inconsistent dimensions' => ['MULTILINESTRING ((1 2, 3 4), (5 6 7, 8 9 10))'];
        yield 'unmarked XYZ' => ['MULTILINESTRING ((1 2 3, 4 5 6))'];
        yield 'unmarked XYZM' => ['MULTILINESTRING ((1 2 3 4, 5 6 7 8))'];
        yield 'singleton line member' => ['MULTILINESTRING ((1 2))'];
        yield 'missing member parentheses' => ['MULTILINESTRING (1 2, 3 4)'];
        yield 'trailing member comma' => ['MULTILINESTRING ((1 2, 3 4),)'];
    }

    /**
     * @return iterable<string, array{string, list<list<list<int|float>>>, bool, bool}>
     */
    public static function multiLineStringWkts(): iterable
    {
        yield 'XY' => ['MULTILINESTRING ((1 2, 3 4), (5 6, 7 8))', [[[1, 2], [3, 4]], [[5, 6], [7, 8]]], false, false];
        yield 'XYZ' => ['MULTILINESTRING Z ((1 2 3, 4 5 6), (7 8 9, 10 11 12))', [[[1, 2, 3], [4, 5, 6]], [[7, 8, 9], [10, 11, 12]]], true, false];
        yield 'XYM' => ['MULTILINESTRING M ((1 2 3, 4 5 6), (7 8 9, 10 11 12))', [[[1, 2, 3], [4, 5, 6]], [[7, 8, 9], [10, 11, 12]]], false, true];
        yield 'XYZM' => ['MULTILINESTRING ZM ((1 2 3 4, 5 6 7 8))', [[[1, 2, 3, 4], [5, 6, 7, 8]]], true, true];
    }

    /**
     * Test preservation of coordinate dimensions on empty multi-line strings.
     *
     * @param string $wkt  WKT representation of an empty multi-line string
     * @param bool   $hasZ whether the multi-line string has a Z ordinate
     * @param bool   $hasM whether the multi-line string has an M ordinate
     */
    #[DataProvider('emptyMultiLineStringWkts')]
    public function testDecodePreservesEmptyMultiLineStringDimension(string $wkt, bool $hasZ, bool $hasM): void
    {
        $multiLineString = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiLineStringInterface::class, $multiLineString);
        self::assertTrue($multiLineString->isEmpty());
        self::assertSame($hasZ, $multiLineString->hasZ());
        self::assertSame($hasM, $multiLineString->hasM());
    }

    /** Test preservation of empty line members in a dimensioned multi-line string. */
    public function testDecodePreservesEmptyMultiLineStringMembers(): void
    {
        $multiLineString = (new WktDecoderStrategy())->decode('MULTILINESTRING Z (EMPTY, (1 2 3, 4 5 6))');

        self::assertInstanceOf(MultiLineStringInterface::class, $multiLineString);
        self::assertSame([[], [[1, 2, 3], [4, 5, 6]]], $multiLineString->toArray());
        self::assertTrue($multiLineString->getLineStrings()[0]->isEmpty());
        self::assertTrue($multiLineString->getLineStrings()[0]->hasZ());
    }

    /**
     * Test rejection of malformed multi-line-string representations.
     *
     * @param string $wkt malformed WKT input
     */
    #[DataProvider('malformedMultiLineStringWkts')]
    public function testDecodeRejectsMalformedMultiLineStringRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding of a multi-line string in the supplied coordinate dimension.
     *
     * @param string                      $wkt                 WKT representation of the multi-line string
     * @param list<list<list<int|float>>> $expectedCoordinates expected ordered coordinates
     * @param bool                        $hasZ                whether the multi-line string has a Z ordinate
     * @param bool                        $hasM                whether the multi-line string has an M ordinate
     */
    #[DataProvider('multiLineStringWkts')]
    public function testDecodeSupportsEveryMultiLineStringDimension(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $multiLineString = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(MultiLineStringInterface::class, $multiLineString);
        self::assertSame($expectedCoordinates, $multiLineString->toArray());
        self::assertSame($hasZ, $multiLineString->hasZ());
        self::assertSame($hasM, $multiLineString->hasM());
    }
}
