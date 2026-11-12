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

namespace LongitudeOne\SpatialDecoder;

use LongitudeOne\SpatialDecoder\Strategy\DecoderStrategyInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

interface DecoderInterface
{
    /**
     * Decoder constructor.
     *
     * @param DecoderStrategyInterface $strategy the strategy to use
     */
    public function __construct(DecoderStrategyInterface $strategy);

    /**
     * Decode data into a spatial interface from the strategy's input format.
     *
     * @param string|array<string, mixed>|object $data the data to decode into a spatial interface
     *
     * @return SpatialInterface the decoded spatial interface
     */
    public function decode(string|array|object $data): SpatialInterface;

    /**
     * Get the current strategy.
     *
     * @return DecoderStrategyInterface the strategy to use
     */
    public function getStrategy(): DecoderStrategyInterface;

    /**
     * Set the strategy to use.
     *
     * @param DecoderStrategyInterface $strategy the strategy to use
     *
     * @return self the current instance
     */
    public function setStrategy(DecoderStrategyInterface $strategy): self;
}
