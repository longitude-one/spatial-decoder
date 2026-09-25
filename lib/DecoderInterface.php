<?php
/**
 * This file is part of the spatial-decoder project.
 *
 * PHP 8.4 | 8.5
 *
 * Copyright Alexandre Tranchant <alexandre.tranchant@gmail.com> 2026
 * Copyright Longitude One 2026
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     * @param mixed $data the data to decode into a spatial interface
     *
     * @return SpatialInterface the decoded spatial interface
     */
    public function decode(mixed $data): SpatialInterface;

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
