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

use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\DecodedSpatialBuilder;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktDialect;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Decode supported EWKT geometries with an optional root spatial reference.
 */
final class EwktDecoderStrategy extends AbstractSpatialDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode the EWKT prefix and delegate the geometry to the WKT parser.
     *
     * @param string $data EWKT text to decode
     */
    public function decode(string $data): SpatialInterface
    {
        $cursor = new WktTokenCursor($data);
        $srid = $this->readOptionalSrid($cursor);
        $spatial = new WktParserFactory()->createFromCursor($cursor, WktDialect::EWKT)->parse();

        return new DecodedSpatialBuilder($this->mode, $this->mutable)->build(null === $srid ? $spatial : $spatial->withSrid($srid));
    }

    /**
     * Consume one required prefix token.
     *
     * @param WktTokenCursor $cursor shared text lexer cursor
     * @param int            $type   required token type
     */
    private function consume(WktTokenCursor $cursor, int $type): void
    {
        if (!$cursor->isNextToken($type)) {
            throw new InvalidArgumentException('Invalid EWKT SRID prefix; expected SRID=<integer>; before the root geometry.');
        }

        $cursor->moveNext();
    }

    /**
     * Read the optional root spatial reference.
     *
     * @param WktTokenCursor $cursor cursor positioned before the root geometry
     */
    private function readOptionalSrid(WktTokenCursor $cursor): ?int
    {
        if (!$cursor->isNextToken(Lexer::T_SRID)) {
            return null;
        }

        $cursor->moveNext();
        $this->consume($cursor, Lexer::T_EQUALS);
        $value = (string) $cursor->currentToken()?->value;
        $this->consume($cursor, Lexer::T_INTEGER);
        if (1 !== preg_match('/^[0-9]+$/D', $value)) {
            throw new InvalidArgumentException('The EWKT SRID must be a non-negative decimal integer.');
        }

        $srid = filter_var(ltrim($value, '0') ?: '0', \FILTER_VALIDATE_INT);
        if (false === $srid) {
            throw new InvalidArgumentException('The EWKT SRID exceeds the supported integer range.');
        }

        $this->consume($cursor, Lexer::T_SEMICOLON);

        return $srid;
    }
}
