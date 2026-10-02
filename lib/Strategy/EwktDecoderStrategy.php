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

namespace LongitudeOne\SpatialDecoder\Strategy;

use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Decode supported EWKT geometries with an optional root spatial reference.
 */
final class EwktDecoderStrategy implements StringDecoderStrategyInterface
{
    /**
     * Decode the EWKT prefix and delegate the geometry to the WKT parser.
     *
     * @param string $data EWKT text to decode
     */
    public function decode(string $data): SpatialInterface
    {
        $cursor = new WktTokenCursor($data);
        if (!$cursor->isNextToken(Lexer::T_SRID)) {
            return (new WktParserFactory())->createFromCursor($cursor)->parse();
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

        return (new WktParserFactory())->createFromCursor($cursor)->parse()->withSrid($srid);
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
}
