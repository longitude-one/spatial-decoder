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
 * A recognized abstract spatial type cannot be instantiated.
 *
 * The type classification is supplied by GeometryTypeEnum::isInstantiable().
 */
final class NonInstantiableGeometryTypeException extends \InvalidArgumentException implements DecoderExceptionInterface
{
    /**
     * Retain the recognized type without imposing format-specific validation.
     *
     * @param GeometryTypeEnum $spatialType the recognized spatial type
     */
    public function __construct(private readonly GeometryTypeEnum $spatialType)
    {
        parent::__construct(\sprintf('Geometry type "%s" is recognized but is not instantiable.', $spatialType->value));
    }

    /**
     * Return the recognized spatial type as supplied by the decoding strategy.
     */
    public function getSpatialType(): GeometryTypeEnum
    {
        return $this->spatialType;
    }
}
