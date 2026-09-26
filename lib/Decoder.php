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

use LongitudeOne\SpatialDecoder\Strategy\ArrayDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\DecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\ObjectDecoderStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\StringDecoderStrategyInterface;
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
     * @param string|array<string, mixed>|object $data The data to decode into a spatial interface
     *
     * @return SpatialInterface the decoded spatial interface in the format specified by the strategy
     */
    public function decode(string|array|object $data): SpatialInterface
    {
        if (\is_string($data) && $this->strategy instanceof StringDecoderStrategyInterface) {
            return $this->strategy->decode($data);
        }

        if (\is_array($data) && $this->strategy instanceof ArrayDecoderStrategyInterface) {
            return $this->strategy->decode($data);
        }

        if (\is_object($data) && $this->strategy instanceof ObjectDecoderStrategyInterface) {
            return $this->strategy->decode($data);
        }

        throw new Exception\InvalidArgumentException('The configured strategy does not support the supplied input type.');
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
