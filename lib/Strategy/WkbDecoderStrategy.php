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
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Builder\SpatialBuilder;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Decode raw WKB non-empty XY Points (OGC SFA 1.2.1, section 8). */
final class WkbDecoderStrategy extends AbstractConfigurableStrategy implements StringDecoderStrategyInterface
{
    /**
     * Select the output family and mutability, as for WKT.
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
     * Decode one complete Point without retaining per-call state.
     *
     * @param string $data raw binary WKB, without a vendor prefix
     */
    public function decode(string $data): SpatialInterface
    {
        if (21 !== \strlen($data)) {
            throw new InvalidArgumentException('Expected exactly 21 bytes for a non-empty WKB XY Point.');
        }
        $format = match (\ord($data[0])) {
            0 => 'Ntype/Ex/Ey',
            1 => 'Vtype/ex/ey',
            default => throw new InvalidArgumentException('Invalid WKB byte-order marker.'),
        };

        return $this->readPoint($format, $data);
    }

    /**
     * Construct through a local builder with WKT-compatible defaults.
     *
     * @param float $coordinateX the X ordinate
     * @param float $coordinateY the Y ordinate
     */
    private function buildPoint(float $coordinateX, float $coordinateY): SpatialInterface
    {
        $mode = SpatialModelEnum::GEOGRAPHY === $this->options['mode'] ? SpatialModelEnum::GEOGRAPHY : SpatialModelEnum::GEOMETRY;
        $builder = SpatialBuilder::create()->family($mode);
        $builder = $this->options['mutable'] ? $builder->mutable() : $builder->immutable();

        return $builder->point($coordinateX, $coordinateY);
    }

    /**
     * Read the supported type and ordered double ordinates.
     *
     * @param string $format the endian-specific unpack format
     * @param string $data   complete Point bytes
     */
    private function readPoint(string $format, string $data): SpatialInterface
    {
        $point = unpack($format, $data, 1) ?: [];
        if (1 !== ($point['type'] ?? null)) {
            throw new InvalidArgumentException('Only WKB XY Point type 1 is currently supported.');
        }
        $coordinateX = $point['x'] ?? null;
        $coordinateY = $point['y'] ?? null;
        if (!\is_float($coordinateX) || !\is_float($coordinateY)) {
            throw new InvalidArgumentException('Invalid WKB Point ordinates.');
        }
        if (is_nan($coordinateX) || is_nan($coordinateY)) {
            throw new InvalidArgumentException('NaN ordinates and EMPTY WKB Points are not yet supported.');
        }

        return $this->buildPoint($coordinateX, $coordinateY);
    }
}
