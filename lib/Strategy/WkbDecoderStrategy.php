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

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Wkb\WkbReader;
use LongitudeOne\SpatialTypes\Builder\SpatialBuilder;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Decode raw WKB geometries and nested collections with SQL/MM dimensions. */
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
     * Decode one complete geometry without retaining per-call state.
     *
     * @param string $data raw binary WKB, without a vendor prefix
     */
    public function decode(string $data): SpatialInterface
    {
        $reader = new WkbReader($data);
        $spatial = $this->readGeometry($reader);
        $reader->finish();

        return $spatial;
    }

    /**
     * Keep the declared collection or compound-curve type after reading its members.
     *
     * @param SpatialBuilder $builder construction context
     * @param int            $type    aggregate base identifier
     * @param array          $members ordered decoded members
     *
     * @phpstan-param list<SpatialInterface> $members
     */
    private function buildCollection(SpatialBuilder $builder, int $type, array $members): SpatialInterface
    {
        return match ($type) {
            4 => $builder->createMultiPoint($members),
            5 => $builder->createMultiLineString($members),
            6 => $builder->createMultiPolygon($members),
            9 => $builder->createCompoundCurve($members),
            default => $builder->createCollection($members),
        };
    }

    /**
     * Preserve the declared dimension for populated and EMPTY points.
     *
     * @param SpatialBuilder          $builder     locally configured construction context
     * @param CoordinateDimensionEnum $dimension   declared coordinate layout
     * @param array                   $coordinates ordered ordinates, or none for EMPTY
     *
     * @phpstan-param list<float> $coordinates
     */
    private function buildPoint(SpatialBuilder $builder, CoordinateDimensionEnum $dimension, array $coordinates): PointInterface
    {
        return $builder->point(
            $coordinates[0] ?? null,
            $coordinates[1] ?? null,
            $dimension->hasZ() ? ($coordinates[2] ?? null) : null,
            $dimension->hasM() ? ($coordinates[$dimension->mIndex()] ?? null) : null,
        );
    }

    /**
     * Construct through a local builder with WKT-compatible defaults.
     *
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     */
    private function createBuilder(CoordinateDimensionEnum $dimension): SpatialBuilder
    {
        $mode = SpatialModelEnum::GEOGRAPHY === $this->options['mode'] ? SpatialModelEnum::GEOGRAPHY : SpatialModelEnum::GEOMETRY;
        $builder = SpatialBuilder::create()->family($mode)->dimension($dimension);

        return $this->options['mutable'] ? $builder->mutable() : $builder->immutable();
    }

    /**
     * Construct only after every member passes structural and contract checks.
     *
     * @param WkbReader               $reader    current binary cursor
     * @param SpatialBuilder          $builder   parent construction context
     * @param CoordinateDimensionEnum $dimension declared parent layout
     * @param int                     $type      aggregate base identifier
     */
    private function readCollection(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension, int $type): SpatialInterface
    {
        $count = $reader->readCount(5);
        $expected = match ($type) {
            4 => [GeometryTypeEnum::POINT],
            5 => [GeometryTypeEnum::LINESTRING],
            6 => [GeometryTypeEnum::POLYGON],
            9 => [GeometryTypeEnum::LINESTRING, GeometryTypeEnum::CIRCULARSTRING],
            default => [],
        };
        $members = [];
        for ($index = 0; $index < $count; ++$index) {
            $member = $this->readGeometry($reader);
            if ([] !== $expected && !\in_array($member->getType(), $expected, true)) {
                throw new InvalidArgumentException('Incompatible WKB collection member type.');
            }
            if ($dimension !== $member->getDimension()) {
                throw new InvalidArgumentException('WKB collection members must use the declared coordinate dimension.');
            }
            $members[] = $member;
        }

        return $this->buildCollection($builder, $type, $members);
    }

    /**
     * Resolve only explicitly supported SQL/MM identifiers.
     *
     * @param int $type complete type identifier, including its dimensional offset
     */
    private function readDimension(int $type): CoordinateDimensionEnum
    {
        return match (intdiv($type, 1000)) {
            0 => CoordinateDimensionEnum::XY,
            1 => CoordinateDimensionEnum::XYZ,
            2 => CoordinateDimensionEnum::XYM,
            3 => CoordinateDimensionEnum::XYZM,
            default => throw new InvalidArgumentException('Unsupported WKB type identifier.'),
        };
    }

    /**
     * Dispatch root and member headers through the same supported type set.
     *
     * @param WkbReader $reader current binary cursor
     */
    private function readGeometry(WkbReader $reader): SpatialInterface
    {
        $type = $reader->readHeader();
        $dimension = $this->readDimension($type);
        $builder = $this->createBuilder($dimension);

        return match ($type % 1000) {
            1 => $this->buildPoint($builder, $dimension, $reader->readCoordinates($dimension)),
            2 => $builder->createLineString($this->readPoints($reader, $builder, $dimension)),
            3 => $this->readPolygon($reader, $builder, $dimension),
            4, 5, 6, 7, 9 => $this->readCollection($reader, $builder, $dimension, $type % 1000),
            8 => $builder->createCircularString($this->readPoints($reader, $builder, $dimension)),
            default => throw new InvalidArgumentException('Unsupported WKB type identifier.'),
        };
    }

    /**
     * Read a bounded point sequence without repairing its geometry.
     *
     * @param WkbReader               $reader    current binary cursor
     * @param SpatialBuilder          $builder   construction context for all components
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     *
     * @return list<PointInterface>
     */
    private function readPoints(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension): array
    {
        $count = $reader->readCount(8 * $dimension->coordinateDimension());
        $points = [];
        for ($index = 0; $index < $count; ++$index) {
            $points[] = $this->buildPoint($builder, $dimension, $reader->readCoordinates($dimension));
        }

        return $points;
    }

    /**
     * Rings contain counters and coordinates, without nested geometry headers.
     *
     * @param WkbReader               $reader    current binary cursor
     * @param SpatialBuilder          $builder   construction context for all components
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     */
    private function readPolygon(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension): SpatialInterface
    {
        $count = $reader->readCount(4);
        $rings = [];
        for ($index = 0; $index < $count; ++$index) {
            $rings[] = $builder->createLineString($this->readPoints($reader, $builder, $dimension));
        }

        return $builder->createPolygon($rings);
    }
}
