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

namespace LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktMultiLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

/**
 * Parses WKT multi-line-string representations.
 *
 * @internal
 */
final class MultiLineStringWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a multi-line-string parser.
     *
     * @param WktTokenCursor            $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader       $coordinateReader reader for coordinate values and dimensions
     * @param WktMultiLineStringFactory $factory          factory for the decoded multi-line string
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktMultiLineStringFactory $factory
    ) {
    }

    /**
     * Parse a multi-line-string representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return ImmutableSpatialInterface the decoded multi-line string
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutableSpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

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
