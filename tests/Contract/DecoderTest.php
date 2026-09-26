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
use LongitudeOne\SpatialDecoder\Strategy\ArrayDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\DecoderStrategyInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
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
        $input = ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);
        $decoder = new Decoder($strategy);

        $result = $decoder->decode($input);

        self::assertSame($decodedSpatial, $result);
    }

    /**
     * This test validates the public contract of decoder class.
     */
    public function testDecodeApiViaSetter(): void
    {
        $input = ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $initialStrategy = $this->createStub(DecoderStrategyInterface::class);
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);
        $decoder = new Decoder($initialStrategy);
        self::assertSame($initialStrategy, $decoder->getStrategy());

        $decoder->setStrategy($strategy);
        self::assertSame($strategy, $decoder->getStrategy());

        $result = $decoder->decode($input);

        self::assertSame($decodedSpatial, $result);
    }
}
