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
 * Parses one geometry body from the shared WKT token stream.
 *
 * @internal
 */
interface WktGeometryParserInterface
{
    /**
     * Parse a geometry, optionally inheriting a collection dimension.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return SpatialInterface the decoded geometry
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): SpatialInterface;
}
