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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\ArrayDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\ObjectDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\StringDecoderStrategyInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Decoder
 */
class DecoderTest extends TestCase
{
    /** Test delegation of array input to the strategy. */
    public function testDecodeDelegatesArrayInputToStrategy(): void
    {
        $input = ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);

        self::assertSame($decodedSpatial, (new Decoder($strategy))->decode($input));
    }

    /** Test delegation of object input to the strategy. */
    public function testDecodeDelegatesObjectInputToStrategy(): void
    {
        $input = (object) ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = $this->createMock(ObjectDecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);

        self::assertSame($decodedSpatial, (new Decoder($strategy))->decode($input));
    }

    /** Test delegation of string input to the strategy. */
    public function testDecodeDelegatesStringInputToStrategy(): void
    {
        $input = 'spatial-data';
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = $this->createMock(StringDecoderStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('decode')
            ->with($input)
            ->willReturn($decodedSpatial);
        $decoder = new Decoder($strategy);

        $result = $decoder->decode($input);

        self::assertSame($decodedSpatial, $result);
    }

    /** Test rejection of an unsupported array before strategy invocation. */
    public function testDecodeRejectsUnsupportedArrayBeforeInvokingStrategy(): void
    {
        $strategy = $this->createMock(StringDecoderStrategyInterface::class);
        $strategy->expects($this->never())->method('decode');

        $this->expectException(InvalidArgumentException::class);

        (new Decoder($strategy))->decode(['encoded' => 'spatial-data']);
    }

    /** Test rejection of an unsupported string before strategy invocation. */
    public function testDecodeRejectsUnsupportedInputBeforeInvokingStrategy(): void
    {
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy->expects($this->never())->method('decode');

        $this->expectException(InvalidArgumentException::class);

        (new Decoder($strategy))->decode('spatial-data');
    }

    /** Test rejection of an unsupported object before strategy invocation. */
    public function testDecodeRejectsUnsupportedObjectBeforeInvokingStrategy(): void
    {
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy->expects($this->never())->method('decode');

        $this->expectException(InvalidArgumentException::class);

        (new Decoder($strategy))->decode((object) ['encoded' => 'spatial-data']);
    }

    /** Test dispatch when the strategy supports multiple input types. */
    public function testDecodeUsesMatchingContractWhenStrategySupportsMultipleInputs(): void
    {
        $input = (object) ['encoded' => 'spatial-data'];
        $decodedSpatial = $this->createStub(SpatialInterface::class);
        $strategy = new class($decodedSpatial) implements ObjectDecoderStrategyInterface, StringDecoderStrategyInterface {
            public string|object|null $receivedInput = null;

            /**
             * Construct a strategy returning the supplied spatial object.
             *
             * @param SpatialInterface $decodedSpatial the spatial object to return
             */
            public function __construct(private SpatialInterface $decodedSpatial)
            {
            }

            /**
             * Return the decoded spatial object for either supported input.
             *
             * @param string|object $data the data to decode
             *
             * @return SpatialInterface the decoded spatial data
             */
            public function decode(string|object $data): SpatialInterface
            {
                $this->receivedInput = $data;

                return $this->decodedSpatial;
            }
        };

        $result = (new Decoder($strategy))->decode($input);

        self::assertSame($input, $strategy->receivedInput);
        self::assertSame($decodedSpatial, $result);
    }
}
