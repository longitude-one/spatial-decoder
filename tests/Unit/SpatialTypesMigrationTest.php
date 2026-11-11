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

use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Factory\SpatialFactory;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
class SpatialTypesMigrationTest extends TestCase
{
    /** @return iterable<string, array{string, list<mixed>}> */
    public static function geometricErrors(): iterable
    {
        yield 'singleton line' => ['LINESTRING (1 2)', [[1, 2]]];
        yield 'singleton multiline member' => ['MULTILINESTRING ((1 2))', [[[1, 2]]]];
        yield 'unclosed ring' => ['POLYGON ((0 0,4 0,4 4,1 1))', [[[0, 0], [4, 0], [4, 4], [1, 1]]]];
        yield 'short ring' => ['POLYGON ((0 0,4 0,0 0))', [[[0, 0], [4, 0], [0, 0]]]];
        yield 'unclosed multipolygon member' => ['MULTIPOLYGON (((0 0,4 0,4 4,1 1)))', [[[[0, 0], [4, 0], [4, 4], [1, 1]]]]];
        yield 'one control point' => ['CIRCULARSTRING (0 0)', [[0, 0]]];
        yield 'two control points' => ['CIRCULARSTRING (0 0,1 1)', [[0, 0], [1, 1]]];
        yield 'four control points' => ['CIRCULARSTRING (0 0,1 1,2 0,3 1)', [[0, 0], [1, 1], [2, 0], [3, 1]]];
        yield 'six control points' => ['CIRCULARSTRING (0 0,1 1,2 0,3 1,4 0,5 1)', [[0, 0], [1, 1], [2, 0], [3, 1], [4, 0], [5, 1]]];
        yield 'repeated arc start' => ['CIRCULARSTRING (0 0,0 0,2 0)', [[0, 0], [0, 0], [2, 0]]];
        yield 'repeated arc end' => ['CIRCULARSTRING (0 0,2 0,2 0)', [[0, 0], [2, 0], [2, 0]]];
        yield 'repeated arc join' => ['CIRCULARSTRING (0 0,1 1,2 0,2 0,4 0)', [[0, 0], [1, 1], [2, 0], [2, 0], [4, 0]]];
        yield 'disconnected components' => ['COMPOUNDCURVE ((0 0,1 1),CIRCULARSTRING (2 2,3 3,4 4))', [[[0, 0], [1, 1]], [[2, 2], [3, 3], [4, 4]]]];
        yield 'empty circular component' => ['COMPOUNDCURVE (CIRCULARSTRING EMPTY)', [[]]];
        yield 'nested error' => ['GEOMETRYCOLLECTION (GEOMETRYCOLLECTION (POLYGON ((0 0,1 1))))', [[[[[0, 0], [1, 1]]]]]];
    }

    /** @return iterable<string, array{string, list<mixed>}> */
    public static function surfaceErrors(): iterable
    {
        yield 'triangle quadrilateral' => ['TRIANGLE ((0 0,4 0,4 4,0 4,0 0))', [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]]];
        yield 'triangle hole' => ['TRIANGLE ((0 0,4 0,0 4,0 0),(1 1,2 1,1 2,1 1))', [[[0, 0], [4, 0], [0, 4], [0, 0]], [[1, 1], [2, 1], [1, 2], [1, 1]]]];
        yield 'empty patch' => ['POLYHEDRALSURFACE Z (EMPTY)', [[]]];
        yield 'nonplanar patch' => ['POLYHEDRALSURFACE Z (((0 0 0,4 0 0,4 4 1,0 4 0,0 0 0)))', [[[[0, 0, 0], [4, 0, 0], [4, 4, 1], [0, 4, 0], [0, 0, 0]]]]];
    }

    /** Text decoding remains immutable even when public factories use mutable mode. */
    public function testIgnoresSharedFactoryMode(): void
    {
        $mutable = SpatialFactory::isMutable();
        SpatialFactory::mutable();
        try {
            foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
                $geometry = $strategy->decode('GEOMETRYCOLLECTION (POINT (1 2),GEOMETRYCOLLECTION (LINESTRING (3 4)))');
                $this->assertImmutableGraph($geometry, 0);
                $copy = $geometry->withSrid(4326);
                $this->assertImmutableGraph($copy, 4326);
                $this->assertImmutableGraph($geometry, 0);
                self::assertTrue(SpatialFactory::isMutable());
            }
        } finally {
            if (!$mutable) {
                SpatialFactory::immutable();
            }
        }
    }

    /**
     * Preserve geometric errors in either dialect for explicit caller validation.
     *
     * @param string                  $input       representation to decode
     * @param array<array-key, mixed> $coordinates exact original coordinates
     */
    #[DataProvider('geometricErrors')]
    public function testPreservesGeometricErrors(string $input, array $coordinates): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $prefix = $strategy instanceof EwktDecoderStrategy ? 'SRID=4326;' : '';
            $geometry = (new Decoder($strategy))->decode($prefix.$input);
            self::assertInstanceOf(ImmutableSpatialInterface::class, $geometry);
            self::assertSame($coordinates, $geometry->toArray());
            self::assertSame('' === $prefix ? 0 : 4326, $geometry->getSrid());
            self::assertSame(SpatialModelEnum::GEOMETRY, $geometry->getFamily());
        }
    }

    /**
     * Surface topology is separate from EWKT decoding.
     *
     * @param string                  $input       surface representation to decode
     * @param array<array-key, mixed> $coordinates exact original coordinates
     */
    #[DataProvider('surfaceErrors')]
    public function testPreservesSurfaceErrors(string $input, array $coordinates): void
    {
        $geometry = (new EwktDecoderStrategy())->decode('SRID=4326;'.$input);
        self::assertSame($coordinates, $geometry->toArray());
        self::assertSame(4326, $geometry->getSrid());
    }

    /**
     * Inspect immutable output and recursively propagated references.
     *
     * @param ImmutableSpatialInterface $geometry geometry and descendants to inspect
     * @param int                       $srid     expected reference identifier
     */
    private function assertImmutableGraph(ImmutableSpatialInterface $geometry, int $srid): void
    {
        self::assertSame($srid, $geometry->getSrid());
        self::assertSame(SpatialModelEnum::GEOMETRY, $geometry->getFamily());
        $elements = match (true) {
            $geometry instanceof CollectionInterface => $geometry->getElements(),
            $geometry instanceof LineStringInterface => $geometry->getPoints(),
            default => [],
        };
        foreach ($elements as $element) {
            self::assertInstanceOf(ImmutableSpatialInterface::class, $element);
            $this->assertImmutableGraph($element, $srid);
        }
    }
}
