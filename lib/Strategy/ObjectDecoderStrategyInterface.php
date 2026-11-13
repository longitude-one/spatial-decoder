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

use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

interface ObjectDecoderStrategyInterface extends DecoderStrategyInterface
{
    /**
     * Decode spatial data from an object.
     *
     * @param object $data the data to decode
     *
     * @return SpatialInterface the decoded spatial data
     */
    public function decode(object $data): SpatialInterface;
}
