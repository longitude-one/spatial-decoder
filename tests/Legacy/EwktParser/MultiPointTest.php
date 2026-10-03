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

class MultiPointTest extends SpecificTestCase
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
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][], 3: ?string}, null, void>
     */
    public static function multiPointProvider(): \Generator
    {
        yield 'testParsingMultiPointValue' => ['MULTIPOINT(0 0,10 0,10 10,0 10)', null, [[0, 0], [10, 0], [10, 10], [0, 10]], null];
        yield 'testParsingMultiPointZValue' => ['MULTIPOINTZ(0 0 0,10 0 0,10 10 0,0 10 0)', null, [[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], 'Z'];
        yield 'testParsingMultiPointMValue' => ['MULTIPOINTM(0 0 0,10 0 0,10 10 0,0 10 0)', null, [[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], 'M'];
        yield 'testParsingMultiPointZMValue' => ['MULTIPOINTZM(0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1)', null, [[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1]], 'ZM'];
        yield 'testParsingMultiPointValueWithSrid' => ['SRID=4326;MULTIPOINT(0 0,10 0,10 10,0 10)', 4326, [[0, 0], [10, 0], [10, 10], [0, 10]], null];
        yield 'testParsingMultiPointZValueWithSrid' => ['SRID=4326;MULTIPOINTZ(0 0 0,10 0 0,10 10 0,0 10 0)', 4326, [[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], 'Z'];
        yield 'testParsingMultiPointMValueWithSrid' => ['SRID=4326;MULTIPOINTM(0 0 0,10 0 0,10 10 0,0 10 0)', 4326, [[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], 'M'];
        yield 'testParsingMultiPointZMValueWithSrid' => ['SRID=4326;MULTIPOINTZM(0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1)', 4326, [[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1]], 'ZM'];
    }

    /**
     * Verify that MULTIPOINT values decode to the expected points, SRID, and dimension.
     *
     * @param string           $value       EWKT value to decode
     * @param int|null         $srid        Expected spatial reference identifier
     * @param (int|string)[][] $coordinates Expected point coordinates
     * @param string|null      $dimension   Expected coordinate dimension
     */
    #[DataProvider('multiPointProvider')]
    public function testMultiPoint(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        $actual = $this->parser->decode($value);

        self::assertMultiPointParsed($srid, $coordinates, $dimension, $actual);
    }
}
