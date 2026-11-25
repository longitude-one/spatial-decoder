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
use LongitudeOne\SpatialDecoder\Strategy\MySQLDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\MutableSpatialInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify the representation boundary of raw MySQL column values. */
final class MySQLProfileTest extends TestCase
{
    /** @return iterable<string, array{string}> invalid column values */
    public static function invalidInputs(): iterable
    {
        $prefix = pack('V', 4326);
        $collection = pack('CVV', 1, 7, 1);
        $point = pack('CVee', 1, 1, 2.35, 48.86);
        yield 'short prefix' => ["\0\0\0"];
        yield 'trailing bytes' => [$prefix.$point."\0"];
        $invalid = [
            'big endian' => pack('CN', 0, 1).pack('EE', 2.35, 48.86),
            'unknown order' => pack('CV', 2, 1),
            'curve' => pack('CV', 1, 8),
            'dimension' => pack('CV', 1, 1001),
            'EWKB flag' => pack('CV', 1, 0x20000001),
            'empty point' => pack('CVee', 1, 1, \NAN, \NAN),
            'truncated point' => substr($point, 0, -1),
            'impossible count' => pack('CVV', 1, 2, 0xFFFFFFFF),
            'wrong member' => pack('CVV', 1, 5, 1).$point,
        ];
        foreach (range(2, 6) as $type) {
            $invalid['empty type '.$type] = pack('CVV', 1, $type, 0);
        }
        foreach ($invalid as $name => $body) {
            yield $name => [$prefix.$body];
            yield 'nested '.$name => [$prefix.$collection.$body];
        }
    }

    /** Verify configured output using the fixed Paris point from the Encoder corpus. */
    public function testOutputConfiguration(): void
    {
        foreach ([SpatialModelEnum::GEOMETRY, SpatialModelEnum::GEOGRAPHY] as $mode) {
            foreach ([false, true] as $mutable) {
                $decoder = new Decoder(new MySQLDecoderStrategy(['mode' => $mode, 'mutable' => $mutable]));
                foreach ([0, 4326] as $srid) {
                    $result = $decoder->decode(pack('V', $srid).pack('H*', '0101000000cdcccccccccc0240ae47e17a146e4840'));
                    self::assertSame($srid, $result->getSrid());
                    self::assertSame($mode, $result->getFamily());
                    self::assertSame($mutable, $result instanceof MutableSpatialInterface);
                    self::assertSame([2.35, 48.86], $result->toArray());
                }
            }
        }
    }

    /** @param string $data rejected binary value */
    #[DataProvider('invalidInputs')]
    public function testRejectionAndReuse(string $data): void
    {
        $decoder = new Decoder(new MySQLDecoderStrategy());
        try {
            $decoder->decode($data);
            self::fail('Expected invalid MySQL input to be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $decoder->decode(pack('H*', '00000000010700000000000000'))->getSrid());
        }
    }
}
