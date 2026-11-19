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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/** Independent byte fixtures exercise the public binary decoding boundary. */
final class WkbDecoderStrategyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidInputs(): iterable
    {
        $valid = pack('H*', '0101000000000000000000f03f00000000000000c0');
        yield 'hex text' => [bin2hex($valid)];
        yield 'MySQL prefix' => [pack('V', 4326).$valid];
        yield 'trailing byte' => [$valid."\0"];
        yield 'second root' => [$valid.$valid];
        yield 'invalid marker' => ["\2".substr($valid, 1)];
        foreach (range(0, 20) as $length) {
            yield 'truncated '.$length => [substr($valid, 0, $length)];
        }
        foreach ([0, 2, 3, 4, 5, 6, 7, 8, 9, 15, 16, 17, 999, 1000, 1002, 2000, 2002, 3000, 3002, 4001, 0x80000001, 0x40000001, 0x20000001] as $type) {
            yield 'unsupported type '.$type => ["\1".pack('V', $type).substr($valid, 5)];
        }
        yield 'EWKB SRID' => ["\1".pack('V2', 0x20000001, 4326).pack('e*', 1, 2)];
        yield 'NaN X' => ["\1".pack('V', 1).pack('e*', \NAN, 2)];
        yield 'NaN Y' => ["\1".pack('V', 1).pack('e*', 1, \NAN)];
    }

    /** @return iterable<string, array{array<string, mixed>, class-string<\Exception>}> */
    public static function invalidOptions(): iterable
    {
        yield 'mode string' => [['mode' => 'Geography'], InvalidOptionsException::class];
        yield 'mutable integer' => [['mutable' => 1], InvalidOptionsException::class];
        yield 'unknown SRID' => [['srid' => 4326], UndefinedOptionsException::class];
    }

    /** @return iterable<string, array{string}> */
    public static function pointFixtures(): iterable
    {
        yield 'little endian' => ['0101000000000000000000f03f00000000000000c0'];
        yield 'big endian' => ['00000000013ff0000000000000c000000000000000'];
    }

    /**
     * Both integer and double fields follow the input byte order.
     *
     * @param string $hex independently specified Point bytes
     */
    #[DataProvider('pointFixtures')]
    public function testDecodePoint(string $hex): void
    {
        $point = new Decoder(new WkbDecoderStrategy())->decode(pack('H*', $hex));
        self::assertSame([1.0, -2.0], $point->toArray());
        self::assertInstanceOf(ImmutableSpatialInterface::class, $point);
        self::assertSame(SpatialModelEnum::GEOMETRY, $point->getFamily());
        self::assertEquals(new WktDecoderStrategy()->decode('POINT (1 -2)')->getSpatialReference(), $point->getSpatialReference());
    }

    /** Captured from spatial-encoder cfc59cd8792bb24a5bdd6bc29877d3fb6d21ba30, WkbBinaryStrategy. */
    public function testEncoderCompatibility(): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        foreach (['0000000001c0525000000000004048400000000000', '010100000000000000005052c00000000000404840'] as $hex) {
            self::assertSame([-73.25, 48.5], $decoder->decode(pack('H*', $hex))->toArray());
        }
    }

    /**
     * Configuration uses the same strict option contract as WKT.
     *
     * @param array<string, mixed> $options   invalid construction options
     * @param string               $exception expected option exception
     *
     * @phpstan-param class-string<\Exception> $exception
     */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptions(array $options, string $exception): void
    {
        $this->expectException($exception);
        new WkbDecoderStrategy($options);
    }

    /** Every output combination preserves binary ordinates and default SRID. */
    public function testOutputConfiguration(): void
    {
        foreach (SpatialModelEnum::cases() as $mode) {
            foreach ([false, true] as $mutable) {
                $strategy = new WkbDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]);
                $point = new Decoder($strategy)->decode(pack('H*', '0101000000000000000000f03f00000000000000c0'));
                self::assertSame($mode, $point->getFamily());
                self::assertInstanceOf($mutable ? MutableSpatialInterface::class : ImmutableSpatialInterface::class, $point);
                self::assertSame([1.0, -2.0], $point->toArray());
                self::assertSame(0, $point->getSrid());
            }
        }
    }

    /**
     * A rejected input never contaminates the next call on the same instance.
     *
     * @param string $input malformed or unsupported bytes
     */
    #[DataProvider('invalidInputs')]
    public function testRejectionAndReuse(string $input): void
    {
        $decoder = new Decoder(new WkbDecoderStrategy());
        self::assertSame([0.0, 0.0], $decoder->decode("\1".pack('V', 1).pack('e*', 0, 0))->toArray());
        try {
            $decoder->decode($input);
            self::fail('Expected binary input rejection.');
        } catch (InvalidArgumentException) {
            self::assertSame([-3.5, 8.25], $decoder->decode("\0".pack('N', 1).pack('E*', -3.5, 8.25))->toArray());
        }
    }
}
