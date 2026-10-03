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

class PointTest extends SpecificTestCase
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
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[], 3: ?string}, null, void>
     */
    public static function pointProvider(): \Generator
    {
        yield 'testParsingPointValue' => ['POINT(34.23 -87)', null, ['34.23', -87], null];
        yield 'testParsingPointZValue' => ['POINT(34.23 -87 10)', null, ['34.23', -87, 10], 'Z'];
        yield 'testParsingPointDeclaredZValue' => ['POINTZ(34.23 -87 10)', null, ['34.23', -87, 10], 'Z'];
        yield 'testParsingPointMValue' => ['POINTM(34.23 -87 10)', null, ['34.23', -87, 10], 'M'];
        yield 'testParsingPointZMValue' => ['POINT(34.23 -87 10 30)', null, ['34.23', -87, 10, 30], 'ZM'];
        yield 'testParsingPointDeclaredZMValue' => ['POINT ZM(34.23 -87 10 30)', null, ['34.23', -87, 10, 30], 'ZM'];
        yield 'testParsingPointValueWithSrid' => ['SRID=4326;POINT(34.23 -87)', 4326, ['34.23', -87], null];
        yield 'testParsingPointZValueWithSrid' => ['SRID=4326;POINT(34.23 -87 10)', 4326, ['34.23', -87, 10], 'Z'];
        yield 'testParsingPointValueScientificWithSrid' => ['SRID=4326;POINT(4.23e-005 -8E-003)', 4326, ['4.23E-5', '-0.008'], null];
        yield 'testParsingPointValueScientific' => ['POINT(4.23e-005 -8E-003)', null, ['4.23E-5', '-0.008'], null];
        yield 'testParsingPointMValueWithSrid' => ['SRID=4326;POINTM(34.23 -87 10)', 4326, ['34.23', -87, 10], 'M'];
        yield 'testParsingPointZMValueWithSrid' => ['SRID=4326;POINT(34.23 -87 10 30)', 4326, ['34.23', -87, 10, 30], 'ZM'];
    }

    /**
     * @param (int|string)[] $coordinates
     */
    #[DataProvider('pointProvider')]
    public function testPoint(string $value, ?int $srid, array $coordinates, ?string $dimension): void
    {
        /** @var array{type:string, value: (int|string)[], srid: ?int, dimension: ?string} $actual */
        $actual = $this->parser->decode($value);

        self::assertPointParsed($srid, $coordinates, $dimension, $actual);
    }
}
