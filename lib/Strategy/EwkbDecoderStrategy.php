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

use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryGeometryDecoder;
use LongitudeOne\SpatialDecoder\Strategy\Ewkb\EwkbDialect;
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/** Decode raw dimensioned EWKB graphs with spatial reference metadata at every header. */
final class EwkbDecoderStrategy extends AbstractSpatialDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode one complete geometry without retaining per-call state.
     *
     * @param string $data raw binary EWKB, without a textual or vendor wrapper
     */
    public function decode(string $data): SpatialInterface
    {
        return new BinaryGeometryDecoder(new EwkbDialect(), $this->mode, $this->mutable)->decode(new WkbReader($data));
    }
}
