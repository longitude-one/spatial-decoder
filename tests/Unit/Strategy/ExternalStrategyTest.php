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

use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Tests\Fixture\ExternalStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/** Existing orchestration accepts application-owned configuration without inheritance. */
final class ExternalStrategyTest extends TestCase
{
    /** Default and custom references are resolved before decoding. */
    public function testExternalConfiguration(): void
    {
        self::assertSame(0, new Decoder(new ExternalStrategy())->decode('POINT (1 2)')->getSrid());
        $result = new Decoder(new ExternalStrategy(['srid' => 4326]))->decode('POINT (1 2)');
        self::assertSame(4326, $result->getSrid());
        self::assertSame([1, 2], $result->toArray());
    }

    /** The application rejects unsupported option values before decoding. */
    public function testInvalidReference(): void
    {
        $this->expectException(InvalidOptionsException::class);
        new ExternalStrategy(['srid' => -1]);
    }
}
