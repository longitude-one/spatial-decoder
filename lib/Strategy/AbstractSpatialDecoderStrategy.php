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

use LongitudeOne\Core\Enum\SpatialModelEnum;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Output configuration shared by the built-in spatial strategies.
 *
 * @internal
 */
abstract class AbstractSpatialDecoderStrategy extends AbstractConfigurableStrategy
{
    protected readonly SpatialModelEnum $mode;

    protected readonly bool $mutable;

    /** @param array<string, mixed> $options decoder options */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        /** @var array{mode: SpatialModelEnum, mutable: bool} $resolved */
        $resolved = $this->options;
        $this->mode = $resolved['mode'];
        $this->mutable = $resolved['mutable'];
    }

    /**
     * Declare the representation used for the complete decoded graph.
     *
     * @param OptionsResolver $resolver the local options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['mode' => SpatialModelEnum::GEOMETRY, 'mutable' => false]);
        $resolver->setAllowedTypes('mode', SpatialModelEnum::class);
        $resolver->setAllowedTypes('mutable', 'bool');
    }
}
