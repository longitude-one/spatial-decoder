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

class MultiLineStringTest extends SpecificTestCase
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
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][][], 3: ?string}, null, void>
     */
    public static function multiLineStringProvider(): \Generator
    {
        yield 'testParsingMultiLineStringValue' => ['MULTILINESTRING((0 0,10 0,10 10,0 10),(5 5,7 5,7 7,5 7))', null, [[[0, 0], [10, 0], [10, 10], [0, 10]], [[5, 5], [7, 5], [7, 7], [5, 7]]], null];
        yield 'testParsingMultiLineStringZValue' => ['MULTILINESTRINGZ((0 0 0,10 0 0,10 10 0,0 10 0),(5 5 1,7 5 1,7 7 1,5 7 1))', null, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1]]], 'Z'];
        yield 'testParsingMultiLineStringMValue' => ['MULTILINESTRINGM((0 0 0,10 0 0,10 10 0,0 10 0),(5 5 1,7 5 1,7 7 1,5 7 1))', null, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1]]], 'M'];
        yield 'testParsingMultiLineStringZMValue' => ['MULTILINESTRINGZM((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1),(5 5 1 2,7 5 1 2,7 7 1 2,5 7 1 2))', null, [[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1]], [[5, 5, 1, 2], [7, 5, 1, 2], [7, 7, 1, 2], [5, 7, 1, 2]]], 'ZM'];
        yield 'testParsingMultiLineStringValueWithSrid' => ['SRID=4326;MULTILINESTRING((0 0,10 0,10 10,0 10),(5 5,7 5,7 7,5 7))', 4326, [[[0, 0], [10, 0], [10, 10], [0, 10]], [[5, 5], [7, 5], [7, 7], [5, 7]]], null];
        yield 'testParsingMultiLineStringZValueWithSrid' => ['SRID=4326;MULTILINESTRINGZ((0 0 0,10 0 0,10 10 0,0 10 0),(5 5 1,7 5 1,7 7 1,5 7 1))', 4326, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1]]], 'Z'];
        yield 'testParsingMultiLineStringMValueWithSrid' => ['SRID=4326;MULTILINESTRINGM((0 0 0,10 0 0,10 10 0,0 10 0),(5 5 1,7 5 1,7 7 1,5 7 1))', 4326, [[[0, 0, 0], [10, 0, 0], [10, 10, 0], [0, 10, 0]], [[5, 5, 1], [7, 5, 1], [7, 7, 1], [5, 7, 1]]], 'M'];
        yield 'testParsingMultiLineStringZMValueWithSrid' => ['SRID=4326;MULTILINESTRINGZM((0 0 0 1,10 0 0 1,10 10 0 1,0 10 0 1),(5 5 1 2,7 5 1 2,7 7 1 2,5 7 1 2))', 4326, [[[0, 0, 0, 1], [10, 0, 0, 1], [10, 10, 0, 1], [0, 10, 0, 1]], [[5, 5, 1, 2], [7, 5, 1, 2], [7, 7, 1, 2], [5, 7, 1, 2]]], 'ZM'];
    }

    /**
     * @param (int|string)[][][] $coordinates
     */
    #[DataProvider('multiLineStringProvider')]
    public function testMultiLineString(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        /** @var array{type:string, value: (int|string)[][][], srid: ?int, dimension: ?string} $actual */
        $actual = $this->parser->decode($value);

        self::assertMultiLineStringParsed($srid, $coordinates, $dimension, $actual);
    }
}
