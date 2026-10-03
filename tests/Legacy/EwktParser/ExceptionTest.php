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

use LongitudeOne\Core\Enum\GeometryTypeEnum;
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
        yield 'CURVE' => ['CURVE', 'Geometry type "Curve" is recognized but is not instantiable.'];
        yield 'GEOMETRY' => ['GEOMETRY', 'Geometry type "Geometry" is recognized but is not instantiable.'];
        yield 'SOLID' => ['SOLID', 'Geometry type "Solid" is recognized but is not instantiable.'];
        yield 'SURFACE' => ['SURFACE', 'Geometry type "Surface" is recognized but is not instantiable.'];
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}, null, void>
     */
    public static function notYetImplementedTypes(): \Generator
    {
        yield 'BREPSOLID' => ['BREPSOLID', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::BREPSOLID->value)];
        yield 'CIRCLE' => ['CIRCLE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::CIRCLE->value)];
        yield 'CLOTHOID' => ['CLOTHOID', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::CLOTHOID->value)];
        // This legacy test is commented because it is now implemented in the decoder.
        // yield 'COMPOUNDCURVE' => ['COMPOUNDCURVE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::COMPOUNDCURVE->value)];
        yield 'COMPOUNDSURFACE' => ['COMPOUNDSURFACE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::COMPOUNDSURFACE->value)];
        yield 'CURVEPOLYGON' => ['CURVEPOLYGON', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::CURVEPOLYGON->value)];
        yield 'ELLIPTICALCURVE' => ['ELLIPTICALCURVE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::ELLIPTICALCURVE->value)];
        yield 'GEODESICSTRING' => ['GEODESICSTRING', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::GEODESICSTRING->value)];
        yield 'MULTICURVE' => ['MULTICURVE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::MULTICURVE->value)];
        yield 'MULTISURFACE' => ['MULTISURFACE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::MULTISURFACE->value)];
        yield 'NURBSCURVE' => ['NURBSCURVE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::NURBSCURVE->value)];
        yield 'SPIRALCURVE' => ['SPIRALCURVE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::SPIRALCURVE->value)];
        // This legacy test is commented because it is now implemented in the decoder.
        // yield 'POLYHEDRALSURFACE' => ['POLYHEDRALSURFACE', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::POLYHEDRALSURFACE->value)];
        yield 'TIN' => ['TIN', \sprintf('Decoding of spatial type "%s" is not yet implemented.', GeometryTypeEnum::TIN->value)];
    }

    /** Ensure an unknown geometry name raises the decoder's invalid-argument exception. */
    public function testNotExistentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The supplied WKT geometry type is not supported. Invalid WKT input: "FOO(42 42)".');

        (new Decoder(new EwktDecoderStrategy()))->decode('FOO(42 42)');
    }

    /**
     * Ensure recognized but non-instantiable types raise the matching geometry-type exception.
     *
     * @param string $notInstantiableType Type to reject
     * @param string $expectedMessage     Expected exception message
     */
    #[DataProvider('notInstantiableTypes')]
    public function testNotInstantiable(string $notInstantiableType, string $expectedMessage): void
    {
        $this->expectException(NonInstantiableGeometryTypeException::class);
        $this->expectExceptionMessageIsOrContains($expectedMessage);

        $toParse = \sprintf('%s(42 42)', $notInstantiableType);

        (new Decoder(new EwktDecoderStrategy()))->decode($toParse);
    }

    /** Ensure the non-instantiable exception exposes the correct message and geometry type. */
    public function testNotInstantiableException(): void
    {
        $exception = new NonInstantiableGeometryTypeException(GeometryTypeEnum::GEOMETRY);
        self::assertSame('Geometry type "Geometry" is recognized but is not instantiable.', $exception->getMessage());
        self::assertSame(GeometryTypeEnum::GEOMETRY, $exception->getSpatialType());
    }

    /**
     * Ensure recognized but unimplemented geometry types raise the matching exception and message.
     *
     * @param string $notYetImplemented Type not yet supported
     * @param string $expectedMessage   Expected exception message
     */
    #[DataProvider('notYetImplementedTypes')]
    public function testNotYetImplemented(string $notYetImplemented, string $expectedMessage): void
    {
        $this->expectException(NotYetImplementedException::class);
        $this->expectExceptionMessageIsOrContains($expectedMessage);

        $toParse = \sprintf('%s(42 42)', $notYetImplemented);

        (new Decoder(new EwktDecoderStrategy()))->decode($toParse);
    }
}
