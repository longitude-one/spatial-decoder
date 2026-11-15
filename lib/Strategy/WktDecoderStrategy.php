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
use LongitudeOne\SpatialDecoder\Strategy\Wkt\DecodedSpatialBuilder;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Decode supported Well-Known Text representations.
 */
final class WktDecoderStrategy extends AbstractConfigurableStrategy implements StringDecoderStrategyInterface
{
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

    /**
     * Decode a spatial object from WKT text.
     *
     * @param string $data the WKT data to decode
     *
     * @return SpatialInterface the decoded spatial data
     */
    public function decode(string $data): SpatialInterface
    {
        $spatial = new WktParserFactory()->create($data)->parse();

        return DecodedSpatialBuilder::fromOptions($this->options)->build($spatial);
    }
}
