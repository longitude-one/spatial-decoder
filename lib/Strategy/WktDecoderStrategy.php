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

namespace LongitudeOne\SpatialDecoder\Strategy;

use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

/**
 * Decode supported Well-Known Text representations.
 */
final class WktDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode a spatial object from WKT text.
     *
     * @param string $data the WKT data to decode
     *
     * @return ImmutableSpatialInterface the decoded spatial data
     */
    public function decode(string $data): ImmutableSpatialInterface
    {
        return new WktParserFactory()->create($data)->parse();
    }
}
