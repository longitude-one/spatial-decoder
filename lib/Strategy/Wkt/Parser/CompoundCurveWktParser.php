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
use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktCompoundCurveFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableCircularStringInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableCurveInterface;
use LongitudeOne\SpatialTypes\Interfaces\ImmutableSpatialInterface;

/**
 * Parses WKT compound-curve representations.
 *
 * @internal
 */
final class CompoundCurveWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a compound-curve parser.
     *
     * @param WktTokenCursor                       $cursor            lexer cursor for the WKT input
     * @param WktCoordinateReader                  $coordinateReader  reader for coordinate values and dimensions
     * @param WktLineStringFactory                 $lineStringFactory factory for linear components
     * @param WktCompoundCurveFactory              $factory           factory for the decoded compound curve
     * @param WktGeometryParserDispatcherInterface $geometryParser    dispatcher for typed curve components
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktLineStringFactory $lineStringFactory,
        private WktCompoundCurveFactory $factory,
        private WktGeometryParserDispatcherInterface $geometryParser
    ) {
    }

    /**
     * Parse a compound-curve representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension dimension inherited from a parent collection
     *
     * @return ImmutableSpatialInterface the decoded compound curve
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): ImmutableSpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createCompoundCurve($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        $this->cursor->expectSymbol('(');
        $curves = [];
        do {
            $curve = $this->parseComponent($dimension);
            $dimension ??= $curve->getDimension();
            $curves[] = $curve;

            $hasNextCurve = $this->cursor->isNextToken(Lexer::T_COMMA);
            if ($hasNextCurve) {
                $this->cursor->moveNext();
            }
        } while ($hasNextCurve);
        $this->cursor->expectSymbol(')');

        return $this->factory->createCompoundCurve($dimension, $curves);
    }

    /**
     * Parse a parenthesized linear component or a typed curve component.
     *
     * @param CoordinateDimensionEnum|null $dimension compound-curve dimension established by its parent or first component
     *
     * @return ImmutableCurveInterface the decoded component
     */
    private function parseComponent(?CoordinateDimensionEnum $dimension): ImmutableCurveInterface
    {
        if ($this->cursor->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            [$dimension, $coordinates] = $this->coordinateReader->consumeCoordinateSequence($dimension);

            return $this->lineStringFactory->createLineString($dimension, $coordinates);
        }

        return $this->parseTypedComponent($dimension);
    }

    /**
     * Parse and validate a typed circular-string component.
     *
     * @param CoordinateDimensionEnum|null $dimension compound-curve dimension established by its parent or first component
     *
     * @return ImmutableCurveInterface the decoded component
     */
    private function parseTypedComponent(?CoordinateDimensionEnum $dimension): ImmutableCurveInterface
    {
        $token = $this->cursor->currentToken();
        if (null === $token || Lexer::T_CIRCULARSTRING !== $token->type) {
            throw $this->cursor->createInvalidInputException('A compound curve component must be a line string or circular string.');
        }

        $curve = $this->geometryParser->parseNext($dimension);
        if (null !== $dimension && $curve->getDimension() !== $dimension) {
            throw $this->cursor->createInvalidInputException('Compound-curve components must use the compound-curve coordinate dimension.');
        }
        if (GeometryTypeEnum::CIRCULARSTRING === $curve->getType() && $curve instanceof ImmutableCircularStringInterface) {
            return $curve;
        }

        throw $this->cursor->createInvalidInputException('A compound curve component must be a line string or circular string.');
    }
}
