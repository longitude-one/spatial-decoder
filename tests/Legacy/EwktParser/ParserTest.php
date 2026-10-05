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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\NonInstantiableGeometryTypeException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Tests\Legacy\EwktParser\Utils\SpecificTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Basic parser tests.
 */
class ParserTest extends SpecificTestCase
{
    /**
     * @return \Generator<string, array{0: string, 1: string}, null, void>
     */
    public static function notExistentValuesProvider(): \Generator
    {
        yield 'testParsingGarbage' => ['@#_$%', 'The supplied WKT geometry type is not supported. Invalid WKT input: "@#_$%".'];
        yield 'testParsingBadType' => ['PNT(10 10)', 'The supplied WKT geometry type is not supported. Invalid WKT input: "PNT(10 10)".'];
        yield 'testParsingGeometryCollectionValueWithBadType' => ['GEOMETRYCOLLECTION(PNT(10 10), POINT(30 30), LINESTRING(15 15, 20 20))', 'The supplied WKT geometry type is not supported. Invalid WKT input: "GEOMETRYCOLLECTION(PNT(10 10), POINT(30 30), LINESTRING(15 15, 20 20))".'];
    }

    /**
     * @return \Generator<string, array{0: string, 1:string}, null, void>
     */
    public static function notInstantiableTypesProvider(): \Generator
    {
        yield 'testNotInstantiableGeometry' => ['GEOMETRY', 'Geometry type "Geometry" is recognized but is not instantiable.'];
        yield 'testNotInstantiableCurve' => ['CURVE', 'Geometry type "Curve" is recognized but is not instantiable.'];
        yield 'testNotInstantiableSolid' => ['SOLID', 'Geometry type "Solid" is recognized but is not instantiable.'];
        yield 'testNotInstantiableSurface' => ['SURFACE', 'Geometry type "Surface" is recognized but is not instantiable.'];
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}, null, void>
     */
    public static function unexpectedValues(): \Generator
    {
        yield 'testParsingPointValueWithBadSrid' => ['SRID=432.6;POINT(34.23 -87)', 'Invalid EWKT SRID prefix; expected SRID=<integer>; before the root geometry.'];
        yield 'testParsingPointValueMissingCoordinate' => ['POINT(34.23)', 'An EWKT coordinate must contain two, three or four ordinates. Invalid WKT input: "POINT(34.23)".'];
        yield 'testParsingPointMValueMissingCoordinate' => ['POINTM(34.23 10)', 'The WKT point ordinates do not match its coordinate dimension. Invalid WKT input: "POINTM(34.23 10)".'];
        yield 'testParsingPointMValueExtraCoordinate' => ['POINTM(34.23 10 30 40)', 'The WKT point ordinates do not match its coordinate dimension. Invalid WKT input: "POINTM(34.23 10 30 40)".'];
        yield 'testParsingPointZMValueMissingCoordinate' => ['POINTZM(34.23 10 45)', 'The WKT point ordinates do not match its coordinate dimension. Invalid WKT input: "POINTZM(34.23 10 45)".'];
        yield 'testParsingPointZMValueExtraCoordinate' => ['POINTZM(34.23 10 45 4.5 99)', 'The WKT point ordinates do not match its coordinate dimension. Invalid WKT input: "POINTZM(34.23 10 45 4.5 99)".'];
        yield 'testParsingPointValueShortString' => ['POINT(34.23', 'The WKT geometry syntax is malformed. Invalid WKT input: "POINT(34.23".'];
        yield 'testParsingPointValueWrongScientificWithSrid' => ['SRID=4326;POINT(4.23test-005 -8e-003)', 'The WKT geometry syntax is malformed. Invalid WKT input: "SRID=4326;POINT(4.23test-005 -8e-003)".'];
        yield 'testParsingPointValueWithComma' => ['POINT(10, 10)', 'The WKT geometry syntax is malformed. Invalid WKT input: "POINT(10, 10)".'];
        yield 'testParsingLineStringValueMissingCoordinate' => ['LINESTRING(34.23 -87, 45.3)', 'The WKT coordinates do not match their coordinate dimension. Invalid WKT input: "LINESTRING(34.23 -87, 45.3)".'];
        yield 'testParsingLineStringValueMismatchedDimensions' => ['LINESTRING(34.23 -87, 45.3 56 23.4)', 'The WKT coordinates do not match their coordinate dimension. Invalid WKT input: "LINESTRING(34.23 -87, 45.3 56 23.4)".'];
        yield 'testParsingPolygonValueMissingParenthesis' => ['POLYGON(0 0,10 0,10 10,0 10,0 0)', 'The WKT geometry syntax is malformed. Invalid WKT input: "POLYGON(0 0,10 0,10 10,0 10,0 0)".'];
        yield 'testParsingPolygonValueMismatchedDimension' => ['POLYGON((0 0,10 0,10 10 10,0 10,0 0))', 'The WKT coordinates do not match their coordinate dimension. Invalid WKT input: "POLYGON((0 0,10 0,10 10 10,0 10,0 0))".'];
        yield 'testParsingPolygonValueMultiRingMissingComma' => ['POLYGON((0 0,10 0,10 10,0 10,0 0)(5 5,7 5,7 7,5 7,5 5))', 'The WKT geometry syntax is malformed. Invalid WKT input: "POLYGON((0 0,10 0,10 10,0 10,0 0)(5 5,7 5,7 7,5 7,5 5))".'];
        yield 'testParsingMultiLineStringValueMissingComma' => ['MULTILINESTRING((0 0,10 0,10 10,0 10)(5 5,7 5,7 7,5 7))', 'The WKT geometry syntax is malformed. Invalid WKT input: "MULTILINESTRING((0 0,10 0,10 10,0 10)(5 5,7 5,7 7,5 7))".'];
        yield 'testParsingMultiPolygonValueMissingParenthesis' => ['MULTIPOLYGON(((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5)),(1 1, 3 1, 3 3, 1 3, 1 1))', 'The WKT geometry syntax is malformed. Invalid WKT input: "MULTIPOLYGON(((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7,5 5)),(1 1, 3 1, 3 3, 1 3, 1 1))".'];
        yield 'testParsingGeometryCollectionValueWithMismatchedDimension' => ['GEOMETRYCOLLECTION(POINT(10 10), POINT(30 30 10), LINESTRING(15 15, 20 20))', 'The WKT point ordinates do not match its coordinate dimension. Invalid WKT input: "GEOMETRYCOLLECTION(POINT(10 10), POINT(30 30 10), LINESTRING(15 15, 20 20))".'];
    }

    /**
     * Ensure recognized but abstract geometry types are rejected with their expected exception messages.
     *
     * @param string $notInstantiableType Type to reject
     * @param string $expectedMessage     Expected exception message
     */
    #[DataProvider('notInstantiableTypesProvider')]
    public function testNotInstantiable(string $notInstantiableType, string $expectedMessage): void
    {
        self::expectException(NonInstantiableGeometryTypeException::class);
        self::expectExceptionMessageIsOrContains($expectedMessage);
        $parser = new Decoder(new EwktDecoderStrategy());
        $parser->decode($notInstantiableType.'(10 10)');
    }

    /** Ensure empty input is rejected as an unsupported geometry value. */
    public function testNullParser(): void
    {
        $parser = new Decoder(new EwktDecoderStrategy());
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIsOrContains('The supplied WKT geometry type is not supported. Invalid WKT input: "".');
        $parser->decode('');
    }

    /**
     * Ensure malformed EWKT syntax and invalid coordinate values produce the expected error messages.
     *
     * @param string $value            EWKT value to reject
     * @param string $exceptionMessage Expected exception message
     */
    #[DataProvider('unexpectedValues')]
    public function testParserWithUnexpectedValues(string $value, string $exceptionMessage): void
    {
        $parser = new Decoder(new EwktDecoderStrategy());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIsOrContains($exceptionMessage);

        $parser->decode($value);
    }

    /**
     * Ensure unknown geometry names are rejected as unsupported WKT types.
     *
     * @param string $garbage Input containing an unsupported geometry type
     * @param string $message Expected exception message
     */
    #[DataProvider('notExistentValuesProvider')]
    public function testParsingGarbage(string $garbage, string $message): void
    {
        $parser = new Decoder(new EwktDecoderStrategy());
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIsOrContains($message);
        $parser->decode($garbage);
    }

    /** Ensure an unknown geometry type is rejected with the decoder's unsupported-type error. */
    public function testUnexpectedType(): void
    {
        $parser = new Decoder(new EwktDecoderStrategy());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIsOrContains('The supplied WKT geometry type is not supported. Invalid WKT input: "foo(10 10)".');
        $parser->decode('foo(10 10)');
    }
}
