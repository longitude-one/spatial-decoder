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

use LongitudeOne\SpatialDecoder\Decoder;
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
     * Non useful unit test to replace when a proper strategy will be implemented.
     */
    public function testDecodeDelegatesInputToStrategyAndReturnsDecodedSpatial(): void
    {
        $input = ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = $this->createMock(DecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);
        $decoder = new Decoder($strategy);

        $result = $decoder->decode($input);

        self::assertSame($decodedSpatial, $result);
    }
}
