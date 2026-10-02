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

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy
 */
class EwktDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function geometries(): iterable
    {
        yield 'XY' => ['POINT (1 2)'];
        yield 'XYZ' => ['POINT Z (1 2 3)'];
        yield 'XYM' => ['POINT M (1 2 3)'];
        yield 'XYZM' => ['POINT ZM (1 2 3 4)'];
        yield 'empty point' => ['POINT Z EMPTY'];
        yield 'line string' => ['LINESTRING (1 2,3 4)'];
        yield 'polygon' => ['POLYGON ((0 0,4 0,4 4,0 0))'];
        yield 'multi point' => ['MULTIPOINT ((1 2),(3 4))'];
        yield 'multi line string' => ['MULTILINESTRING ((1 2,3 4))'];
        yield 'multi polygon' => ['MULTIPOLYGON (((0 0,4 0,4 4,0 0)))'];
        yield 'empty collection' => ['GEOMETRYCOLLECTION ZM EMPTY'];
        yield 'nested collection' => ['GEOMETRYCOLLECTION (POINT EMPTY,GEOMETRYCOLLECTION (POINT (1 2),LINESTRING (1 2,3 4)))'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'missing equals' => ['SRID 4326;POINT (1 2)'];
        yield 'missing value' => ['SRID=;POINT (1 2)'];
        yield 'missing semicolon' => ['SRID=4326 POINT (1 2)'];
        yield 'missing geometry' => ['SRID=4326;'];
        yield 'negative' => ['SRID=-1;POINT (1 2)'];
        yield 'plus sign' => ['SRID=+4326;POINT (1 2)'];
        yield 'fraction' => ['SRID=4326.0;POINT (1 2)'];
        yield 'exponent' => ['SRID=4e3;POINT (1 2)'];
        yield 'word' => ['SRID=EPSG;POINT (1 2)'];
        yield 'overflow' => ['SRID='.\PHP_INT_MAX.'0;POINT (1 2)'];
        yield 'repeated prefix' => ['SRID=4326;SRID=3857;POINT (1 2)'];
        yield 'nested prefix' => ['SRID=4326;GEOMETRYCOLLECTION (SRID=4326;POINT (1 2))'];
        yield 'nested prefix without root' => ['GEOMETRYCOLLECTION (SRID=4326;POINT (1 2))'];
        yield 'deeply nested prefix' => ['SRID=4326;GEOMETRYCOLLECTION (GEOMETRYCOLLECTION (SRID=3857;POINT (1 2)))'];
        yield 'multi point prefix' => ['SRID=4326;MULTIPOINT (SRID=4326;(1 2))'];
        yield 'trailing prefix' => ['POINT (1 2);SRID=4326'];
        yield 'invalid geometry' => ['SRID=4326;POINT (1)'];
        yield 'trailing data' => ['SRID=4326;POINT (1 2) garbage'];
        yield 'empty input' => [''];
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function spatialReferences(): iterable
    {
        yield 'explicit zero' => ['SRID=0;', 0];
        yield 'leading zeroes' => ['SRID=004326;', 4326];
        yield 'maximum integer' => ['SRID='.\PHP_INT_MAX.';', \PHP_INT_MAX];
        yield 'whitespace and case' => [" srid = 3857 ;\n", 3857];
    }

    /**
     * Missing prefixes preserve the equivalent WKT result and default SRID.
     *
     * @param string $wkt geometry text
     */
    #[DataProvider('geometries')]
    public function testDecodeWithoutSpatialReference(string $wkt): void
    {
        $actual = (new EwktDecoderStrategy())->decode($wkt);

        self::assertEquals((new WktDecoderStrategy())->decode($wkt), $actual);
        $this->assertSpatialReference($actual, 0);
    }

    /**
     * Preserve WKT geometry behavior and assign the root SRID recursively.
     *
     * @param string $wkt geometry text
     */
    #[DataProvider('geometries')]
    public function testDecodeWithSpatialReference(string $wkt): void
    {
        $expected = (new WktDecoderStrategy())->decode($wkt)->withSrid(4326);
        $actual = (new Decoder(new EwktDecoderStrategy()))->decode('SRID=4326;'.$wkt);

        self::assertEquals($expected, $actual);
        $this->assertSpatialReference($actual, 4326);
    }

    /**
     * Reject malformed prefixes, geometry syntax and misplaced references.
     *
     * @param string $input invalid EWKT input
     */
    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EwktDecoderStrategy())->decode($input);
    }

    /**
     * Preserve supported decimal SRID values without truncation.
     *
     * @param string $prefix input prefix
     * @param int    $srid   expected spatial reference
     */
    #[DataProvider('spatialReferences')]
    public function testSpatialReferenceValues(string $prefix, int $srid): void
    {
        $actual = (new EwktDecoderStrategy())->decode($prefix.'POINT (1 2)');

        self::assertSame($srid, $actual->getSrid());
        self::assertSame([1, 2], $actual->toArray());
    }

    /** Ensure that reusing a strategy does not retain a previous reference. */
    public function testStrategyDoesNotRetainSpatialReference(): void
    {
        $strategy = new EwktDecoderStrategy();
        self::assertSame(4326, $strategy->decode('SRID=4326;POINT EMPTY')->getSrid());
        self::assertSame(0, $strategy->decode('POINT EMPTY')->getSrid());
    }

    /**
     * Check the reference on a geometry and all its descendants.
     *
     * @param SpatialInterface $geometry geometry to check
     * @param int              $srid     expected spatial reference
     */
    private function assertSpatialReference(SpatialInterface $geometry, int $srid): void
    {
        self::assertSame($srid, $geometry->getSrid());
        if ($geometry instanceof CollectionInterface) {
            foreach ($geometry->getElements() as $element) {
                $this->assertSpatialReference($element, $srid);
            }
        }
    }
}
