<?php

/*
 * This file is part of the LongitudeOne Spatial project.
 *
 * Copyright (c) 2026 Alexandre Tranchant <alexandre.tranchant@gmail.com>
 *
 * See LICENSE for copyright and license information.
 *
 */

declare(strict_types=1);

namespace LongitudeOne\SpatialDecoder\Tests\Unit\Strategy;

use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\LineStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolygonInterface;
use LongitudeOne\SpatialTypes\Interfaces\PolyhedralSurfaceInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** All supported types preserve their values across the four representation choices. */
final class ConfiguredRepresentationCorpusTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function geometries(): iterable
    {
        yield 'point' => ['POINT (1 2)', false];
        yield 'empty point with dimension' => ['POINT ZM EMPTY', false];
        yield 'line XYM' => ['LINESTRING M (1 2 7, 3 4 8)', false];
        yield 'empty line' => ['LINESTRING Z EMPTY', false];
        yield 'polygon' => ['POLYGON ((0 0, 1 0, 0 1, 0 0))', false];
        yield 'empty polygon' => ['POLYGON EMPTY', false];
        yield 'multi point including empty member' => ['MULTIPOINT (EMPTY, (1 2))', false];
        yield 'multi line including empty member' => ['MULTILINESTRING (EMPTY, (1 2, 3 4))', false];
        yield 'multi polygon including empty member' => ['MULTIPOLYGON (EMPTY, ((0 0, 1 0, 0 1, 0 0)))', false];
        yield 'circular string' => ['CIRCULARSTRING (0 0, 1 1, 2 0)', false];
        yield 'empty circular string' => ['CIRCULARSTRING EMPTY', false];
        yield 'compound curve' => ['COMPOUNDCURVE ((0 0, 1 0), CIRCULARSTRING (1 0, 2 1, 3 0))', false];
        yield 'empty compound curve' => ['COMPOUNDCURVE EMPTY', false];
        yield 'nested collection' => ['GEOMETRYCOLLECTION ZM (GEOMETRYCOLLECTION (POINT (1 2 3 4)), LINESTRING EMPTY)', false];
        yield 'empty collection' => ['GEOMETRYCOLLECTION M EMPTY', false];
        yield 'EWKT point reference' => ['SRID=4326;POINT (1 2)', true];
        yield 'EWKT collection reference' => ['SRID=4326;GEOMETRYCOLLECTION (POINT (1 2), LINESTRING (3 4, 5 6))', true];
        yield 'EWKT triangle' => ['SRID=4326;TRIANGLE ((0 0, 1 0, 0 1, 0 0))', true];
        yield 'EWKT empty triangle' => ['TRIANGLE ZM EMPTY', true];
        yield 'EWKT surface' => ['SRID=4326;POLYHEDRALSURFACE Z (((0 0 0, 1 0 0, 0 1 0, 0 0 0)))', true];
        yield 'EWKT empty surface' => ['POLYHEDRALSURFACE ZM EMPTY', true];
        yield 'unclosed ring preserved' => ['POLYGON ((0 0, 1 0))', false];
        yield 'disconnected curve preserved' => ['COMPOUNDCURVE ((0 0, 1 0), (2 0, 3 0))', false];
    }

    /**
     * Representation choices must not repair, flatten, redimension or reproject the parsed input.
     *
     * @param string $text     input representation
     * @param bool   $extended whether to use EWKT
     */
    #[DataProvider('geometries')]
    public function testRepresentationChoices(string $text, bool $extended): void
    {
        $source = $extended ? new EwktDecoderStrategy()->decode($text) : new WktDecoderStrategy()->decode($text);
        foreach ([SpatialModelEnum::GEOMETRY, SpatialModelEnum::GEOGRAPHY] as $mode) {
            foreach ([false, true] as $mutable) {
                $options = ['mode' => $mode, 'mutable' => $mutable];
                $result = $extended ? new EwktDecoderStrategy($options)->decode($text) : new WktDecoderStrategy($options)->decode($text);
                self::assertSame($source->toArray(), $result->toArray());
                self::assertSame($source->getType(), $result->getType());
                self::assertSame($source->getDimension(), $result->getDimension());
                $this->assertRepresentation($result, $mode, $mutable, $source->getSrid());
            }
        }
    }

    /**
     * Inspect every level of the returned spatial graph.
     *
     * @param SpatialInterface $spatial spatial object under inspection
     * @param SpatialModelEnum $mode    expected spatial family
     * @param bool             $mutable expected mutability
     * @param int              $srid    expected root and component reference
     */
    private function assertRepresentation(SpatialInterface $spatial, SpatialModelEnum $mode, bool $mutable, int $srid): void
    {
        self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $spatial);
        self::assertSame($mode, $spatial->getFamily());
        self::assertSame($srid, $spatial->getSrid());
        $members = match (true) {
            $spatial instanceof CollectionInterface, $spatial instanceof CompoundCurveInterface, $spatial instanceof PolyhedralSurfaceInterface => $spatial->getElements(),
            $spatial instanceof PolygonInterface => $spatial->getRings(),
            $spatial instanceof LineStringInterface => $spatial->getPoints(),
            default => [],
        };
        foreach ($members as $member) {
            $this->assertRepresentation($member, $mode, $mutable, $srid);
        }
    }
}
