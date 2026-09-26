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

namespace LongitudeOne\SpatialDecoder\Tests\Contract;

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Decoder
 */
class DecoderTest extends TestCase
{
    /**
     * This test validates the public contract of the decoder.
     */
    public function testDecodeApiViaConstructor(): void
    {
        $decoder = new Decoder(new WktDecoderStrategy());

        $result = $decoder->decode('POINT (1 2)');

        self::assertInstanceOf(PointInterface::class, $result);
        self::assertSame([1, 2], $result->toArray());
    }

    /**
     * This test validates the public contract of decoder class.
     */
    public function testDecodeApiViaSetter(): void
    {
        $initialStrategy = new WktDecoderStrategy();
        $strategy = new WktDecoderStrategy();
        $decoder = new Decoder($initialStrategy);
        self::assertSame($initialStrategy, $decoder->getStrategy());

        self::assertSame($decoder, $decoder->setStrategy($strategy));
        self::assertSame($strategy, $decoder->getStrategy());

        $result = $decoder->decode('POINT ZM (1 2 3 4)');

        self::assertInstanceOf(PointInterface::class, $result);
        self::assertSame([1, 2, 3, 4], $result->toArray());
    }
}
