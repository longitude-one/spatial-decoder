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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt;

use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialTypes\Builder\SpatialBuilder;
use LongitudeOne\SpatialTypes\Interfaces\CollectionInterface;
use LongitudeOne\SpatialTypes\Interfaces\CompoundCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;
use LongitudeOne\SpatialTypes\Interfaces\PointInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Applies one local representation choice to the complete decoded graph.
 *
 * @internal
 */
final readonly class DecodedSpatialBuilder
{
    /**
     * Configure output construction without changing the shared factory mode.
     *
     * @param SpatialModelEnum $mode    spatial family for every member
     * @param bool             $mutable whether every member is mutable
     */
    public function __construct(private SpatialModelEnum $mode, private bool $mutable)
    {
    }

    /**
     * Transfer resolved options to the typed construction context.
     *
     * @param array<string, mixed> $options resolved decoder options
     */
    public static function fromOptions(array $options): self
    {
        $mode = $options['mode'] ?? null;
        $mutable = $options['mutable'] ?? null;
        if (!$mode instanceof SpatialModelEnum || !\is_bool($mutable)) {
            throw new InvalidArgumentException('Expected a spatial model enum and a boolean mutable flag.');
        }

        return new self($mode, $mutable);
    }

    /**
     * Rebuild a decoded graph through the public spatial-types builder.
     *
     * @param SpatialInterface $spatial parsed immutable geometry
     */
    public function build(SpatialInterface $spatial): SpatialInterface
    {
        if (!$this->mutable && $spatial instanceof ImmutableSpatialInterface && $this->mode === $spatial->getFamily()) {
            return $spatial;
        }

        $builder = SpatialBuilder::create()->family($this->mode)->dimension($spatial->getDimension())->srid($spatial->getSpatialReference());
        $builder = $this->mutable ? $builder->mutable() : $builder->immutable();

        if ($spatial instanceof CollectionInterface) {
            return $this->buildCollection($builder, $spatial);
        }
        if ($spatial instanceof CompoundCurveInterface) {
            return $builder->createCompoundCurve(array_map($this->build(...), $spatial->getCurves()));
        }

        if ($spatial instanceof PointInterface) {
            return $builder->point(
                $spatial->getX(),
                $spatial->getY(),
                $spatial->hasZ() ? $spatial->getZ() : null,
                $spatial->hasM() ? $spatial->getM() : null,
            );
        }

        return $this->buildCoordinates($builder, $spatial);
    }

    /**
     * Rebuild members as objects so EMPTY points retain their explicit representation.
     *
     * @param SpatialBuilder      $builder local construction context
     * @param CollectionInterface $spatial parsed collection
     */
    private function buildCollection(SpatialBuilder $builder, CollectionInterface $spatial): SpatialInterface
    {
        $members = array_map($this->build(...), $spatial->getElements());

        return match ($spatial->getType()) {
            GeometryTypeEnum::GEOMETRYCOLLECTION => $builder->createCollection($members),
            GeometryTypeEnum::MULTIPOINT => $builder->createMultiPoint($members),
            GeometryTypeEnum::MULTILINESTRING => $builder->createMultiLineString($members),
            GeometryTypeEnum::MULTIPOLYGON => $builder->createMultiPolygon($members),
            default => throw new InvalidArgumentException('Unsupported decoded collection type.'),
        };
    }

    /**
     * Preserve the parsed type and complete ordinate layout.
     *
     * @param SpatialBuilder   $builder local construction context
     * @param SpatialInterface $spatial parsed geometry
     */
    private function buildCoordinates(SpatialBuilder $builder, SpatialInterface $spatial): SpatialInterface
    {
        $coordinates = $spatial->toArray();

        return match ($spatial->getType()) {
            GeometryTypeEnum::LINESTRING => $builder->createLineString($coordinates),
            GeometryTypeEnum::CIRCULARSTRING => $builder->createCircularString($coordinates),
            GeometryTypeEnum::POLYGON => $builder->createPolygon($coordinates),
            GeometryTypeEnum::TRIANGLE => $builder->createTriangle($coordinates),
            GeometryTypeEnum::POLYHEDRALSURFACE => $builder->createPolyhedralSurface($coordinates),
            default => throw new InvalidArgumentException('Unsupported decoded geometry type.'),
        };
    }
}
