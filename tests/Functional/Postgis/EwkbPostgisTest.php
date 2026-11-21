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

namespace LongitudeOne\SpatialDecoder\Tests\Functional\Postgis;

use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwkbDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Offline PostGIS bytes and independently authored complete expected graphs. */
final class EwkbPostgisTest extends TestCase
{
    /** @return iterable<string, array{array<mixed>, string}> */
    public static function fixtures(): iterable
    {
        $contents = file_get_contents(__DIR__.'/Ewkb/corpus.json');
        self::assertIsString($contents);
        $corpus = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($corpus);
        self::assertIsString($corpus['postgis_full_version']);
        self::assertNotSame('', $corpus['postgis_full_version']);
        self::assertIsArray($corpus['fixtures']);
        self::assertCount(240, $corpus['fixtures']);
        foreach ($corpus['fixtures'] as $fixture) {
            self::assertIsArray($fixture);
            self::assertIsString($fixture['id']);
            foreach (['ndr', 'xdr'] as $order) {
                yield $fixture['id'].'-'.$order => [$fixture, $order];
            }
        }
    }

    /**
     * Every canonical byte order preserves all nodes in every output mode.
     *
     * @param array<mixed> $fixture PostGIS bytes and independently authored expectations
     * @param string       $order   selected byte order
     */
    #[DataProvider('fixtures')]
    public function testCanonicalGraphs(array $fixture, string $order): void
    {
        $binary = $this->bytes($fixture, 'ewkb_'.$order);
        if (false === $fixture['supported']) {
            $this->expectException(InvalidArgumentException::class);
            new Decoder(new EwkbDecoderStrategy())->decode($binary);

            return;
        }
        self::assertIsArray($fixture['expected']);
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $actual = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode($binary);
                $this->assertGraph($actual, $fixture['expected'], $mode, $mutable);
            }
        }
    }

    /** Freeze the complete family/layout/reference/state matrix, including unsupported cases. */
    public function testCorpusCoverage(): void
    {
        $expected = [];
        $types = ['POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON',
            'GEOMETRYCOLLECTION', 'CIRCULARSTRING', 'COMPOUNDCURVE', 'TRIANGLE', 'POLYHEDRALSURFACE',
            'CURVEPOLYGON', 'MULTICURVE', 'MULTISURFACE', 'TIN'];
        foreach ($types as $type) {
            foreach (['XY', 'XYZ', 'XYM', 'XYZM'] as $dimension) {
                foreach ([0, 4326] as $srid) {
                    foreach (['empty', 'populated'] as $state) {
                        foreach (['ndr', 'xdr'] as $order) {
                            $expected[] = implode('-', [$type, $dimension, $srid, $state, $order]);
                        }
                    }
                }
            }
        }
        $actual = array_keys(iterator_to_array(self::fixtures()));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
    }

    /**
     * SQL/MM offsets and EWKB flags carry equivalent semantics; WKB rejects extensions.
     *
     * @param array<mixed> $fixture paired outputs from ST_AsBinary and ST_AsEWKB
     * @param string       $order   selected byte order
     */
    #[DataProvider('fixtures')]
    public function testWkbBoundary(array $fixture, string $order): void
    {
        if (false === $fixture['supported']) {
            $this->expectException(InvalidArgumentException::class);
            new WkbDecoderStrategy()->decode($this->bytes($fixture, 'wkb_'.$order));

            return;
        }
        $wkb = $this->bytes($fixture, 'wkb_'.$order);
        $ewkb = $this->bytes($fixture, 'ewkb_'.$order);
        $standard = new WkbDecoderStrategy()->decode($wkb);
        $extended = new EwkbDecoderStrategy()->decode($ewkb);
        self::assertSame($standard->toArray(), $extended->toArray());
        // An EWKB strategy also accepts supported ordinary WKB, without inventing SRID.
        self::assertEquals($standard, new EwkbDecoderStrategy()->decode($wkb));
        if (0 === $fixture['srid']) {
            self::assertEquals($standard, $extended);
        }
        if ($wkb !== $ewkb) {
            try {
                new WkbDecoderStrategy()->decode($ewkb);
                self::fail('WKB must reject EWKB flags at any header.');
            } catch (DecoderExceptionInterface $exception) {
                self::assertInstanceOf(InvalidArgumentException::class, $exception);
                self::assertStringContainsString('WKB', $exception->getMessage());
            }
        }
    }

    /**
     * Check structure, coordinates, EMPTY and metadata at every generated node.
     *
     * @param SpatialInterface $actual   decoded geometry or component
     * @param array<mixed>     $expected independent expected node
     * @param SpatialModelEnum $mode     output family
     * @param bool             $mutable  output mutability
     */
    private function assertGraph(SpatialInterface $actual, array $expected, SpatialModelEnum $mode, bool $mutable): void
    {
        self::assertSame($expected['type'], $actual->getType()->name);
        self::assertSame($expected['dimension'], $actual->getDimension()->name);
        self::assertSame($expected['srid'], $actual->getSrid());
        self::assertSame($mode, $actual->getFamily());
        self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $actual);
        if ($actual instanceof PointInterface) {
            self::assertIsArray($expected['coordinates']);
            $coordinates = [];
            foreach ($expected['coordinates'] as $ordinate) {
                self::assertIsNumeric($ordinate);
                $coordinates[] = (float) $ordinate;
            }
            self::assertSame($coordinates, $actual->toArray());

            return;
        }
        self::assertIsArray($expected['children']);
        self::assertTrue(method_exists($actual, 'getElements'));
        $children = $actual->getElements();
        self::assertIsArray($children);
        self::assertCount(\count($expected['children']), $children);
        foreach ($children as $index => $child) {
            self::assertInstanceOf(SpatialInterface::class, $child);
            $expectedChild = $expected['children'][$index];
            self::assertIsArray($expectedChild);
            $this->assertGraph($child, $expectedChild, $mode, $mutable);
        }
    }

    /**
     * Validate fixture transport without involving an encoder or a database.
     *
     * @param array<mixed> $fixture stored corpus row
     * @param string       $field   representation and byte order
     */
    private function bytes(array $fixture, string $field): string
    {
        self::assertIsString($fixture[$field]);
        self::assertMatchesRegularExpression('/\A(?:[0-9a-f]{2})+\z/', $fixture[$field]);
        $binary = hex2bin($fixture[$field]);
        self::assertIsString($binary);

        return $binary;
    }
}
