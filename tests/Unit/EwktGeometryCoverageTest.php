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

namespace LongitudeOne\SpatialDecoder\Tests\Unit;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolyhedralSurfaceInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\TriangleEwktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\PolyhedralSurfaceEwktParser
 */
class EwktGeometryCoverageTest extends TestCase
{
    /** @return iterable<string, array{string, GeometryTypeEnum, CoordinateDimensionEnum}> */
    public static function emptyGeometries(): iterable
    {
        $types = [GeometryTypeEnum::POINT, GeometryTypeEnum::LINESTRING, GeometryTypeEnum::POLYGON,
            GeometryTypeEnum::TRIANGLE, GeometryTypeEnum::POLYHEDRALSURFACE, GeometryTypeEnum::MULTIPOINT,
            GeometryTypeEnum::MULTILINESTRING, GeometryTypeEnum::MULTIPOLYGON, GeometryTypeEnum::GEOMETRYCOLLECTION];
        foreach ($types as $type) {
            foreach (CoordinateDimensionEnum::cases() as $dimension) {
                if (GeometryTypeEnum::POLYHEDRALSURFACE === $type && \in_array($dimension, [CoordinateDimensionEnum::XY, CoordinateDimensionEnum::XYM], true)) {
                    continue;
                }
                $marker = match ($dimension) {
                    CoordinateDimensionEnum::XY => '', CoordinateDimensionEnum::XYZ => ' Z',
                    CoordinateDimensionEnum::XYM => ' M', CoordinateDimensionEnum::XYZM => ' ZM',
                };
                yield $type->name.$marker => [$type->name.$marker.' EMPTY', $type, $dimension];
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidGeometries(): iterable
    {
        yield 'triangle quadrilateral' => ['TRIANGLE ((0 0,4 0,4 4,0 4,0 0))'];
        yield 'triangle hole' => ['TRIANGLE ((0 0,4 0,0 4,0 0),(1 1,2 1,1 2,1 1))'];
        yield 'triangle open ring' => ['TRIANGLE ((0 0,4 0,0 4,1 1))'];
        yield 'triangle mixed dimension' => ['TRIANGLE Z ((0 0 0,4 0 0,0 4,0 0 0))'];
        yield 'surface XY' => ['POLYHEDRALSURFACE (((0 0,4 0,0 4,0 0)))'];
        yield 'surface XY empty' => ['POLYHEDRALSURFACE EMPTY'];
        yield 'surface M empty' => ['POLYHEDRALSURFACE M EMPTY'];
        yield 'surface empty patch' => ['POLYHEDRALSURFACE Z (EMPTY)'];
        yield 'surface nonplanar patch' => ['POLYHEDRALSURFACE Z (((0 0 0,4 0 0,4 4 1,0 4 0,0 0 0)))'];
        yield 'surface mixed patches' => ['POLYHEDRALSURFACE Z (((0 0 0,4 0 0,0 4 0,0 0 0)),((0 0,4 0,0 4,0 0)))'];
        yield 'surface missing parenthesis' => ['POLYHEDRALSURFACE Z ((0 0 0,4 0 0,0 4 0,0 0 0))'];
        yield 'nested independent SRID' => ['GEOMETRYCOLLECTION Z (TRIANGLE EMPTY,SRID=3857;POLYHEDRALSURFACE EMPTY)'];
        yield 'unknown' => ['UNKNOWN EMPTY'];
    }

    /** @return iterable<string, array{GeometryTypeEnum}> */
    public static function unimplementedTypes(): iterable
    {
        foreach ([GeometryTypeEnum::CIRCULARSTRING, GeometryTypeEnum::COMPOUNDCURVE,
            GeometryTypeEnum::CURVEPOLYGON, GeometryTypeEnum::MULTICURVE,
            GeometryTypeEnum::MULTISURFACE, GeometryTypeEnum::TIN] as $type) {
            yield $type->name => [$type];
        }
    }

    /**
     * @param string                  $input     EWKT geometry
     * @param GeometryTypeEnum        $type      expected type
     * @param CoordinateDimensionEnum $dimension expected layout
     */
    #[DataProvider('emptyGeometries')]
    public function testEmptyGeometry(string $input, GeometryTypeEnum $type, CoordinateDimensionEnum $dimension): void
    {
        $geometry = (new EwktDecoderStrategy())->decode('SRID=4326;'.$input);
        self::assertTrue($geometry->isEmpty());
        self::assertSame($type, $geometry->getType());
        self::assertSame($dimension, $geometry->getDimension());
        $this->assertMetadata($geometry);
    }

    /** @param string $input invalid geometry */
    #[DataProvider('invalidGeometries')]
    public function testInvalidGeometry(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EwktDecoderStrategy())->decode('SRID=4326;'.$input);
    }

    /** Measures survive both explicit and inferred XYZM surface decoding. */
    public function testMeasuredSurface(): void
    {
        foreach (['', ' ZM'] as $marker) {
            $surface = (new EwktDecoderStrategy())->decode('SRID=4326;POLYHEDRALSURFACE'.$marker.' (((0 0 0 7,4 0 0 8,0 4 0 9,0 0 0 7)))');
            self::assertSame(CoordinateDimensionEnum::XYZM, $surface->getDimension());
            self::assertSame([[[[0, 0, 0, 7], [4, 0, 0, 8], [0, 4, 0, 9], [0, 0, 0, 7]]]], $surface->toArray());
            $this->assertMetadata($surface);
        }
    }

    /** Preserve inferred layouts, patches, nested members, and root references. */
    public function testNestedSurfaces(): void
    {
        $patch = [[0, 0, 0], [4, 0, 0], [0, 4, 0], [0, 0, 0]];
        $other = [[0, 0, 0], [0, 0, 4], [4, 0, 0], [0, 0, 0]];
        $surface = 'POLYHEDRALSURFACE (((0 0 0,4 0 0,0 4 0,0 0 0)),((0 0 0,0 0 4,4 0 0,0 0 0)))';
        $geometry = (new EwktDecoderStrategy())->decode('SRID=4326;GEOMETRYCOLLECTION ('.$surface.',GEOMETRYCOLLECTION (TRIANGLE ((0 0 0,4 0 0,0 4 0,0 0 0)),TRIANGLE EMPTY))');
        self::assertInstanceOf(CollectionInterface::class, $geometry);
        self::assertSame(CoordinateDimensionEnum::XYZ, $geometry->getDimension());
        $elements = $geometry->getElements();
        self::assertSame(GeometryTypeEnum::POLYHEDRALSURFACE, $elements[0]->getType());
        self::assertSame([[$patch], [$other]], $elements[0]->toArray());
        self::assertSame([[$patch], []], $elements[1]->toArray());
        $this->assertMetadata($geometry);
    }

    /** Preserve each triangle layout and the coordinates of its closed ring. */
    public function testTriangleLayouts(): void
    {
        foreach (['' => [0, 0], 'Z' => [0, 0, 2], 'M' => [0, 0, 7], 'ZM' => [0, 0, 2, 7]] as $marker => $origin) {
            $right = $origin;
            $right[0] = 4;
            $top = $origin;
            $top[1] = 4;
            $ring = [$origin, $right, $top, $origin];
            $text = implode(',', array_map(static fn (array $point): string => implode(' ', $point), $ring));
            $triangle = (new EwktDecoderStrategy())->decode('SRID=4326;TRIANGLE'.$marker.' (('.$text.'))');
            self::assertSame(GeometryTypeEnum::TRIANGLE, $triangle->getType());
            self::assertSame([$ring], $triangle->toArray());
            self::assertSame(str_contains($marker, 'Z'), $triangle->hasZ());
            self::assertSame(str_contains($marker, 'M'), $triangle->hasM());
            $this->assertMetadata($triangle);
        }
    }

    /** @param GeometryTypeEnum $type recognized unimplemented type */
    #[DataProvider('unimplementedTypes')]
    public function testUnimplementedType(GeometryTypeEnum $type): void
    {
        foreach ([$type->name.' EMPTY', 'GEOMETRYCOLLECTION (GEOMETRYCOLLECTION ('.$type->name.' EMPTY))'] as $input) {
            try {
                (new EwktDecoderStrategy())->decode($input);
                self::fail('Expected an unimplemented geometry exception.');
            } catch (NotYetImplementedException $exception) {
                self::assertSame($type, $exception->getSpatialType());
                self::assertStringContainsString($type->value, $exception->getMessage());
            }
        }
    }

    /** Standard WKT keeps its existing supported geometry set. */
    public function testWktSurfaceSupportIsUnchanged(): void
    {
        foreach (['TRIANGLE EMPTY', 'POLYHEDRALSURFACE Z EMPTY'] as $input) {
            try {
                (new WktDecoderStrategy())->decode($input);
                self::fail('WKT must retain its existing unsupported-type behavior.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('not supported', $exception->getMessage());
            }
        }
    }

    /** @param SpatialInterface $geometry geometry and descendants to inspect */
    private function assertMetadata(SpatialInterface $geometry): void
    {
        self::assertSame(4326, $geometry->getSrid());
        self::assertSame(SpatialModelEnum::GEOMETRY, $geometry->getFamily());
        $children = match (true) {
            $geometry instanceof PolygonInterface => $geometry->getRings(),
            $geometry instanceof LineStringInterface => $geometry->getPoints(),
            $geometry instanceof PolyhedralSurfaceInterface => $geometry->getPatches(),
            $geometry instanceof CollectionInterface => $geometry->getElements(),
            default => [],
        };
        foreach ($children as $child) {
            $this->assertMetadata($child);
        }
    }
}
