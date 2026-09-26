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

namespace LongitudeOne\SpatialDecoder\Strategy;

use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

interface ArrayDecoderStrategyInterface extends DecoderStrategyInterface
{
    /**
     * Decode spatial data from an array.
     *
     * @param array<string, mixed> $data the data to decode
     *
     * @return SpatialInterface the decoded spatial data
     */
    public function decode(array $data): SpatialInterface;
}
