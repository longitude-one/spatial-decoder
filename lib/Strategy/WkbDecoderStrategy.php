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
     * @param SpatialBuilder   $builder construction context
     * @param GeometryTypeEnum $type    aggregate geometry type
     * @param array            $members ordered decoded members
     *
     * @phpstan-param list<SpatialInterface> $members
     */
    private function buildCollection(SpatialBuilder $builder, GeometryTypeEnum $type, array $members): SpatialInterface
    {
        return match ($type) {
            GeometryTypeEnum::MULTIPOINT => $builder->createMultiPoint($members),
            GeometryTypeEnum::MULTILINESTRING => $builder->createMultiLineString($members),
            GeometryTypeEnum::MULTIPOLYGON => $builder->createMultiPolygon($members),
            GeometryTypeEnum::COMPOUNDCURVE => $builder->createCompoundCurve($members),
            GeometryTypeEnum::POLYHEDRALSURFACE => $builder->createPolyhedralSurface($members),
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
     * @param GeometryTypeEnum        $type      aggregate geometry type
     */
    private function readCollection(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension, GeometryTypeEnum $type): SpatialInterface
    {
        $count = $reader->readCount(5);
        $component = $type->componentType();
        $expected = GeometryTypeEnum::COMPOUNDCURVE === $type
            ? [GeometryTypeEnum::LINESTRING, GeometryTypeEnum::CIRCULARSTRING]
            : (null === $component ? [] : [$component]);
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
        return CoordinateDimensionEnum::tryFromWkbCode($type - $type % 1000)
            ?? throw new InvalidArgumentException('Unsupported WKB type identifier.');
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
        $geometryType = GeometryTypeEnum::tryFromWkbCode($type % 1000);
        $builder = $this->createBuilder($dimension);

        return match ($geometryType) {
            GeometryTypeEnum::POINT => $this->buildPoint($builder, $dimension, $reader->readCoordinates($dimension)),
            GeometryTypeEnum::LINESTRING => $builder->createLineString($this->readPoints($reader, $builder, $dimension)),
            GeometryTypeEnum::POLYGON,
            GeometryTypeEnum::TRIANGLE => $this->readPolygon($reader, $builder, $dimension, $geometryType),
            GeometryTypeEnum::POLYHEDRALSURFACE => $this->readPolyhedralSurface($reader, $builder, $dimension),
            GeometryTypeEnum::MULTIPOINT,
            GeometryTypeEnum::MULTILINESTRING,
            GeometryTypeEnum::MULTIPOLYGON,
            GeometryTypeEnum::GEOMETRYCOLLECTION,
            GeometryTypeEnum::COMPOUNDCURVE => $this->readCollection($reader, $builder, $dimension, $geometryType),
            GeometryTypeEnum::CIRCULARSTRING => $builder->createCircularString($this->readPoints($reader, $builder, $dimension)),
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
     * Polygon and Triangle rings have no nested geometry headers.
     *
     * @param WkbReader               $reader    current binary cursor
     * @param SpatialBuilder          $builder   construction context for all components
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     * @param GeometryTypeEnum        $type      declared Polygon or Triangle type
     */
    private function readPolygon(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension, GeometryTypeEnum $type): SpatialInterface
    {
        $count = $reader->readCount(4);
        $rings = [];
        for ($index = 0; $index < $count; ++$index) {
            $rings[] = $builder->createLineString($this->readPoints($reader, $builder, $dimension));
        }

        return GeometryTypeEnum::TRIANGLE === $type ? $builder->createTriangle($rings) : $builder->createPolygon($rings);
    }

    /**
     * Reject layouts unavailable in the spatial model, even for EMPTY surfaces.
     *
     * @param WkbReader               $reader    current binary cursor
     * @param SpatialBuilder          $builder   construction context for all patches
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     */
    private function readPolyhedralSurface(WkbReader $reader, SpatialBuilder $builder, CoordinateDimensionEnum $dimension): SpatialInterface
    {
        if (!$dimension->hasZ()) {
            throw new InvalidArgumentException('WKB PolyhedralSurface requires an XYZ or XYZM coordinate dimension.');
        }

        return $this->readCollection($reader, $builder, $dimension, GeometryTypeEnum::POLYHEDRALSURFACE);
    }
}
