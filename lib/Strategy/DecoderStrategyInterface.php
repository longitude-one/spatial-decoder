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

use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Decodes spatial objects from a binary or textual representation.
 */
interface DecoderStrategyInterface
{
    /**
     * Decode data into a spatial object from the strategy's input format.
     *
     * @param string|array<string, mixed>|object $data the data to decode into a spatial object
     *
     * @throws InvalidArgumentException if the data cannot be decoded
     */
    public function decode(string|array|object $data): SpatialInterface;
}
