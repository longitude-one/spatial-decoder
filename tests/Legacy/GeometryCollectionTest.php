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

namespace LongitudeOne\SpatialDecoder\Tests\Legacy;

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Tests\Legacy\Utils\SpecificTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GeometryCollectionTest extends SpecificTestCase
{
    private Decoder $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Decoder(new EwktDecoderStrategy());
    }

    protected function tearDown(): void
    {
        unset($this->parser);
        parent::tearDown();
    }

    /**
     * @return \Generator<string, array{0:string, 1:int|null, 2: array<array{'type': string, value:(int|string)[]|(int|string)[][]}>, 3: ?string}, null, void>
     */
    public static function geometryCollectionProvider(): \Generator
    {
        yield 'testGeometryCollection' => ['GEOMETRYCOLLECTION(POINT(34.23 -87), LINESTRING(34.23 -87, 45.3 -92))', null, [['type' => 'POINT', 'value' => ['34.23', -87]], ['type' => 'LINESTRING', 'value' => [['34.23', -87], ['45.3', -92]]]], null];
        yield 'testGeometryCollectionWithSrid' => ['SRID=4326;GEOMETRYCOLLECTION(POINT(34.23 -87), LINESTRING(34.23 -87, 45.3 -92))', 4326, [['type' => 'POINT', 'value' => ['34.23', -87]], ['type' => 'LINESTRING', 'value' => [['34.23', -87], ['45.3', -92]]]], null];
        yield 'testGeometryCollectionWithZ' => ['GEOMETRYCOLLECTION(POINT Z(34.23 -87 10), LINESTRING Z(34.23 -87 10, 45.3 -92 10))', null, [['type' => 'POINT', 'value' => ['34.23', -87, 10]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10], ['45.3', -92, 10]]]], 'Z'];
        yield 'testGeometryCollectionWithZAndSrid' => ['SRID=4326;GEOMETRYCOLLECTION(POINT Z(34.23 -87 10), LINESTRING Z(34.23 -87 10, 45.3 -92 10))', 4326, [['type' => 'POINT', 'value' => ['34.23', -87, 10]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10], ['45.3', -92, 10]]]], 'Z'];
        yield 'testGeometryCollectionWithM' => ['GEOMETRYCOLLECTION(POINT M(34.23 -87 10), LINESTRING M(34.23 -87 10, 45.3 -92 10))', null, [['type' => 'POINT', 'value' => ['34.23', -87, 10]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10], ['45.3', -92, 10]]]], 'M'];
        yield 'testGeometryCollectionWithMAndSrid' => ['SRID=4326;GEOMETRYCOLLECTION(POINT M(34.23 -87 10), LINESTRING M(34.23 -87 10, 45.3 -92 10))', 4326, [['type' => 'POINT', 'value' => ['34.23', -87, 10]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10], ['45.3', -92, 10]]]], 'M'];
        yield 'testGeometryCollectionWithZM' => ['GEOMETRYCOLLECTION(POINT ZM(34.23 -87 10 20), LINESTRING ZM(34.23 -87 10 20, 45.3 -92 10 20))', null, [['type' => 'POINT', 'value' => ['34.23', -87, 10, 20]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10, 20], ['45.3', -92, 10, 20]]]], 'ZM'];
        yield 'testGeometryCollectionWithZMAndSrid' => ['SRID=4326;GEOMETRYCOLLECTION(POINT ZM(34.23 -87 10 20), LINESTRING ZM(34.23 -87 10 20, 45.3 -92 10 20))', 4326, [['type' => 'POINT', 'value' => ['34.23', -87, 10, 20]], ['type' => 'LINESTRING', 'value' => [['34.23', -87, 10, 20], ['45.3', -92, 10, 20]]]], 'ZM'];
    }

    /**
     * @param array<array{'type': string, value:(int|string)[]|(int|string)[][]}> $coordinates
     */
    #[DataProvider('geometryCollectionProvider')]
    public function testGeometryCollection(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        /** @var array{type:string, value: array<array{'type': string, value:(int|string)[]|(int|string)[][]}>, srid: ?int, dimension: ?string} $actual */
        $actual = $this->parser->decode($value);

        self::assertGeometryCollectionParsed($srid, $coordinates, $dimension, $actual);
    }
}
