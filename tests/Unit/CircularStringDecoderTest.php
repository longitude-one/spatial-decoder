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
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Exception\SpatialTypeExceptionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
class CircularStringDecoderTest extends TestCase
{
    /** @return iterable<string, array{string, list<list<int|float>>, CoordinateDimensionEnum}> */
    public static function curves(): iterable
    {
        yield 'single arc XY' => ['CIRCULARSTRING (0 0,1 1,2 0)', [[0, 0], [1, 1], [2, 0]], CoordinateDimensionEnum::XY];
        yield 'multiple arcs' => ['CIRCULARSTRING (0 0,1 1,2 0,3 -1,4 0)', [[0, 0], [1, 1], [2, 0], [3, -1], [4, 0]], CoordinateDimensionEnum::XY];
        yield 'closed circle' => ['CIRCULARSTRING (1 0,-1 0,1 0)', [[1, 0], [-1, 0], [1, 0]], CoordinateDimensionEnum::XY];
        yield 'collinear' => ['CIRCULARSTRING (0 0,1 0,2 0)', [[0, 0], [1, 0], [2, 0]], CoordinateDimensionEnum::XY];
        yield 'XYZ' => ['CIRCULARSTRING Z (0 0 2,1 1 3,2 0 4)', [[0, 0, 2], [1, 1, 3], [2, 0, 4]], CoordinateDimensionEnum::XYZ];
        yield 'XYM' => ['CIRCULARSTRING M (0 0 2,1 1 3,2 0 4)', [[0, 0, 2], [1, 1, 3], [2, 0, 4]], CoordinateDimensionEnum::XYM];
        yield 'XYZM' => ['CIRCULARSTRING ZM (0 0 2 5,1 1 3 6,2 0 4 7)', [[0, 0, 2, 5], [1, 1, 3, 6], [2, 0, 4, 7]], CoordinateDimensionEnum::XYZM];
        yield 'case whitespace numeric syntax' => [" circularstring\n( -2.5 +0, 1e0 2.5, 3.5 -1 ) ", [[-2.5, 0], [1.0, 2.5], [3.5, -1]], CoordinateDimensionEnum::XY];
        foreach (['' => CoordinateDimensionEnum::XY, ' Z' => CoordinateDimensionEnum::XYZ, ' M' => CoordinateDimensionEnum::XYM, ' ZM' => CoordinateDimensionEnum::XYZM] as $marker => $dimension) {
            yield 'empty '.$dimension->name => ['CIRCULARSTRING'.$marker.' EMPTY', [], $dimension];
        }
    }

    /** @return iterable<string, array{string, CoordinateDimensionEnum}> */
    public static function ewktDimensions(): iterable
    {
        yield 'inferred XYZ' => ['CIRCULARSTRING (0 0 2,1 1 3,2 0 4)', CoordinateDimensionEnum::XYZ];
        yield 'inferred XYZM' => ['CIRCULARSTRING (0 0 2 5,1 1 3 6,2 0 4 7)', CoordinateDimensionEnum::XYZM];
        yield 'compact Z' => ['CIRCULARSTRINGZ (0 0 2,1 1 3,2 0 4)', CoordinateDimensionEnum::XYZ];
        yield 'compact M' => ['CIRCULARSTRINGM (0 0 2,1 1 3,2 0 4)', CoordinateDimensionEnum::XYM];
        yield 'compact ZM' => ['CIRCULARSTRINGZM (0 0 2 5,1 1 3 6,2 0 4 7)', CoordinateDimensionEnum::XYZM];
        yield 'compact empty Z' => ['CIRCULARSTRINGZ EMPTY', CoordinateDimensionEnum::XYZ];
        yield 'compact empty M' => ['CIRCULARSTRINGM EMPTY', CoordinateDimensionEnum::XYM];
        yield 'compact empty ZM' => ['CIRCULARSTRINGZM EMPTY', CoordinateDimensionEnum::XYZM];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCurves(): iterable
    {
        yield 'collection dimension conflict' => ['GEOMETRYCOLLECTION Z (CIRCULARSTRING M EMPTY)'];
        yield 'collection tuple mismatch' => ['GEOMETRYCOLLECTION Z (CIRCULARSTRING (0 0 1,1 1,2 0 3))'];
        yield 'nested SRID' => ['GEOMETRYCOLLECTION (SRID=3857;CIRCULARSTRING EMPTY)'];

        foreach (['(0 0)', '(0 0,1 1)', '(0 0,1 1,2 0,3 1)', '(0 0,1 1,2 0,3 1,4 0,5 1)',
            '(0 0,0 0,2 0)', '(0 0,2 0,2 0)', '(0 0,1 1,2 0,2 0,4 0)',
            '()', '(EMPTY)', '(garbage)', '(0 0,1 1,2 0,)', '(0 0,1 1,2 0',
            '((0 0,1 1,2 0))', '(0 0,1 1 2,2 0)', 'Z (0 0 1,1 1,2 0 3)',
            'M (0 0 1,1 1 2,2 0)', 'ZM (0 0 1 2,1 1 2 3,2 0 3)',
            'Z M EMPTY', 'EMPTY trailing', '(0 0,1e999 1,2 0)', '(0 0,1@1,2 0)'] as $body) {
            yield $body => ['CIRCULARSTRING '.$body];
        }
    }

    /** Verify nested curves inherit collection dimensions and the EWKT reference. */
    public function testCollectionInheritance(): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $prefix = $strategy instanceof EwktDecoderStrategy ? 'SRID=4326;' : '';
            $collection = $strategy->decode($prefix.'GEOMETRYCOLLECTION M (CIRCULARSTRING (0 0 2,1 1 3,2 0 4),GEOMETRYCOLLECTION (CIRCULARSTRING EMPTY))');
            self::assertInstanceOf(CollectionInterface::class, $collection);
            $curve = $collection->getElements()[0];
            self::assertInstanceOf(CircularStringInterface::class, $curve);
            self::assertSame(CoordinateDimensionEnum::XYM, $curve->getDimension());
            self::assertSame([[0, 0, 2], [1, 1, 3], [2, 0, 4]], $curve->toArray());
            self::assertSame($collection->getSrid(), $curve->getSrid());
            $nested = $collection->getElements()[1];
            self::assertInstanceOf(CollectionInterface::class, $nested);
            $empty = $nested->getElements()[0];
            self::assertInstanceOf(CircularStringInterface::class, $empty);
            self::assertTrue($empty->isEmpty());
            self::assertSame(CoordinateDimensionEnum::XYM, $empty->getDimension());
            self::assertSame($collection->getSrid(), $empty->getSrid());
        }
    }

    /**
     * @param string                  $input     EWKT representation
     * @param CoordinateDimensionEnum $dimension expected layout
     */
    #[DataProvider('ewktDimensions')]
    public function testEwktDimensionExtensions(string $input, CoordinateDimensionEnum $dimension): void
    {
        foreach (['', 'SRID=3857;'] as $prefix) {
            $curve = (new EwktDecoderStrategy())->decode($prefix.$input);
            self::assertInstanceOf(CircularStringInterface::class, $curve);
            self::assertSame($dimension, $curve->getDimension());
            self::assertSame('' === $prefix ? 0 : 3857, $curve->getSrid());
        }

        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode($input);
    }

    /**
     * @param string                  $input       text representation
     * @param list<list<int|float>>   $coordinates original control points
     * @param CoordinateDimensionEnum $dimension   expected coordinate layout
     */
    #[DataProvider('curves')]
    public function testPreservesCircularString(string $input, array $coordinates, CoordinateDimensionEnum $dimension): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            foreach ($strategy instanceof EwktDecoderStrategy ? ['', 'SRID=4326;'] : [''] as $prefix) {
                $curve = (new Decoder($strategy))->decode($prefix.$input);
                self::assertInstanceOf(CircularStringInterface::class, $curve);
                self::assertSame(GeometryTypeEnum::CIRCULARSTRING, $curve->getType());
                self::assertSame($dimension, $curve->getDimension());
                self::assertSame($coordinates, $curve->toArray());
                self::assertSame([] === $coordinates, $curve->isEmpty());
                self::assertSame('' === $prefix ? 0 : 4326, $curve->getSrid());
                self::assertSame([] !== $coordinates && $coordinates[0] === $coordinates[\count($coordinates) - 1], $curve->isClosed());
                foreach ($curve->getPoints() as $point) {
                    self::assertSame($curve->getSrid(), $point->getSrid());
                }
            }
        }
    }

    /** @param string $input malformed circular string */
    #[DataProvider('invalidCurves')]
    public function testRejectsInvalidCurves(string $input): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            try {
                $strategy->decode($strategy instanceof EwktDecoderStrategy ? 'SRID=4326;'.$input : $input);
                self::fail('Invalid circular strings must fail through the decoder exception contract.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /** Verify WKT continues to reject the EWKT reference prefix. */
    public function testWktRejectsSrid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode('SRID=4326;CIRCULARSTRING (0 0,1 1,2 0)');
    }

    /** Verify domain validation is translated without losing its cause. */
    public function testWrapsSpatialValidationFailure(): void
    {
        try {
            (new WktDecoderStrategy())->decode('CIRCULARSTRING (0 0,1 1)');
            self::fail('An invalid control-point count must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertInstanceOf(SpatialTypeExceptionInterface::class, $exception->getPrevious());
        }
    }
}
