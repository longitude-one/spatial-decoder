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

use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Binary\BinaryGeometryDecoder;
use LongitudeOne\SpatialDecoder\Strategy\MySQL\MySQLDialect;
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/** Decode raw MySQL spatial column values. */
final class MySQLDecoderStrategy extends AbstractSpatialDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode one complete geometry without retaining per-call state.
     *
     * @param string $data raw binary geometry with its MySQL SRID prefix
     */
    public function decode(string $data): SpatialInterface
    {
        if (4 > \strlen($data)) {
            throw new InvalidArgumentException('Incomplete MySQL SRID prefix.');
        }
        $srid = unpack('Vsrid', $data)['srid'] ?? null;
        if (!\is_int($srid)) {
            throw new InvalidArgumentException('Invalid MySQL SRID prefix.');
        }

        return new BinaryGeometryDecoder(new MySQLDialect(), $this->mode, $this->mutable)->decode(WkbReader::forMysql(substr($data, 4)), $srid);
    }
}
