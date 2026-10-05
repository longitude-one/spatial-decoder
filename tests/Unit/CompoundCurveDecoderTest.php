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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
class CompoundCurveDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CoordinateDimensionEnum, list<int|float>, list<int|float>, list<int|float>, list<int|float>}>
     */
    public static function dimensions(): iterable
    {
        yield 'XY' => ['', CoordinateDimensionEnum::XY, [0, 0], [1, 1], [2, 0], [3, 1]];
        yield 'XYZ' => [' Z', CoordinateDimensionEnum::XYZ, [0, 0, 1], [1, 1, 2], [2, 0, 3], [3, 1, 4]];
        yield 'XYM' => [' M', CoordinateDimensionEnum::XYM, [0, 0, 1], [1, 1, 2], [2, 0, 3], [3, 1, 4]];
        yield 'XYZM' => [' ZM', CoordinateDimensionEnum::XYZM, [0, 0, 1, 2], [1, 1, 2, 3], [2, 0, 3, 4], [3, 1, 4, 5]];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCurves(): iterable
    {
        yield 'typed linear component' => ['COMPOUNDCURVE (LINESTRING (0 0,1 1))'];
        yield 'unsupported component' => ['COMPOUNDCURVE (POINT (0 0))'];
        yield 'disconnected components' => ['COMPOUNDCURVE ((0 0,1 1),CIRCULARSTRING (2 2,3 3,4 4))'];
        yield 'empty line component' => ['COMPOUNDCURVE (EMPTY)'];
        yield 'empty circular component' => ['COMPOUNDCURVE (CIRCULARSTRING EMPTY)'];
        yield 'parent XYZ with short component' => ['COMPOUNDCURVE Z ((0 0 1,1 1 2),CIRCULARSTRING (1 1,2 0,3 1))'];
        yield 'conflicting component marker' => ['COMPOUNDCURVE Z (CIRCULARSTRING M (0 0 1,1 1 2,2 0 3))'];
        yield 'EWKT independently inferred component dimension' => ['COMPOUNDCURVE ((0 0 1,1 1 2),CIRCULARSTRING (1 1,2 0,3 1))'];
        yield 'malformed component' => ['COMPOUNDCURVE ((0 0,1 1),)'];
    }

    /**
     * Format a coordinate tuple for a WKT fixture.
     *
     * @param list<int|float> $tuple coordinate ordinates
     */
    private static function tuple(array $tuple): string
    {
        return implode(' ', $tuple);
    }

    /** EMPTY, compact/inferred EWKT dimensions and curve closure are retained. */
    public function testEmptyAndClosedCurves(): void
    {
        foreach ([
            'COMPOUNDCURVE EMPTY' => CoordinateDimensionEnum::XY,
            'COMPOUNDCURVE Z EMPTY' => CoordinateDimensionEnum::XYZ,
            'COMPOUNDCURVE M EMPTY' => CoordinateDimensionEnum::XYM,
            'COMPOUNDCURVE ZM EMPTY' => CoordinateDimensionEnum::XYZM,
        ] as $input => $dimension) {
            foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
                $empty = $strategy->decode($input);
                self::assertInstanceOf(CompoundCurveInterface::class, $empty);
                self::assertTrue($empty->isEmpty());
                self::assertSame($dimension, $empty->getDimension());
            }
        }
        foreach ([
            'COMPOUNDCURVE EMPTY' => CoordinateDimensionEnum::XY,
            'COMPOUNDCURVEZ EMPTY' => CoordinateDimensionEnum::XYZ,
            'COMPOUNDCURVEM EMPTY' => CoordinateDimensionEnum::XYM,
            'COMPOUNDCURVEZM EMPTY' => CoordinateDimensionEnum::XYZM,
        ] as $input => $dimension) {
            $empty = (new EwktDecoderStrategy())->decode($input);
            self::assertInstanceOf(CompoundCurveInterface::class, $empty);
            self::assertSame($dimension, $empty->getDimension());
        }

        $closed = (new WktDecoderStrategy())->decode('COMPOUNDCURVE ((0 0,1 1,0 0))');
        self::assertInstanceOf(CompoundCurveInterface::class, $closed);
        self::assertTrue($closed->isClosed());

        $inferred = (new EwktDecoderStrategy())->decode('COMPOUNDCURVE ((0 0 1,1 1 2),CIRCULARSTRING (1 1 2,2 0 3,3 1 4))');
        self::assertInstanceOf(CompoundCurveInterface::class, $inferred);
        self::assertSame(CoordinateDimensionEnum::XYZ, $inferred->getDimension());
        self::assertSame(CoordinateDimensionEnum::XYZ, $inferred->getCurve(1)->getDimension());

        $nested = (new EwktDecoderStrategy())->decode('SRID=4326;GEOMETRYCOLLECTION (COMPOUNDCURVE ((0 0,1 1),CIRCULARSTRING (1 1,2 0,3 1)))');
        self::assertInstanceOf(CollectionInterface::class, $nested);
        $nestedCurve = $nested->getElements()[0];
        self::assertInstanceOf(CompoundCurveInterface::class, $nestedCurve);
        self::assertSame(4326, $nestedCurve->getSrid());
        self::assertSame(4326, $nestedCurve->getCurve(0)->getSrid());
    }

    /** Root EWKT SRID is retained by the compound curve, parts, and their points. */
    public function testEwktPropagatesSridToComponents(): void
    {
        $curve = (new EwktDecoderStrategy())->decode('SRID=4326;COMPOUNDCURVE Z ((0 0 1,1 1 2),CIRCULARSTRING (1 1 2,2 0 3,3 1 4))');
        self::assertInstanceOf(CompoundCurveInterface::class, $curve);
        self::assertSame(4326, $curve->getSrid());

        foreach ($curve->getCurves() as $part) {
            self::assertSame(4326, $part->getSrid());
            foreach ([$part->getStartPoint(), $part->getEndPoint()] as $point) {
                self::assertNotNull($point);
                self::assertSame(4326, $point->getSrid());
            }
        }
    }

    /**
     * Decode all dimensional layouts while preserving ordered concrete curve types.
     *
     * @param string                  $marker    parent dimension marker
     * @param CoordinateDimensionEnum $dimension expected layout
     * @param list<int|float>         $start     line start point
     * @param list<int|float>         $join      shared component endpoint
     * @param list<int|float>         $middle    circular-string control point
     * @param list<int|float>         $end       circular-string endpoint
     */
    #[DataProvider('dimensions')]
    public function testPreservesMixedCurvesAndDimensions(
        string $marker,
        CoordinateDimensionEnum $dimension,
        array $start,
        array $join,
        array $middle,
        array $end
    ): void {
        $input = 'COMPOUNDCURVE'.$marker.' (('.self::tuple($start).','.self::tuple($join).'),CIRCULARSTRING ('.self::tuple($join).','.self::tuple($middle).','.self::tuple($end).'))';
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $curve = $strategy->decode($input);
            self::assertInstanceOf(CompoundCurveInterface::class, $curve);
            self::assertSame(GeometryTypeEnum::COMPOUNDCURVE, $curve->getType());
            self::assertSame($dimension, $curve->getDimension());
            self::assertSame([[$start, $join], [$join, $middle, $end]], $curve->toArray());
            self::assertFalse($curve->isClosed());
            self::assertCount(2, $curve->getCurves());
            self::assertInstanceOf(LineStringInterface::class, $curve->getCurve(0));
            self::assertInstanceOf(CircularStringInterface::class, $curve->getCurve(1));
            self::assertSame($dimension, $curve->getCurve(0)->getDimension());
            self::assertSame($dimension, $curve->getCurve(1)->getDimension());
        }
    }

    /**
     * Invalid structures follow the decoder exception contract in both dialects.
     *
     * @param string $input invalid WKT or EWKT representation
     */
    #[DataProvider('invalidCurves')]
    public function testRejectsInvalidCompoundCurves(string $input): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            try {
                $strategy->decode($strategy instanceof EwktDecoderStrategy ? 'SRID=4326;'.$input : $input);
                self::fail('Invalid compound curves must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /** Parenthesized linear components and single curve families are supported. */
    public function testSupportsLinearAndSingleFamilyComponents(): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $linear = $strategy->decode('COMPOUNDCURVE ((0 0,1 1,2 0))');
            self::assertInstanceOf(CompoundCurveInterface::class, $linear);
            self::assertInstanceOf(LineStringInterface::class, $linear->getCurve(0));
            self::assertSame([[[0, 0], [1, 1], [2, 0]]], $linear->toArray());

            $circular = $strategy->decode('COMPOUNDCURVE (CIRCULARSTRING (0 0,1 1,2 0))');
            self::assertInstanceOf(CompoundCurveInterface::class, $circular);
            self::assertInstanceOf(CircularStringInterface::class, $circular->getCurve(0));
            self::assertSame([[[0, 0], [1, 1], [2, 0]]], $circular->toArray());
        }
    }

    /** Standard WKT continues to reject an EWKT SRID prefix. */
    public function testWktRejectsSrid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode('SRID=4326;COMPOUNDCURVE EMPTY');
    }

    /** Standard WKT requires an explicit marker for non-XY coordinates. */
    public function testWktRequiresDimensionMarkerForNonXyCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode('COMPOUNDCURVE ((0 0 1,1 1 2),CIRCULARSTRING (1 1 2,2 0 3,3 1 4))');
    }

    /** Domain validation failures are wrapped and retain their cause. */
    public function testWrapsSpatialContractFailure(): void
    {
        try {
            (new WktDecoderStrategy())->decode('COMPOUNDCURVE ((0 0,1 1),CIRCULARSTRING (2 2,3 3,4 4))');
            self::fail('Disconnected components must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertInstanceOf(SpatialTypeExceptionInterface::class, $exception->getPrevious());
        }
    }
}
