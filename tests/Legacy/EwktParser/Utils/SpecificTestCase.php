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

namespace LongitudeOne\SpatialDecoder\Tests\Legacy\EwktParser\Utils;

use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException as InvalidCoordinateException;

class SpecificTestCase extends TestCase
{
    /**
     * Assert that a decoded CircularString matches its expected metadata and coordinates.
     *
     * @param int|null               $expectedSrid        Expected spatial reference identifier
     * @param (float|int|string)[][] $expectedCoordinates Expected circular string coordinates
     * @param string|null            $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface       $actual              Decoded spatial object
     * @param string                 $message             Assertion failure message
     */
    protected static function assertCircularStringParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'CIRCULARSTRING', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded GeometryCollection and each of its elements match expectations.
     *
     * @param int|null                                                           $expectedSrid        Expected spatial reference identifier
     * @param array<array{type: string, value: (int|string)[]|(int|string)[][]}> $expectedCoordinates Expected child geometries
     * @param string|null                                                        $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface                                                   $actual              Decoded spatial object
     * @param string                                                             $message             Assertion failure message
     */
    protected static function assertGeometryCollectionParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertSame('GEOMETRYCOLLECTION', strtoupper($actual->getType()->value), $message);
        self::assertSame($expectedSrid ?? 0, $actual->getSrid(), $message);
        self::assertSame($expectedDimension ?? '', $actual->getDimension()->wktModifier(), $message);
        self::assertInstanceOf(CollectionInterface::class, $actual, $message);

        $elements = $actual->getElements();
        self::assertCount(\count($expectedCoordinates), $elements, $message);
        foreach ($expectedCoordinates as $index => $expectedElement) {
            self::assertParsed($expectedSrid, $expectedElement['type'], $expectedElement['value'], $expectedDimension, $elements[$index], $message);
        }
    }

    /**
     * Assert that a decoded LineString matches its expected metadata and coordinates.
     *
     * @param int|null         $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][] $expectedCoordinates Expected line coordinates
     * @param string|null      $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface $actual              Decoded spatial object
     * @param string           $message             Assertion failure message
     */
    protected static function assertLineStringParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'LINESTRING', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded MultiLineString matches its expected metadata and coordinates.
     *
     * @param int|null           $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][][] $expectedCoordinates Expected multiline coordinates
     * @param string|null        $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface   $actual              Decoded spatial object
     * @param string             $message             Assertion failure message
     */
    protected static function assertMultiLineStringParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'MULTILINESTRING', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded MultiPoint matches its expected metadata and coordinates.
     *
     * @param int|null         $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][] $expectedCoordinates Expected point coordinates
     * @param string|null      $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface $actual              Decoded spatial object
     * @param string           $message             Assertion failure message
     */
    protected static function assertMultiPointParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'MULTIPOINT', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded MultiPolygon matches its expected metadata and coordinates.
     *
     * @param int|null             $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][][][] $expectedCoordinates Expected multipolygon coordinates
     * @param string|null          $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface     $actual              Decoded spatial object
     * @param string               $message             Assertion failure message
     */
    protected static function assertMultiPolygonParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'MULTIPOLYGON', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded Point matches its expected metadata and ordinates.
     *
     * @param int|null         $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[]   $expectedCoordinates Expected point ordinates
     * @param string|null      $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface $actual              Decoded spatial object
     * @param string           $message             Assertion failure message
     */
    protected static function assertPointParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'POINT', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded Polygon matches its expected metadata and coordinates.
     *
     * @param int|null           $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][][] $expectedCoordinates Expected polygon coordinates
     * @param string|null        $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface   $actual              Decoded spatial object
     * @param string             $message             Assertion failure message
     */
    protected static function assertPolygonParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertParsed($expectedSrid, 'POLYGON', $expectedCoordinates, $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded Triangle matches its expected metadata and coordinates.
     *
     * @param int|null         $expectedSrid        Expected spatial reference identifier
     * @param (int|string)[][] $expectedCoordinates Expected triangle coordinates
     * @param string|null      $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface $actual              Decoded spatial object
     * @param string           $message             Assertion failure message
     */
    protected static function assertTriangleParsed(?int $expectedSrid, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        // Spatial triangles expose the same ring nesting as polygons.
        self::assertParsed($expectedSrid, 'TRIANGLE', [$expectedCoordinates], $expectedDimension, $actual, $message);
    }

    /**
     * Assert that a decoded geometry matches its type, metadata, and numeric coordinates.
     *
     * @param int|null                                                                                        $expectedSrid        Expected spatial reference identifier
     * @param string                                                                                          $type                Expected geometry type
     * @param (float|int|string)[]|(float|int|string)[][]|(float|int|string)[][][]|(float|int|string)[][][][] $expectedCoordinates Expected coordinate values
     * @param string|null                                                                                     $expectedDimension   Expected coordinate dimension
     * @param SpatialInterface                                                                                $actual              Decoded spatial object
     * @param string                                                                                          $message             Assertion failure message
     */
    private static function assertParsed(?int $expectedSrid, string $type, array $expectedCoordinates, ?string $expectedDimension, SpatialInterface $actual, string $message = ''): void
    {
        self::assertSame($type, strtoupper($actual->getType()->value), $message);
        self::assertSame($expectedSrid ?? 0, $actual->getSrid(), $message);
        self::assertSame($expectedDimension ?? '', $actual->getDimension()->wktModifier(), $message);

        self::assertSame(self::numericCoordinates($expectedCoordinates), $actual->toArray(), $message);
    }

    /**
     * Convert legacy lexer numeric strings to the numbers exposed by spatial objects.
     *
     * @param array<array-key, mixed> $coordinates legacy coordinate values
     *
     * @return array<array-key, mixed>
     */
    private static function numericCoordinates(array $coordinates): array
    {
        foreach ($coordinates as $index => $coordinate) {
            if (\is_array($coordinate)) {
                $coordinates[$index] = self::numericCoordinates($coordinate);

                continue;
            }

            if (!is_numeric($coordinate)) {
                throw new InvalidCoordinateException('Expected a numeric coordinate.');
            }

            $coordinates[$index] = $coordinate + 0;
        }

        return $coordinates;
    }
}
