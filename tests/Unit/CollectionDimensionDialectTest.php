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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
class CollectionDimensionDialectTest extends TestCase
{
    /** @return iterable<string, array{string, CoordinateDimensionEnum, list<list<int|float>>}> */
    public static function dimensions(): iterable
    {
        yield 'Z' => ['Z', CoordinateDimensionEnum::XYZ, [[0, 0, 2], [1, 1, 3], [2, 0, 4]]];
        yield 'M' => ['M', CoordinateDimensionEnum::XYM, [[0, 0, 2], [1, 1, 3], [2, 0, 4]]];
        yield 'ZM' => ['ZM', CoordinateDimensionEnum::XYZM, [[0, 0, 2, 3], [1, 1, 3, 4], [2, 0, 4, 5]]];
    }

    /** @return iterable<string, array{string}> */
    public static function implicitWktMembers(): iterable
    {
        foreach (self::dimensions() as $name => [$marker, , $coordinates]) {
            $body = self::coordinateBody($coordinates);
            foreach (['CIRCULARSTRING '.$body, 'CIRCULARSTRING EMPTY', 'POINT (0 0'.('ZM' === $marker ? ' 2 3' : ' 2').')', 'POINT EMPTY'] as $member) {
                foreach ([$member, 'GEOMETRYCOLLECTION ('.$member.')'] as $nested) {
                    yield $name.' sibling '.$nested => ['GEOMETRYCOLLECTION (POINT '.$marker.' EMPTY,'.$nested.')'];
                    yield $name.' reversed '.$nested => ['GEOMETRYCOLLECTION ('.$nested.',POINT '.$marker.' EMPTY)'];
                }
            }
        }
    }

    /**
     * @param list<list<int|float>> $coordinates control points
     */
    private static function coordinateBody(array $coordinates): string
    {
        return '('.implode(',', array_map(static fn (array $point): string => implode(' ', $point), $coordinates)).')';
    }

    /**
     * @param string                  $marker      dimensional marker
     * @param CoordinateDimensionEnum $dimension   expected layout
     * @param list<list<int|float>>   $coordinates expected control points
     */
    #[DataProvider('dimensions')]
    public function testEwktPreservesSiblingPropagation(string $marker, CoordinateDimensionEnum $dimension, array $coordinates): void
    {
        $body = self::coordinateBody($coordinates);
        $firstMembers = ['POINT '.$marker.' EMPTY', 'CIRCULARSTRING '.$marker.' '.$body];
        if ('M' !== $marker) {
            $firstMembers[] = 'CIRCULARSTRING '.$body;
        }
        foreach ($firstMembers as $first) {
            $collection = (new EwktDecoderStrategy())->decode('SRID=4326;GEOMETRYCOLLECTION ('.$first.',GEOMETRYCOLLECTION (CIRCULARSTRING '.$body.',CIRCULARSTRING EMPTY))');
            self::assertInstanceOf(CollectionInterface::class, $collection);
            $nested = $collection->getElements()[1];
            self::assertInstanceOf(CollectionInterface::class, $nested);
            $curve = $nested->getElements()[0];
            $empty = $nested->getElements()[1];
            self::assertInstanceOf(CircularStringInterface::class, $curve);
            self::assertInstanceOf(CircularStringInterface::class, $empty);
            self::assertSame($dimension, $curve->getDimension());
            self::assertSame($coordinates, $curve->toArray());
            self::assertSame($dimension, $empty->getDimension());
            self::assertTrue($empty->isEmpty());
            self::assertSame(4326, $curve->getSrid());
            self::assertSame(4326, $empty->getSrid());
        }
    }

    /**
     * @param string                  $marker      dimensional marker
     * @param CoordinateDimensionEnum $dimension   expected layout
     * @param list<list<int|float>>   $coordinates expected control points
     */
    #[DataProvider('dimensions')]
    public function testExplicitAncestorAndMemberMarkers(string $marker, CoordinateDimensionEnum $dimension, array $coordinates): void
    {
        $body = self::coordinateBody($coordinates);
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            foreach ([
                'GEOMETRYCOLLECTION '.$marker.' (POINT EMPTY,GEOMETRYCOLLECTION (CIRCULARSTRING '.$body.',CIRCULARSTRING EMPTY))',
                'GEOMETRYCOLLECTION (POINT '.$marker.' EMPTY,GEOMETRYCOLLECTION (CIRCULARSTRING '.$marker.' '.$body.',CIRCULARSTRING '.$marker.' EMPTY))',
            ] as $input) {
                $collection = $strategy->decode($input);
                self::assertInstanceOf(CollectionInterface::class, $collection);
                $nested = $collection->getElements()[1];
                self::assertInstanceOf(CollectionInterface::class, $nested);
                $curve = $nested->getElements()[0];
                $empty = $nested->getElements()[1];
                self::assertInstanceOf(CircularStringInterface::class, $curve);
                self::assertInstanceOf(CircularStringInterface::class, $empty);
                self::assertSame($dimension, $curve->getDimension());
                self::assertSame($coordinates, $curve->toArray());
                self::assertSame($dimension, $empty->getDimension());
                self::assertTrue($empty->isEmpty());
            }
        }
    }

    /** @param string $input WKT lacking an explicit ancestor or member marker */
    #[DataProvider('implicitWktMembers')]
    public function testWktRejectsSiblingDimension(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WktDecoderStrategy())->decode($input);
    }
}
