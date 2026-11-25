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
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbDialect;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/** Decode raw WKB geometries and nested collections with SQL/MM dimensions. */
final class WkbDecoderStrategy extends AbstractSpatialDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode one complete geometry without retaining per-call state.
     *
     * @param string $data raw binary WKB, without a vendor prefix
     */
    public function decode(string $data): SpatialInterface
    {
        return new BinaryGeometryDecoder(new WkbDialect(), $this->mode, $this->mutable)->decode($data);
    }
}
