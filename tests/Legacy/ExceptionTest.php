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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\NonInstantiableGeometryTypeException;
use LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    /**
     * @return \Generator<string, array{0: string, 1: string}, null, void>
     */
    public static function notInstantiableTypes(): \Generator
    {
        yield 'CURVE' => ['CURVE', 'According the ISO 13249-3:2016 standard, the "CURVE" type is not instantiable. Did you mean "MULTICURVE"?'];
        yield 'GEOMETRY' => ['GEOMETRY', 'According the ISO 13249-3:2016 standard, the "GEOMETRY" type is not instantiable. Did you mean "GEOMETRYCOLLECTION"?'];
        yield 'SOLID' => ['SOLID', 'According the ISO 13249-3:2016 standard, the "SOLID" type is not instantiable. Did you mean "POLYGON"?'];
        yield 'SURFACE' => ['SURFACE', 'According the ISO 13249-3:2016 standard, the "SURFACE" type is not instantiable. Did you mean "MULTISURFACE"?'];
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}, null, void>
     */
    public static function notYetImplementedTypes(): \Generator
    {
        yield 'BREPSOLID' => ['BREPSOLID', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "BREPSOLID".'];
        yield 'CIRCLE' => ['CIRCLE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "CIRCLE".'];
        yield 'CLOTHOID' => ['CLOTHOID', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "CLOTHOID".'];
        yield 'COMPOUNDCURVE' => ['COMPOUNDCURVE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "COMPOUNDCURVE".'];
        yield 'COMPOUNDSURFACE' => ['COMPOUNDSURFACE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "COMPOUNDSURFACE".'];
        yield 'CURVEPOLYGON' => ['CURVEPOLYGON', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "CURVEPOLYGON".'];
        yield 'ELLIPTICALCURVE' => ['ELLIPTICALCURVE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "ELLIPTICALCURVE".'];
        yield 'GEODESICSTRING' => ['GEODESICSTRING', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "GEODESICSTRING".'];
        yield 'MULTICURVE' => ['MULTICURVE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "MULTICURVE".'];
        yield 'MULTISURFACE' => ['MULTISURFACE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "MULTISURFACE".'];
        yield 'NURBSCURVE' => ['NURBSCURVE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "NURBSCURVE".'];
        yield 'SPIRALCURVE' => ['SPIRALCURVE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "SPIRALCURVE".'];
        yield 'POLYHDRLSURFACE' => ['POLYHDRLSURFACE', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "POLYHDRLSURFACE".'];
        yield 'TIN' => ['TIN', 'The LongitudeOne\Geo\WKT\Parser is not yet able to parse "TIN".'];
    }

    public function testNotExistentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('According the ISO 13249-3:2016 standard, the "FOO" type does not exist.');

        (new Decoder(new EwktDecoderStrategy()))->decode('FOO(42 42)');
    }

    #[DataProvider('notInstantiableTypes')]
    public function testNotInstantiable(string $notInstantiableType, string $expectedMessage): void
    {
        $this->expectException(NonInstantiableGeometryTypeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $toParse = \sprintf('%s(42 42)', $notInstantiableType);

        (new Decoder(new EwktDecoderStrategy()))->decode($toParse);
    }

    public function testNotInstantiableException(): void
    {
        $exception = new NonInstantiableGeometryTypeException('foo');
        self::assertSame('According the ISO 13249-3:2016 standard, the "foo" type is not instantiable.', $exception->getMessage());
    }

    #[DataProvider('notYetImplementedTypes')]
    public function testNotYetImplemented(string $notYetImplemented, string $expectedMessage): void
    {
        $this->expectException(NotYetImplementedException::class);
        $this->expectExceptionMessage($expectedMessage);

        $toParse = \sprintf('%s(42 42)', $notYetImplemented);

        (new Decoder(new EwktDecoderStrategy()))->decode($toParse);
    }
}
