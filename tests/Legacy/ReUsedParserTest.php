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

class ReUsedParserTest extends SpecificTestCase
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

    public function testReusedParser(): void
    {
        foreach (PointTest::pointProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertPointParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (LineStringTest::lineStringProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[][], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertLineStringParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (PolygonTest::polygonProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[][][], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertPolygonParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (MultiPointTest::multiPointProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[][], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertMultiPointParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (MultiLineStringTest::multiLineStringProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[][][], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertMultiLineStringParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (MultiPolygonTest::multiPolygonProvider() as $name => $testData) {
            /** @var array{type:string, value: (int|string)[][][][], srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertMultiPolygonParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
        foreach (GeometryCollectionTest::geometryCollectionProvider() as $name => $testData) {
            /** @var array{type:string, value: array<array{'type': string, value:(int|string)[]|(int|string)[][]}>, srid: ?int, dimension: ?string} $actual */
            $actual = $this->parser->decode($testData[0]);
            self::assertGeometryCollectionParsed($testData[1], $testData[2], $testData[3], $actual, 'Failed dataset "'.$name.'"');
        }
    }
}
