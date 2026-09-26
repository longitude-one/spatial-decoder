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
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktGeometryCollectionFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Parses recursively nested WKT geometry collections.
 *
 * @internal
 */
final class GeometryCollectionWktParser implements WktGeometryParserInterface
{
    /**
     * Construct a geometry-collection parser.
     *
     * @param WktTokenCursor                       $cursor           lexer cursor for the WKT input
     * @param WktCoordinateReader                  $coordinateReader reader for coordinate values and dimensions
     * @param WktGeometryCollectionFactory         $factory          factory for the decoded collection
     * @param WktGeometryParserDispatcherInterface $geometryParser   recursive geometry dispatcher
     */
    public function __construct(
        private WktTokenCursor $cursor,
        private WktCoordinateReader $coordinateReader,
        private WktGeometryCollectionFactory $factory,
        private WktGeometryParserDispatcherInterface $geometryParser
    ) {
    }

    /**
     * Parse a geometry-collection representation.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension collection dimension inherited from a parent
     *
     * @return SpatialInterface the decoded geometry collection
     */
    public function parse(?CoordinateDimensionEnum $inheritedDimension = null): SpatialInterface
    {
        $dimension = $this->coordinateReader->consumeDimension($inheritedDimension);
        if ($this->cursor->isNextToken(Lexer::T_EMPTY)) {
            $this->cursor->moveNext();

            return $this->factory->createGeometryCollection($dimension ?? CoordinateDimensionEnum::XY, []);
        }

        $elements = [];
        $this->cursor->expectSymbol('(');
        do {
            $element = $this->geometryParser->parseNext($dimension);
            $elementDimension = $this->dimensionOf($element);
            if (null !== $dimension && $dimension !== $elementDimension) {
                throw $this->cursor->createInvalidInputException('WKT geometry-collection members must use one coordinate dimension.');
            }
            $dimension ??= $elementDimension;
            $elements[] = $element;

            $hasNextElement = $this->cursor->isNextToken(Lexer::T_COMMA);
            if ($hasNextElement) {
                $this->cursor->moveNext();
            }
        } while ($hasNextElement);

        $this->cursor->expectSymbol(')');

        return $this->factory->createGeometryCollection($dimension, $elements);
    }

    /**
     * Resolve a spatial object's coordinate layout.
     *
     * @param SpatialInterface $spatial spatial object to inspect
     *
     * @return CoordinateDimensionEnum the object's coordinate dimension
     */
    private function dimensionOf(SpatialInterface $spatial): CoordinateDimensionEnum
    {
        return match (true) {
            $spatial->hasZ() && $spatial->hasM() => CoordinateDimensionEnum::XYZM,
            $spatial->hasZ() => CoordinateDimensionEnum::XYZ,
            $spatial->hasM() => CoordinateDimensionEnum::XYM,
            default => CoordinateDimensionEnum::XY,
        };
    }
}
