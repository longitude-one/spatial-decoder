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

class CircularStringTest extends SpecificTestCase
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
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][], 3: ?string}, null, void>
     */
    public static function circularStringProvider(): \Generator
    {
        yield 'testCircularString' => ['CIRCULARSTRING(0 0, 1 1, 1 0)', null, [[0, 0], [1, 1], [1, 0]], null];
        yield 'testCircularStringWithFloat' => ['CIRCULARSTRING(0.0 0.0, 1.1 1.1, 1.0 0.0)', null, [['0', '0'], ['1.1', '1.1'], ['1', '0']], null];
        yield 'testCircularStringWithSrid' => ['SRID=4326;CIRCULARSTRING(0 0, 1 1, 1 0)', 4326, [[0, 0], [1, 1], [1, 0]], null];
        yield 'testCircularStringWithZ' => ['CIRCULARSTRINGZ(0 0 0, 1 1 1, 1 0 -1)', null, [[0, 0, 0], [1, 1, 1], [1, 0, -1]], 'Z'];
        yield 'testCircularStringWithZAndSrid' => ['SRID=4326;CIRCULARSTRINGZ(0 0 0, 1 1 1, 1 0 -1)', 4326, [[0, 0, 0], [1, 1, 1], [1, 0, -1]], 'Z'];
        yield 'testCircularStringWithM' => ['CIRCULARSTRINGM(0 0 0, 1 1 1, 1 0 -1)', null, [[0, 0, 0], [1, 1, 1], [1, 0, -1]], 'M'];
        yield 'testCircularStringWithMAndSrid' => ['SRID=4326;CIRCULARSTRINGM(0 0 0, 1 1 1, 1 0 -1)', 4326, [[0, 0, 0], [1, 1, 1], [1, 0, -1]], 'M'];
        yield 'testCircularStringWithZM' => ['CIRCULARSTRINGZM(0 0 0 0, 1 1 1 1, 1 0 -1 0)', null, [[0, 0, 0, 0], [1, 1, 1, 1], [1, 0, -1, 0]], 'ZM'];
        yield 'testCircularStringWithZMAndSrid' => ['SRID=4326;CIRCULARSTRINGZM(0 0 0 0, 1 1 1 1.2, 1 0 -1 0)', 4326, [[0, 0, 0, 0], [1, 1, 1, '1.2'], [1, 0, -1, 0]], 'ZM'];
    }

    /**
     * @param (int|string)[][] $coordinates
     */
    #[DataProvider('circularStringProvider')]
    public function testCircularString(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        /** @var array{type:string, value: (int|string)[][], srid: ?int, dimension: ?string} $actual */
        $actual = $this->parser->decode($value);
        self::assertCircularStringParsed($srid, $coordinates, $dimension, $actual);
    }
}
