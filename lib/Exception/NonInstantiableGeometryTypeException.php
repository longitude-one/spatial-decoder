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
