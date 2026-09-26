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
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktParser
 */
class WktDecoderStrategyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int|float>, bool, bool}>
     */
    public static function coordinateDimensionWkts(): iterable
    {
        yield 'XY' => ['POINT (1 2)', [1, 2], false, false];
        yield 'XYZ' => ['POINT Z (1 -2 3)', [1, -2, 3], true, false];
        yield 'XYM' => ['POINT M (1 2 3.1)', [1, 2, 3.1], false, true];
        yield 'XYZM' => ['POINT ZM (1 2 3 4.0)', [1, 2, 3, 4.0], true, true];
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function emptyPointWkts(): iterable
    {
        yield 'XY' => ['POINT EMPTY', false, false];
        yield 'XYZ' => ['POINT Z EMPTY', true, false];
        yield 'XYM' => ['POINT M EMPTY', false, true];
        yield 'XYZM' => ['POINT ZM EMPTY', true, true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAndUnsupportedWkts(): iterable
    {
        yield 'empty input' => [''];
        yield 'missing ordinate' => ['POINT (1)'];
        yield 'dimension mismatch' => ['POINT Z (1 2)'];
        yield 'too many ordinates' => ['POINT (1 2 3 4 5)'];
        yield 'comma between point ordinates' => ['POINT (1, 2)'];
        yield 'unseparated ordinates' => ['POINT (1-2)'];
        yield 'trailing input' => ['POINT (1 2) trailing'];
        yield 'unsupported geometry type' => ['LINESTRING EMPTY'];
        yield 'non-finite ordinate' => ['POINT (1e309 2)'];
        yield 'unknown word' => ['POINT EMP'];
        yield 'unknown geometry word' => ['foo'];
    }

    /**
     * Test preservation of coordinate dimensions on empty points.
     *
     * @param string $wkt  WKT representation of an empty point
     * @param bool   $hasZ whether the point has a Z ordinate
     * @param bool   $hasM whether the point has an M ordinate
     */
    #[DataProvider('emptyPointWkts')]
    public function testDecodePreservesEmptyPointDimension(string $wkt, bool $hasZ, bool $hasM): void
    {
        $point = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(PointInterface::class, $point);
        self::assertTrue($point->isEmpty());
        self::assertSame($hasZ, $point->hasZ());
        self::assertSame($hasM, $point->hasM());

        self::assertNull($point->getX());
        self::assertNull($point->getY());
        if ($hasZ) {
            self::assertNull($point->getZ());
        }
        if ($hasM) {
            self::assertNull($point->getM());
        }
    }

    /**
     * Test rejection of malformed or unsupported WKT representations.
     *
     * @param string $wkt malformed or unsupported WKT input
     */
    #[DataProvider('malformedAndUnsupportedWkts')]
    public function testDecodeRejectsMalformedAndUnsupportedRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }

    /**
     * Test decoding of a point in the supplied coordinate dimension.
     *
     * @param string          $wkt                 WKT representation of the point
     * @param array<int, int> $expectedCoordinates expected ordered ordinates
     * @param bool            $hasZ                whether the point has a Z ordinate
     * @param bool            $hasM                whether the point has an M ordinate
     */
    #[DataProvider('coordinateDimensionWkts')]
    public function testDecodeSupportsEveryCoordinateDimension(string $wkt, array $expectedCoordinates, bool $hasZ, bool $hasM): void
    {
        $point = (new WktDecoderStrategy())->decode($wkt);

        self::assertInstanceOf(PointInterface::class, $point);
        self::assertSame($expectedCoordinates, $point->toArray());
        self::assertSame($hasZ, $point->hasZ());
        self::assertSame($hasM, $point->hasM());
    }
}
