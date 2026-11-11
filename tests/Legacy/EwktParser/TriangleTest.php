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

class TriangleTest extends SpecificTestCase
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

    /** @return iterable<string, array{string, list<list<int|float>>}> */
    public static function geometricallyInvalidTriangles(): iterable
    {
        yield 'testParsingTriangleWith1Point' => ['TRIANGLE((34.23 -87))', [[34.23, -87]]];
        yield 'testParsingTriangleZWith1Point' => ['TRIANGLEZ((34.23 -87 33))', [[34.23, -87, 33]]];
        yield 'testParsingTriangleMWith1Point' => ['TRIANGLEM((34.23 -87 10))', [[34.23, -87, 10]]];
        yield 'testParsingTriangleZMWith1Point' => ['TRIANGLEZM((34.23 -87 10 20))', [[34.23, -87, 10, 20]]];
        yield 'testParsingTriangleWith2Points' => ['TRIANGLE((34.23 -87, 45.3 -92))', [[34.23, -87], [45.3, -92]]];
        yield 'testParsingTriangleZWith2Points' => ['TRIANGLEZ((34.23 -87 33, 45.3 -92 22))', [[34.23, -87, 33], [45.3, -92, 22]]];
        yield 'testParsingTriangleMWith2Points' => ['TRIANGLEM((34.23 -87 10, 45.3 -92 10))', [[34.23, -87, 10], [45.3, -92, 10]]];
        yield 'testParsingTriangleZMWith2Points' => ['TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20))', [[34.23, -87, 10, 20], [45.3, -92, 10, 20]]];
        yield 'testParsingTriangleWith3Points' => ['TRIANGLE((34.23 -87, 45.3 -92, 10 10))', [[34.23, -87], [45.3, -92], [10, 10]]];
        yield 'testParsingTriangleZWith3Points' => ['TRIANGLEZ((34.23 -87 33, 45.3 -92 22, 10 10 11))', [[34.23, -87, 33], [45.3, -92, 22], [10, 10, 11]]];
        yield 'testParsingTriangleMWith3Points' => ['TRIANGLEM((34.23 -87 10, 45.3 -92 10, 10 10 11))', [[34.23, -87, 10], [45.3, -92, 10], [10, 10, 11]]];
        yield 'testParsingTriangleZMWith3Points' => ['TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20, 10 11 12 13))', [[34.23, -87, 10, 20], [45.3, -92, 10, 20], [10, 11, 12, 13]]];
        yield 'testParsingTriangleWith5Points' => ['TRIANGLE((34.23 -87, 45.3 -92, 10 10, 34.23 -87, 45.3 -92))', [[34.23, -87], [45.3, -92], [10, 10], [34.23, -87], [45.3, -92]]];
        yield 'testParsingTriangleZWith5Points' => ['TRIANGLEZ((34.23 -87 33, 45.3 -92 22, 10 10 11, 34.23 -87 33, 45.3 -92 22))', [[34.23, -87, 33], [45.3, -92, 22], [10, 10, 11], [34.23, -87, 33], [45.3, -92, 22]]];
        yield 'testParsingTriangleMWith5Points' => ['TRIANGLEM((34.23 -87 10, 45.3 -92 10, 10 10 11, 34.23 -87 10, 45.3 -92 10))', [[34.23, -87, 10], [45.3, -92, 10], [10, 10, 11], [34.23, -87, 10], [45.3, -92, 10]]];
        yield 'testParsingTriangleZMWith5Points' => ['TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20, 10 11 12 13, 34.23 -87 10 20, 45.3 -92 10 20))', [[34.23, -87, 10, 20], [45.3, -92, 10, 20], [10, 11, 12, 13], [34.23, -87, 10, 20], [45.3, -92, 10, 20]]];
        yield 'testParsingTriangleWithNotClosedRing' => ['TRIANGLE((34.23 -87, 45.3 -92, 10 10, 0 0))', [[34.23, -87], [45.3, -92], [10, 10], [0, 0]]];
        yield 'testParsingTriangleZWithNotClosedRing' => ['TRIANGLEZ((34.23 -87 33, 45.3 -92 22, 10 10 11, 0 0 0))', [[34.23, -87, 33], [45.3, -92, 22], [10, 10, 11], [0, 0, 0]]];
        yield 'testParsingTriangleMWithNotClosedRing' => ['TRIANGLEM((34.23 -87 10, 45.3 -92 10, 10 10 11, 0 0 0))', [[34.23, -87, 10], [45.3, -92, 10], [10, 10, 11], [0, 0, 0]]];
        yield 'testParsingTriangleZMWithNotClosedRing' => ['TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20, 10 11 12 13, 0 0 0 0))', [[34.23, -87, 10, 20], [45.3, -92, 10, 20], [10, 11, 12, 13], [0, 0, 0, 0]]];
    }

    /**
     * @return \Generator<string, array{0: string, 1: ?int, 2: (int|string)[][], 3: ?string}, null, void>
     */
    public static function triangleProvider(): \Generator
    {
        yield 'testParsingTriangleValue' => ['TRIANGLE((34.23 -87, 45.3 -92, 10 10, 34.23 -87))', null, [['34.23', -87], ['45.3', -92], [10, 10], ['34.23', -87]], null];
        yield 'testParsingTriangleZValue' => ['TRIANGLEZ((34.23 -87 33, 45.3 -92 22, 10 10 11, 34.23 -87 33))', null, [['34.23', -87, 33], ['45.3', -92, 22], [10, 10, 11], ['34.23', -87, 33]], 'Z'];
        yield 'testParsingTriangleMValue' => ['TRIANGLEM((34.23 -87 10, 45.3 -92 10, 10 10 11, 34.23 -87 10))', null, [['34.23', -87, 10], ['45.3', -92, 10], [10, 10, 11], ['34.23', -87, 10]], 'M'];
        yield 'testParsingTriangleZMValue' => ['TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20, 10 11 12 13, 34.23 -87 10 20))', null, [['34.23', -87, 10, 20], ['45.3', -92, 10, 20], [10, 11, 12, 13], ['34.23', -87, 10, 20]], 'ZM'];
        yield 'testParsingTriangleValueWithSrid' => ['SRID=4326;TRIANGLE((34.23 -87, 45.3 -92, 10 10, 34.23 -87))', 4326, [['34.23', -87], ['45.3', -92], [10, 10], ['34.23', -87]], null];
        yield 'testParsingTriangleZValueWithSrid' => ['SRID=4326;TRIANGLE((34.23 -87 33, 45.3 -92 22, 10 10 11, 34.23 -87 33))', 4326, [['34.23', -87, 33], ['45.3', -92, 22], [10, 10, 11], ['34.23', -87, 33]], 'Z'];
        yield 'testParsingTriangleMValueWithSrid' => ['SRID=4326;TRIANGLEM((34.23 -87 10, 45.3 -92 10, 10 10 11, 34.23 -87 10))', 4326, [['34.23', -87, 10], ['45.3', -92, 10], [10, 10, 11], ['34.23', -87, 10]], 'M'];
        yield 'testParsingTriangleZMValueWithSrid' => ['SRID=4326;TRIANGLEZM((34.23 -87 10 20, 45.3 -92 10 20, 10 11 12 13, 34.23 -87 10 20))', 4326, [['34.23', -87, 10, 20], ['45.3', -92, 10, 20], [10, 11, 12, 13], ['34.23', -87, 10, 20]], 'ZM'];
    }

    /**
     * Preserve geometric errors for explicit validation by the caller.
     *
     * @param string                $input       EWKT triangle to decode
     * @param list<list<int|float>> $coordinates original ring coordinates
     */
    #[DataProvider('geometricallyInvalidTriangles')]
    public function testPreservesGeometricallyInvalidTriangle(string $input, array $coordinates): void
    {
        $actual = $this->parser->decode($input);
        self::assertSame('TRIANGLE', strtoupper($actual->getType()->value));
        self::assertSame([$coordinates], $actual->toArray());
    }

    /**
     * Verify that TRIANGLE values decode to the expected ring coordinates and metadata.
     *
     * @param string           $actualValue EWKT value to decode
     * @param int|null         $srid        Expected spatial reference identifier
     * @param (int|string)[][] $coordinates Expected triangle coordinates
     * @param string|null      $dimension   Expected coordinate dimension
     */
    #[DataProvider('triangleProvider')]
    public function testTriangle(string $actualValue, ?int $srid, array $coordinates, ?string $dimension): void
    {
        $actual = $this->parser->decode($actualValue);

        self::assertTriangleParsed($srid, $coordinates, $dimension, $actual);
    }
}
