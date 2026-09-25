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

/**
 * Decoder class.
 * This class is the main class of the library.
 */
class Decoder implements DecoderInterface
{
    /**
     * Decoder constructor.
     *
     * @param DecoderStrategyInterface $strategy The decoder strategy to use
     */
    public function __construct(private DecoderStrategyInterface $strategy)
    {
    }

    /**
     * Decode a spatial interface from a format specified by the internal adapter.
     *
     * @param mixed $data The data to decode into a spatial interface
     *
     * @return SpatialInterface the decoded spatial interface in the format specified by the strategy
     */
    public function decode(mixed $data): SpatialInterface
    {
        return $this->strategy->decode($data);
    }

    /**
     * Get the current strategy.
     */
    public function getStrategy(): DecoderStrategyInterface
    {
        return $this->strategy;
    }

    /**
     * Set a new strategy to use.
     *
     * @param DecoderStrategyInterface $strategy the strategy to use
     *
     * @return self the current instance
     */
    public function setStrategy(DecoderStrategyInterface $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }
}
