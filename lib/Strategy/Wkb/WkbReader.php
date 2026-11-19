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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkb;

use LongitudeOne\Core\Enum\ByteOrderEnum;
use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;

/**
 * Bounds every read against the bytes of one decoding call.
 *
 * @internal
 */
final class WkbReader
{
    private ByteOrderEnum $byteOrder;

    private int $offset = 0;

    /**
     * Keep the cursor local to one decoding call.
     *
     * @param string $data complete input bytes
     */
    public function __construct(private readonly string $data)
    {
    }

    /** Reject additional data after the single root geometry. */
    public function finish(): void
    {
        if ($this->offset !== \strlen($this->data)) {
            throw new InvalidArgumentException('Additional bytes after the WKB geometry.');
        }
    }

    /**
     * Recognize encoder-compatible EMPTY points only when all ordinates are NaN.
     *
     * @param CoordinateDimensionEnum $dimension declared coordinate layout
     *
     * @return list<float>
     */
    public function readCoordinates(CoordinateDimensionEnum $dimension): array
    {
        $bytes = $this->readBytes(8 * $dimension->coordinateDimension());
        $format = ByteOrderEnum::BIG_ENDIAN === $this->byteOrder ? 'E*' : 'e*';
        $coordinates = array_values(unpack($format, $bytes) ?: []);
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
     * Reject impossible counters before entering a loop or allocating components.
     *
     * @param int $minimumSize minimum bytes required for one element
     */
    public function readCount(int $minimumSize): int
    {
        $count = $this->readUnsignedInteger();
        if ($count > intdiv(\strlen($this->data) - $this->offset, $minimumSize)) {
            throw new InvalidArgumentException('WKB counter exceeds the elements readable from the available bytes.');
        }

        return $count;
    }

    /**
     * Begin a root or member geometry using its own byte order.
     *
     * Collection counts must be read before descending into members: there are
     * no parent fields after the members, so the next header selects the order
     * for the next geometry without restoring its parent's order.
     */
    public function readHeader(): int
    {
        if (5 > \strlen($this->data) - $this->offset) {
            throw new InvalidArgumentException('Incomplete WKB header.');
        }
        $this->byteOrder = ByteOrderEnum::tryFrom(\ord($this->readBytes(1)))
            ?? throw new InvalidArgumentException('Invalid WKB byte-order marker.');

        return $this->readUnsignedInteger();
    }

    /** Read a uint32 using the current geometry's byte order. */
    public function readUnsignedInteger(): int
    {
        $format = ByteOrderEnum::BIG_ENDIAN === $this->byteOrder ? 'Nvalue' : 'Vvalue';
        $unpacked = unpack($format, $this->readBytes(4)) ?: [];
        $value = $unpacked['value'] ?? null;
        if (!\is_int($value)) {
            throw new InvalidArgumentException('Invalid WKB unsigned integer.');
        }

        return $value;
    }

    /**
     * Consume exactly the required bytes, without unchecked unpack operations.
     *
     * @param int $length bytes required for the next field
     */
    private function readBytes(int $length): string
    {
        if ($length > \strlen($this->data) - $this->offset) {
            throw new InvalidArgumentException('Truncated WKB field or coordinate sequence.');
        }
        $bytes = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }
}
