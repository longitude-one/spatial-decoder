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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt;

/**
 * Select the dimensional syntax accepted by the shared text parser.
 *
 * @internal
 */
enum WktDialect
{
    case EWKT;
    case WKT;
}
