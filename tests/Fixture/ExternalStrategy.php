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

namespace LongitudeOne\SpatialDecoder\Tests\Fixture;

use LongitudeOne\SpatialDecoder\Strategy\ConfigurableStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\StringDecoderStrategyInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** An application-owned decoding strategy without base-class inheritance. */
final class ExternalStrategy implements ConfigurableStrategyInterface, StringDecoderStrategyInterface
{
    /** @var array<string, mixed> Resolved options. */
    private readonly array $options;

    /** @param array<string, mixed> $options application configuration */
    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    /** @param OptionsResolver $resolver local configuration resolver */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('srid', 0);
        $resolver->setAllowedTypes('srid', 'int');
        $resolver->setAllowedValues('srid', static fn (int $value): bool => $value >= 0);
    }

    /** @param string $data input WKT with an application-declared reference */
    public function decode(string $data): SpatialInterface
    {
        return new EwktDecoderStrategy()->decode(\sprintf('SRID=%d;%s', $this->options['srid'], $data));
    }
}
