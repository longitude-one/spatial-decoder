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

namespace LongitudeOne\SpatialDecoder\Exception;

/**
 * Thrown when a validated WKT coordinate lacks an ordinate required by its dimension.
 */
final class LogicException extends \LogicException implements DecoderExceptionInterface
{
}
