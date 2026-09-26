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

use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

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
     * @return SpatialInterface the decoded spatial data
     */
    public function decode(string $data): SpatialInterface
    {
        return (new WktParserFactory())->create($data)->parse();
    }
}
