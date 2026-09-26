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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktSpatialObjectFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Parses WKT multi-line-string representations.
 *
 * @internal
 */
final class MultiLineStringWktParser
{
    /**
     * Construct a multi-line-string parser.
     *
     * @param WktTokenCursor          $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader     $coordinateReader reader for coordinate values and dimensions
     * @param WktSpatialObjectFactory $factory          factory for the decoded multi-line string
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktSpatialObjectFactory $factory
    ) {
    }

    /**
     * Parse a multi-line-string representation.
     *
     * @return SpatialInterface the decoded multi-line string
     */
    public function parse(): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension();
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();
            $this->cursor->assertEnd();

            return $this->factory->createMultiLineString($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        $lineStrings = [];
        $this->cursor->expectSymbol('(');
        $hasNextMember = true;
        do {
            [$lineDimension, $coordinates] = $this->consumeLineStringMember($dimension);
            $dimension ??= $lineDimension;
            $lineStrings[] = $coordinates;

            $hasNextMember = $this->cursor->isNextToken(Lexer::T_COMMA);
            if ($hasNextMember) {
                $this->cursor->moveNext();
            }
        } while ($hasNextMember);

        $this->cursor->expectSymbol(')');
        $this->cursor->assertEnd();

        return $this->factory->createMultiLineString($dimension ?? CoordinateDimensionEnum::XY, $lineStrings);
    }

    /**
     * Consume one line-string member, preserving empty members.
     *
     * @param CoordinateDimensionEnum|null $dimension declared or inferred dimension
     *
     * @return array{CoordinateDimensionEnum|null, list<list<float|int>>} member dimension and coordinates
     */
    private function consumeLineStringMember(?CoordinateDimensionEnum $dimension): array
    {
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return [$dimension, []];
        }

        return $this->coordinateReader->consumeCoordinateSequence($dimension);
    }
}
