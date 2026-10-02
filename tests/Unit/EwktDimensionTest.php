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
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
class EwktDimensionTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function ewktOnlyRepresentations(): iterable
    {
        yield 'implicit XYZ' => ['POINT (1 2 3)'];
        yield 'implicit XYZM' => ['POINT (1 2 3 4)'];
        yield 'compact XYZ' => ['POINTZ (1 2 3)'];
        yield 'compact XYM' => ['POINTM (1 2 3)'];
        yield 'compact XYZM' => ['POINTZM (1 2 3 4)'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDimensions(): iterable
    {
        yield 'missing ordinates' => ['POINT ()'];
        yield 'one ordinate' => ['POINT (1)'];
        yield 'five ordinates' => ['POINT (1 2 3 4 5)'];
        yield 'short Z' => ['POINTZ (1 2)'];
        yield 'long Z' => ['POINT Z (1 2 3 4)'];
        yield 'short M' => ['POINT M (1 2)'];
        yield 'long M' => ['POINTM (1 2 3 4)'];
        yield 'short ZM' => ['POINTZM (1 2 3)'];
        yield 'duplicate marker' => ['POINT Z Z (1 2 3)'];
        yield 'conflicting markers' => ['POINTZ M (1 2 3)'];
        yield 'mixed line' => ['LINESTRING (1 2 3,4 5)'];
        yield 'mixed points' => ['MULTIPOINT (1 2,3 4 5)'];
        yield 'mixed lines' => ['MULTILINESTRING ((1 2 3,4 5 6),(1 2,3 4))'];
        yield 'mixed polygon' => ['POLYGON ((0 0 1,4 0 2,4 4,0 0 1))'];
        yield 'conflicting children' => ['GEOMETRYCOLLECTION (POINT Z (1 2 3),POINT M (4 5 6))'];
        yield 'conflicting parent' => ['GEOMETRYCOLLECTIONM (POINTZ (1 2 3))'];
        yield 'mixed children' => ['GEOMETRYCOLLECTION (POINT (1 2 3),POINT (4 5 6 7))'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function representations(): iterable
    {
        yield 'XY' => ['POINT (1 2)', 'POINT (1 2)'];
        yield 'implicit XYZ' => ['POINT (1 2 3)', 'POINT Z (1 2 3)'];
        yield 'implicit XYZM' => ['POINT (1 2 3 4)', 'POINT ZM (1 2 3 4)'];
        yield 'compact XYZ' => ['POINTZ (1 2 3)', 'POINT Z (1 2 3)'];
        yield 'compact XYM' => ['POINTM (1 2 3)', 'POINT M (1 2 3)'];
        yield 'compact XYZM' => ['POINTZM (1 2 3 4)', 'POINT ZM (1 2 3 4)'];
        yield 'explicit XYZ' => ['POINT Z (1 2 3)', 'POINT Z (1 2 3)'];
        yield 'explicit XYM' => ['POINT M (1 2 3)', 'POINT M (1 2 3)'];
        yield 'explicit XYZM' => ['POINT ZM (1 2 3 4)', 'POINT ZM (1 2 3 4)'];
        yield 'compact line' => ['LINESTRINGZ (1 2 3,4 5 6)', 'LINESTRING Z (1 2 3,4 5 6)'];
        yield 'compact polygon' => ['POLYGONM ((0 0 1,4 0 2,4 4 3,0 0 1))', 'POLYGON M ((0 0 1,4 0 2,4 4 3,0 0 1))'];
        yield 'compact multi point' => ['MULTIPOINTZM ((1 2 3 4))', 'MULTIPOINT ZM ((1 2 3 4))'];
        yield 'compact multi line' => ['MULTILINESTRINGM ((1 2 3,4 5 6))', 'MULTILINESTRING M ((1 2 3,4 5 6))'];
        yield 'compact multi polygon' => ['MULTIPOLYGONZ (((0 0 1,4 0 2,4 4 3,0 0 1)))', 'MULTIPOLYGON Z (((0 0 1,4 0 2,4 4 3,0 0 1)))'];
        yield 'numeric syntax' => ['point(1e1 -2.5 +3)', 'POINT Z (10 -2.5 3)'];
        yield 'line' => ['LINESTRING (1 2 3,4 5 6)', 'LINESTRING Z (1 2 3,4 5 6)'];
        yield 'polygon' => ['POLYGON ((0 0 1,4 0 2,4 4 3,0 0 1))', 'POLYGON Z ((0 0 1,4 0 2,4 4 3,0 0 1))'];
        yield 'bare multi point' => ['MULTIPOINT (1 2 3 4,5 6 7 8)', 'MULTIPOINT ZM (1 2 3 4,5 6 7 8)'];
        yield 'multi point with empty' => ['MULTIPOINT (EMPTY,(1 2 3))', 'MULTIPOINT Z (EMPTY,(1 2 3))'];
        yield 'multi line' => ['MULTILINESTRING ((1 2 3,4 5 6))', 'MULTILINESTRING Z ((1 2 3,4 5 6))'];
        yield 'multi polygon' => ['MULTIPOLYGON (((0 0 1,4 0 2,4 4 3,0 0 1)))', 'MULTIPOLYGON Z (((0 0 1,4 0 2,4 4 3,0 0 1)))'];
        yield 'nested collection' => ['GEOMETRYCOLLECTION (POINT (1 2 3),GEOMETRYCOLLECTION (POINTZ (4 5 6),POINT EMPTY))', 'GEOMETRYCOLLECTION Z (POINT (1 2 3),GEOMETRYCOLLECTION (POINT (4 5 6),POINT EMPTY))'];
        yield 'inherited M' => ['GEOMETRYCOLLECTIONM (POINT (1 2 3))', 'GEOMETRYCOLLECTION M (POINT (1 2 3))'];
        yield 'unmarked empty' => ['POINT EMPTY', 'POINT EMPTY'];
        yield 'empty XYZ' => ['POINTZ EMPTY', 'POINT Z EMPTY'];
        yield 'empty XYM' => ['LINESTRINGM EMPTY', 'LINESTRING M EMPTY'];
        yield 'empty XYZM' => ['GEOMETRYCOLLECTIONZM EMPTY', 'GEOMETRYCOLLECTION ZM EMPTY'];
    }

    /**
     * @param string $ewkt extended representation
     * @param string $wkt  equivalent explicit WKT fixture
     */
    #[DataProvider('representations')]
    public function testEquivalentRepresentations(string $ewkt, string $wkt): void
    {
        $expected = (new WktDecoderStrategy())->decode($wkt);
        $strategy = new EwktDecoderStrategy();
        self::assertEquals($expected, $strategy->decode($ewkt));
        self::assertEquals($expected->withSrid(4326), $strategy->decode('SRID=4326;'.$ewkt));
    }

    /** @param string $input invalid dimensional input */
    #[DataProvider('invalidDimensions')]
    public function testInvalidDimensions(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EwktDecoderStrategy())->decode($input);
    }

    /** Explicitly distinguish the third ordinate's meaning. */
    public function testThirdOrdinateSemantics(): void
    {
        $strategy = new EwktDecoderStrategy();
        $xyz = $strategy->decode('POINT (1 2 3)');
        $xym = $strategy->decode('POINTM (1 2 3)');
        self::assertTrue($xyz->hasZ());
        self::assertFalse($xyz->hasM());
        self::assertFalse($xym->hasZ());
        self::assertTrue($xym->hasM());
        self::assertSame([1, 2, 3], $xyz->toArray());
        self::assertSame([1, 2, 3], $xym->toArray());
    }

    /** @param string $input EWKT-only dimensional syntax */
    #[DataProvider('ewktOnlyRepresentations')]
    public function testWktStillRequiresMarkers(string $input): void
    {
        (new EwktDecoderStrategy())->decode($input);
        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode($input);
    }
}
