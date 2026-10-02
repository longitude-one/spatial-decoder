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

namespace LongitudeOne\SpatialDecoder\Exception;

use LongitudeOne\Core\Enum\GeometryTypeEnum;

/**
 * A recognized, valid spatial type cannot yet be instantiated by the decoder.
 *
 * This exception does not describe malformed input or an unknown spatial type.
 */
final class NotYetImplementedException extends \RuntimeException implements DecoderExceptionInterface
{
    /**
     * Retain the recognized type without imposing format-specific validation.
     *
     * @param GeometryTypeEnum $spatialType the recognized spatial type
     */
    public function __construct(private readonly GeometryTypeEnum $spatialType)
    {
        parent::__construct(\sprintf('Decoding of spatial type "%s" is not yet implemented.', $spatialType->value));
    }

    /**
     * Return the recognized spatial type as supplied by the decoding strategy.
     */
    public function getSpatialType(): GeometryTypeEnum
    {
        return $this->spatialType;
    }
}
