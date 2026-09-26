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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Dispatches the next geometry from a WKT token stream.
 *
 * @internal
 */
interface WktGeometryParserDispatcherInterface
{
    /**
     * Parse the next geometry, optionally inheriting a collection dimension.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension collection dimension to apply to unmarked members
     *
     * @return SpatialInterface the decoded geometry
     */
    public function parseNext(?CoordinateDimensionEnum $inheritedDimension): SpatialInterface;
}
