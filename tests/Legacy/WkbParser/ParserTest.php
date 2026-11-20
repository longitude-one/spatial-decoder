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

namespace LongitudeOne\SpatialDecoder\Tests\Legacy\WkbParser;

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException as FixtureException;

/**
 * Historical fixtures exercise the public API without loading the legacy parser.
 * Adapted from WKB-Parser, copyright LongitudeOne, Alexandre Tranchant and
 * Derek J. Lambert, 2024-2026. See the original MIT license in this directory.
 *
 * @phpstan-type Geometry array{srid?: ?int, type: string, value: array<mixed>, dimension?: ?string}
 * @phpstan-type GoodCase array{value: string, expected: Geometry, disposition: string}
 * @phpstan-type BadCase array{value: string, exception: string, message: string}
 */
final class ParserTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function badBinaryData(): iterable
    {
        foreach (self::fixtures()['bad'] as $name => $case) {
            yield $name => [$case['value']];
        }
    }

    /** @return iterable<string, array{string, Geometry}> */
    public static function goodBinaryData(): iterable
    {
        foreach (self::fixtures()['good'] as $name => $case) {
            if ('preserved' === $case['disposition']) {
                yield $name => [$case['value'], $case['expected']];
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function legacyHexData(): iterable
    {
        foreach (self::fixtures()['good'] as $name => $case) {
            yield $name => [$case['value']];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedBinaryData(): iterable
    {
        foreach (self::fixtures()['good'] as $name => $case) {
            if ('preserved' !== $case['disposition']) {
                yield $name => [$case['value']];
            }
        }
    }

    /**
     * Binary ordinates are doubles; legacy integer literals express the same values.
     *
     * @param array<mixed> $expected historical coordinate tree
     * @param array<mixed> $actual   decoded coordinate tree
     */
    private static function assertCoordinates(array $expected, array $actual): void
    {
        self::assertSame(array_keys($expected), array_keys($actual));
        foreach ($expected as $index => $coordinate) {
            if (\is_array($coordinate)) {
                self::assertIsArray($actual[$index]);
                self::assertCoordinates($coordinate, $actual[$index]);

                continue;
            }
            self::assertTrue(\is_int($coordinate) || \is_float($coordinate));
            self::assertSame((float) $coordinate, $actual[$index]);
        }
    }

    /**
     * Retain heterogeneous member tags as assertions on actual child objects.
     *
     * @param array            $expected legacy result or tagged child
     * @param SpatialInterface $actual   decoded spatial object
     *
     * @phpstan-param Geometry $expected
     */
    private static function assertGeometry(array $expected, SpatialInterface $actual): void
    {
        self::assertSame($expected['type'], strtoupper($actual->getType()->value));
        self::assertSame($expected['srid'] ?? 0, $actual->getSrid());
        self::assertSame($expected['dimension'] ?? '', $actual->getDimension()->wktModifier());
        if (\in_array($expected['type'], ['GEOMETRYCOLLECTION', 'COMPOUNDCURVE'], true)) {
            self::assertTrue($actual instanceof CollectionInterface || $actual instanceof CompoundCurveInterface);
            self::assertCount(\count($expected['value']), $actual->getElements());
            foreach ($expected['value'] as $index => $member) {
                self::assertIsArray($member);
                self::assertArrayHasKey('type', $member);
                self::assertIsString($member['type']);
                self::assertArrayHasKey('value', $member);
                self::assertIsArray($member['value']);
                self::assertGeometry(['type' => $member['type'], 'value' => $member['value'], 'dimension' => $expected['dimension'] ?? null], $actual->getElements()[$index]);
            }

            return;
        }
        self::assertCoordinates($expected['value'], $actual->toArray());
    }

    /** @return array{good: array<string, GoodCase>, bad: array<string, BadCase>} */
    private static function fixtures(): array
    {
        $contents = file_get_contents(__DIR__.'/fixtures.json');
        if (false === $contents) {
            throw new FixtureException('Cannot load the historical WKB fixtures.');
        }
        /** @var array{good: array<string, GoodCase>, bad: array<string, BadCase>} $fixtures */
        $fixtures = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return $fixtures;
    }

    /**
     * Constructor input is replaced by an explicit public decode call.
     *
     * @param string $hex historical malformed bytes
     */
    #[DataProvider('badBinaryData')]
    public function testBadBinaryData(string $hex): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Decoder(new WkbDecoderStrategy())->decode(pack('H*', $hex));
    }

    /**
     * Rejection on an already used decoder retains the historical reuse scenario.
     *
     * @param string $hex historical malformed bytes
     */
    #[DataProvider('badBinaryData')]
    public function testBadBinaryDataWithPreparedParser(string $hex): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        $decoder->decode(pack('H*', '0101000000000000000000f03f0000000000000040'));
        $this->expectException(InvalidArgumentException::class);
        $decoder->decode(pack('H*', $hex));
    }

    /**
     * Keep the original six input forms identifiable; hex text is now rejected.
     *
     * @param string $hex historical fixture
     */
    #[DataProvider('legacyHexData')]
    public function testLegacyHexForms(string $hex): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        foreach (['', 'x', 'X', '0x', '0X'] as $prefix) {
            try {
                $decoder->decode($prefix.$hex);
                self::fail('WKB requires raw binary input.');
            } catch (InvalidArgumentException) {
                self::assertSame([1.0, 2.0], $decoder->decode(pack('H*', '0101000000000000000000f03f0000000000000040'))->toArray());
            }
        }
    }

    /**
     * Preserve coordinates, member types, layout, and absence of an embedded SRID.
     *
     * @param string $hex      historical binary fixture represented as hex
     * @param array  $expected historical parser result
     *
     * @phpstan-param Geometry $expected
     */
    #[DataProvider('goodBinaryData')]
    public function testParserBinary(string $hex, array $expected): void
    {
        $actual = new Decoder(new WkbDecoderStrategy())->decode(pack('H*', $hex));
        self::assertGeometry($expected, $actual);
    }

    /** Reuse one public decoder across every compatible historical binary input. */
    public function testReusedParser(): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        foreach (self::goodBinaryData() as [$hex, $expected]) {
            self::assertGeometry($expected, $decoder->decode(pack('H*', $hex)));
        }
    }

    /**
     * EWKB flags and unsupported types remain outside the WKB contract.
     *
     * @param string $hex unchanged historical success fixture
     */
    #[DataProvider('unsupportedBinaryData')]
    public function testUnsupportedLegacyBinary(string $hex): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Decoder(new WkbDecoderStrategy())->decode(pack('H*', $hex));
    }
}
