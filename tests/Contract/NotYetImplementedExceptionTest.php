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

use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface;
use LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException;
use LongitudeOne\SpatialDecoder\Strategy\ArrayDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\StringDecoderStrategyInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException
 */
class NotYetImplementedExceptionTest extends TestCase
{
    /** Test that the exception is also usable by non-string strategies. */
    public function testArrayStrategyCanReportAnImplementationLimitation(): void
    {
        $exception = new NotYetImplementedException(GeometryTypeEnum::CIRCULARSTRING);
        $strategy = $this->createMock(ArrayDecoderStrategyInterface::class);
        $strategy->expects($this->once())->method('decode')->with(['type' => 'RecognizedType'])->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new Decoder($strategy))->decode(['type' => 'RecognizedType']);
    }

    /** Test the format-independent public exception contract. */
    public function testRetainsRecognizedSpatialTypeAndExplainsLimitation(): void
    {
        $exception = new NotYetImplementedException(GeometryTypeEnum::CIRCULARSTRING);

        self::assertContains(DecoderExceptionInterface::class, class_implements($exception));
        self::assertSame(GeometryTypeEnum::CIRCULARSTRING, $exception->getSpatialType());
        self::assertSame('Decoding of spatial type "CircularString" is not yet implemented.', $exception->getMessage());
    }

    /** Test propagation from a string strategy without a format-specific exception. */
    public function testStringStrategyCanReportAnImplementationLimitation(): void
    {
        $exception = new NotYetImplementedException(GeometryTypeEnum::TIN);
        $strategy = $this->createMock(StringDecoderStrategyInterface::class);
        $strategy->expects($this->once())->method('decode')->with('encoded-data')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new Decoder($strategy))->decode('encoded-data');
    }
}
