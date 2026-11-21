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
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\EwkbDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Independent manually specified hexadecimal fixtures, not encoder output. */
final class EwkbDecoderStrategyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidInputs(): iterable
    {
        yield 'hex text' => ['0101000020e6100000000000000000f03f00000000000000c0'];
        yield 'bytea text' => ['\\x0101000020e6100000000000000000f03f00000000000000c0'];
        foreach (['0101000020', '0020000001'] as $header) {
            foreach (['', '00', '0000', '000000'] as $partial) {
                yield $header.$partial => [pack('H*', $header.$partial)];
            }
        }
        yield 'unknown flag' => [pack('H*', '0101000010000000000000f03f00000000000000c0')];
        yield 'trailing byte' => [pack('H*', '0101000020e6100000000000000000f03f00000000000000c000')];
    }

    /** @return iterable<string, array{string, int}> */
    public static function pointFixtures(): iterable
    {
        yield 'LE absent' => ['0101000000000000000000f03f00000000000000c0', 0];
        yield 'BE absent' => ['00000000013ff0000000000000c000000000000000', 0];
        yield 'LE explicit zero' => ['010100002000000000000000000000f03f00000000000000c0', 0];
        yield 'BE explicit zero' => ['0020000001000000003ff0000000000000c000000000000000', 0];
        yield 'LE SRID 4326' => ['0101000020e6100000000000000000f03f00000000000000c0', 4326];
        yield 'BE SRID 4326' => ['0020000001000010e63ff0000000000000c000000000000000', 4326];
        if (8 === \PHP_INT_SIZE) {
            yield 'LE uint32 maximum' => ['0101000020ffffffff000000000000f03f00000000000000c0', 4294967295];
            yield 'BE uint32 maximum' => ['0020000001ffffffff3ff0000000000000c000000000000000', 4294967295];
        }
    }

    /** Defaults remain immutable Geometry, as with the other strategies. */
    public function testDefaults(): void
    {
        $point = new Decoder(new EwkbDecoderStrategy())->decode(pack('H*', '0101000020e6100000000000000000f03f00000000000000c0'));
        self::assertSame(SpatialModelEnum::GEOMETRY, $point->getFamily());
        self::assertInstanceOf(ImmutableSpatialInterface::class, $point);
    }

    /** Shared geometry bodies preserve root metadata for all supported XY families. */
    public function testGeometryFamilies(): void
    {
        $families = [1 => 'POINT', 2 => 'LINESTRING', 3 => 'POLYGON', 4 => 'MULTIPOINT', 5 => 'MULTILINESTRING',
            6 => 'MULTIPOLYGON', 7 => 'GEOMETRYCOLLECTION', 8 => 'CIRCULARSTRING', 9 => 'COMPOUNDCURVE', 17 => 'TRIANGLE'];
        foreach (['01' => ['e6100000', '000000000000f03f00000000000000c0'],
            '00' => ['000010e6', '3ff0000000000000c000000000000000']] as $marker => [$reference, $point]) {
            foreach ($families as $code => $name) {
                foreach ([false, true] as $flag) {
                    $word = str_pad(dechex($code), 8, '0', \STR_PAD_LEFT);
                    if ($flag) {
                        $word = '20'.substr($word, 2);
                    }
                    if ('01' === (string) $marker) {
                        $word = implode('', array_reverse(str_split($word, 2)));
                    }
                    $body = 1 === $code ? $point : '00000000';
                    $hex = $marker.$word.($flag ? $reference : '').$body;
                    $spatial = new Decoder(new EwkbDecoderStrategy())->decode(pack('H*', $hex));
                    self::assertSame($name, $spatial->getType()->name);
                    self::assertSame($flag ? 4326 : 0, $spatial->getSrid());
                }
            }
        }
    }

    /**
     * Preserve coordinates and SRID across every configured output representation.
     *
     * @param string $hex  independently specified bytes
     * @param int    $srid expected spatial reference
     */
    #[DataProvider('pointFixtures')]
    public function testPoint(string $hex, int $srid): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $point = new Decoder(new EwkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]))->decode(pack('H*', $hex));
                self::assertSame([1.0, -2.0], $point->toArray());
                self::assertSame($srid, $point->getSrid());
                self::assertSame($mode, $point->getFamily());
                self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $point);
            }
        }
    }

    /** Populated bodies reuse the shared engine and preserve SRID on generated components. */
    public function testPopulatedBodies(): void
    {
        $point = '000000000000f03f00000000000000c0';
        $bodies = [
            '02000020' => ['01000000'.$point, [[1.0, -2.0]]],
            '08000020' => ['01000000'.$point, [[1.0, -2.0]]],
            '03000020' => ['0100000001000000'.$point, [[[1.0, -2.0]]]],
            '11000020' => ['0100000001000000'.$point, [[[1.0, -2.0]]]],
            '04000020' => ['010000000101000000'.$point, [[1.0, -2.0]]],
            '05000020' => ['01000000010200000001000000'.$point, [[[1.0, -2.0]]]],
            '06000020' => ['0100000001030000000100000001000000'.$point, [[[[1.0, -2.0]]]]],
            '09000020' => ['01000000010200000001000000'.$point, [[[1.0, -2.0]]]],
        ];
        foreach ($bodies as $type => [$body, $expected]) {
            $spatial = new Decoder(new EwkbDecoderStrategy())->decode(pack('H*', '01'.$type.'e6100000'.$body));
            self::assertSame($expected, $spatial->toArray());
            self::assertSame(4326, $spatial->getSrid());
        }
    }

    /**
     * Failed calls do not retain header or reference state on a reused strategy.
     *
     * @param string $input malformed or unsupported representation
     */
    #[DataProvider('invalidInputs')]
    public function testRejectedInputAndReuse(string $input): void
    {
        $decoder = new Decoder(new EwkbDecoderStrategy());
        try {
            $decoder->decode($input);
            self::fail('Expected a decoder exception.');
        } catch (DecoderExceptionInterface $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
        }
        self::assertSame(0, $decoder->decode(pack('H*', '0101000000000000000000f03f00000000000000c0'))->getSrid());
    }
}
