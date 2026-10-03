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

namespace LongitudeOne\SpatialDecoder\Tests\Legacy\EwktParser;

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Tests\Legacy\EwktParser\Utils\SpecificTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PolygonTest extends SpecificTestCase
{
    private Decoder $parser;

    /** Prepare the decoder before each test. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Decoder(new EwktDecoderStrategy());
    }

    /** Release the decoder after each test. */
    protected function tearDown(): void
    {
        unset($this->parser);
        parent::tearDown();
    }

    /**
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][][], 3: ?string}, null, void>
     */
    public static function polygonProvider(): \Generator
    {
        yield 'testParsingPolygonValue' => ['POLYGON((0 0,10 0,10 10,0 10,0 0))', null, [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]], null];
        yield 'testParsingPolygonZValue' => ['POLYGON((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0))', null, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]]], 'Z'];
        yield 'testParsingPolygonMValue' => ['POLYGONM((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0))', null, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]]], 'M'];
        yield 'testParsingPolygonZMValue' => ['POLYGONZM((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1,0 0 0 1))', null, [[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1], [0, 0, 0, 1]]], 'ZM'];
        yield 'testParsingPolygonValueWithSrid' => ['SRID=4326;POLYGON((0 0,10 0,10 10,0 10,0 0))', 4326, [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]], null];
        yield 'testParsingPolygonValueMultiRing' => ['POLYGON((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5))', null, [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[5, 5], [7, 5], [7, 7], [5, 7], [5, 5]]], null];
        yield 'testParsingPolygonValueMultiRingWithSrid' => ['SRID=4326;POLYGON((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5))', 4326, [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[5, 5], [7, 5], [7, 7], [5, 7], [5, 5]]], null];
        yield 'testParsingPolygonZValueWithSrid' => ['SRID=4326;POLYGON((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0))', 4326, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]]], 'Z'];
        yield 'testParsingPolygonMValueWithSrid' => ['SRID=4326;POLYGONM((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0))', 4326, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]]], 'M'];
        yield 'testParsingPolygonZMValueWithSrid' => ['SRID=4326;POLYGONZM((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1,0 0 0 1))', 4326, [[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1], [0, 0, 0, 1]]], 'ZM'];
    }

    /**
     * Verify that POLYGON values decode with the expected rings, SRID, and dimension.
     *
     * @param string             $value       EWKT value to decode
     * @param int|null           $srid        Expected spatial reference identifier
     * @param (int|string)[][][] $coordinates Expected polygon coordinates
     * @param string|null        $dimension   Expected coordinate dimension
     */
    #[DataProvider('polygonProvider')]
    public function testPolygon(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        $actual = $this->parser->decode($value);

        self::assertPolygonParsed($srid, $coordinates, $dimension, $actual);
    }
}
