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

use LongitudeOne\Core\Enum\ByteOrderEnum;
use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Builder\SpatialBuilder;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Decode raw WKB Points with SQL/MM dimensions and encoder-compatible EMPTY values. */
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
        if (5 > \strlen($data)) {
            throw new InvalidArgumentException('Incomplete WKB Point header.');
        }
        $byteOrder = $this->readByteOrder($data);
        $integerFormat = ByteOrderEnum::BIG_ENDIAN === $byteOrder ? 'Ntype' : 'Vtype';
        $dimension = $this->readDimension($integerFormat, $data);
        if (5 + 8 * $dimension->coordinateDimension() !== \strlen($data)) {
            throw new InvalidArgumentException('WKB Point length does not match its declared dimension.');
        }
        $format = ByteOrderEnum::BIG_ENDIAN === $byteOrder ? 'E*' : 'e*';

        return $this->buildPoint($dimension, $this->readCoordinates($format, $data));
    }

    /**
     * Construct through a local builder with WKT-compatible defaults.
     *
     * @param CoordinateDimensionEnum $dimension   declared coordinate layout
     * @param array                   $coordinates ordered ordinates, or none for EMPTY
     *
     * @phpstan-param list<float> $coordinates
     */
    private function buildPoint(CoordinateDimensionEnum $dimension, array $coordinates): SpatialInterface
    {
        $mode = SpatialModelEnum::GEOGRAPHY === $this->options['mode'] ? SpatialModelEnum::GEOGRAPHY : SpatialModelEnum::GEOMETRY;
        $builder = SpatialBuilder::create()->family($mode)->dimension($dimension);
        $builder = $this->options['mutable'] ? $builder->mutable() : $builder->immutable();

        return $builder->point(
            $coordinates[0] ?? null,
            $coordinates[1] ?? null,
            $dimension->hasZ() ? ($coordinates[2] ?? null) : null,
            $dimension->hasM() ? ($coordinates[$dimension->mIndex()] ?? null) : null,
        );
    }

    /**
     * Validate the marker before unpacking any multibyte field.
     *
     * @param string $data input with a complete header
     */
    private function readByteOrder(string $data): ByteOrderEnum
    {
        return ByteOrderEnum::tryFrom(\ord($data[0]))
            ?? throw new InvalidArgumentException('Invalid WKB byte-order marker.');
    }

    /**
     * Recognize EMPTY only when every declared ordinate is NaN.
     *
     * @param string $format endian-specific double format
     * @param string $data   complete Point bytes
     *
     * @return list<float>
     */
    private function readCoordinates(string $format, string $data): array
    {
        $coordinates = array_values(unpack($format, $data, 5) ?: []);
        $values = [];
        $nanCount = 0;
        foreach ($coordinates as $coordinate) {
            if (!\is_float($coordinate)) {
                throw new InvalidArgumentException('Invalid WKB Point ordinate.');
            }
            $values[] = $coordinate;
            $nanCount += is_nan($coordinate) ? 1 : 0;
        }
        if (0 !== $nanCount && \count($values) !== $nanCount) {
            throw new InvalidArgumentException('Mixed NaN and non-NaN WKB Point ordinates.');
        }

        return 0 === $nanCount ? $values : [];
    }

    /**
     * Resolve only explicitly supported SQL/MM Point identifiers.
     *
     * @param string $format integer byte order
     * @param string $data   complete header bytes
     */
    private function readDimension(string $format, string $data): CoordinateDimensionEnum
    {
        $header = unpack($format, $data, 1) ?: [];

        return match ($header['type'] ?? null) {
            1 => CoordinateDimensionEnum::XY,
            1001 => CoordinateDimensionEnum::XYZ,
            2001 => CoordinateDimensionEnum::XYM,
            3001 => CoordinateDimensionEnum::XYZM,
            default => throw new InvalidArgumentException('Unsupported WKB Point type identifier.'),
        };
    }
}
