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

class MultiPolygonTest extends SpecificTestCase
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
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][][][], 3: ?string}, null, void>
     */
    public static function multiPolygonProvider(): \Generator
    {
        yield 'testParsingMultiPolygonValue' => ['MULTIPOLYGON(((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5)),((1 1, 3 1, 3 3, 1 3, 1 1)))', null, [[[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[5, 5], [7, 5], [7, 7], [5, 7], [5, 5]]], [[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]], null];
        yield 'testParsingMultiPolygonZValue' => ['MULTIPOLYGON(((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0),(5 5 1,7 5 1,7 7 1,5 7 1,5 5 1)),((1 1 0, 3 1 0, 3 3 0, 1 3 0, 1 1 0)))', null, [[[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1], [5, 5, 1]]], [[[1, 1, 0], [3, 1, 0], [3, 3, 0], [1, 3, 0], [1, 1, 0]]]], 'Z'];
        yield 'testParsingMultiPolygonMValue' => ['MULTIPOLYGONM(((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0),(5 5 1,7 5 1,7 7 1,5 7 1,5 5 1)),((1 1 0, 3 1 0, 3 3 0, 1 3 0, 1 1 0)))', null, [[[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1], [5, 5, 1]]], [[[1, 1, 0], [3, 1, 0], [3, 3, 0], [1, 3, 0], [1, 1, 0]]]], 'M'];
        yield 'testParsingMultiPolygonZMValue' => ['MULTIPOLYGONZM(((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1,0 0 0 1),(5 5 1 2,7 5 1 2,7 7 1 2,5 7 1 2,5 5 1 2)),((1 1 0 3, 3 1 0 3, 3 3 0 3, 1 3 0 3, 1 1 0 3)))', null, [[[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1], [0, 0, 0, 1]], [[5, 5, 1, 2], [7, 5, 1, 2], [7, 7, 1, 2], [5, 7, 1, 2], [5, 5, 1, 2]]], [[[1, 1, 0, 3], [3, 1, 0, 3], [3, 3, 0, 3], [1, 3, 0, 3], [1, 1, 0, 3]]]], 'ZM'];
        yield 'testParsingMultiPolygonValueWithSrid' => ['SRID=4326;MULTIPOLYGON(((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5)),((1 1, 3 1, 3 3, 1 3, 1 1)))', 4326, [[[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[5, 5], [7, 5], [7, 7], [5, 7], [5, 5]]], [[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]], null];
        yield 'testParsingMultiPolygonZValueWithSrid' => ['SRID=4326;MULTIPOLYGONZ(((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0),(5 5 1,7 5 1,7 7 1,5 7 1,5 5 1)),((1 1 0, 3 1 0, 3 3 0, 1 3 0, 1 1 0)))', 4326, [[[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1], [5, 5, 1]]], [[[1, 1, 0], [3, 1, 0], [3, 3, 0], [1, 3, 0], [1, 1, 0]]]], 'Z'];
        yield 'testParsingMultiPolygonMValueWithSrid' => ['SRID=4326;MULTIPOLYGONM(((0 0 0,10 0 0,10 10 0,0 10 0,0 0 0),(5 5 1,7 5 1,7 7 1,5 7 1,5 5 1)),((1 1 0, 3 1 0, 3 3 0, 1 3 0, 1 1 0)))', 4326, [[[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0], [0, 0, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1], [5, 5, 1]]], [[[1, 1, 0], [3, 1, 0], [3, 3, 0], [1, 3, 0], [1, 1, 0]]]], 'M'];
        yield 'testParsingMultiPolygonZMValueWithSrid' => ['SRID=4326;MULTIPOLYGONZM(((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1,0 0 0 1),(5 5 1 2,7 5 1 2,7 7 1 2,5 7 1 2,5 5 1 2)),((1 1 0 3, 3 1 0 3, 3 3 0 3, 1 3 0 3, 1 1 0 3)))', 4326, [[[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1], [0, 0, 0, 1]], [[5, 5, 1, 2], [7, 5, 1, 2], [7, 7, 1, 2], [5, 7, 1, 2], [5, 5, 1, 2]]], [[[1, 1, 0, 3], [3, 1, 0, 3], [3, 3, 0, 3], [1, 3, 0, 3], [1, 1, 0, 3]]]], 'ZM'];
    }

    /**
     * Verify that MULTIPOLYGON values decode to the expected polygons and metadata.
     *
     * @param string               $value       EWKT value to decode
     * @param int|null             $srid        Expected spatial reference identifier
     * @param (int|string)[][][][] $coordinates Expected multipolygon coordinates
     * @param string|null          $dimension   Expected coordinate dimension
     */
    #[DataProvider('multiPolygonProvider')]
    public function testMultiPolygon(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        $actual = $this->parser->decode($value);

        self::assertMultiPolygonParsed($srid, $coordinates, $dimension, $actual);
    }
}
