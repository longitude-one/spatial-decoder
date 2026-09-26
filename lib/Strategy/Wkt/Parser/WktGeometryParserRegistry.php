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
use LongitudeOne\SpatialDecoder\Exception\LogicException;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;

/**
 * Dispatches geometries in a shared WKT token stream.
 *
 * @internal
 */
final class WktGeometryParserRegistry implements WktGeometryParserDispatcherInterface
{
    /** @var array<int, WktGeometryParserInterface> */
    private array $parsers = [];

    /**
     * Construct a registry for one WKT token stream.
     *
     * @param WktTokenCursor $cursor lexer cursor shared by registered parsers
     */
    public function __construct(private WktTokenCursor $cursor)
    {
    }

    /**
     * Parse the next geometry in the token stream.
     *
     * @param CoordinateDimensionEnum|null $inheritedDimension collection dimension to apply to unmarked members
     *
     * @return SpatialInterface the decoded geometry
     */
    public function parseNext(?CoordinateDimensionEnum $inheritedDimension): SpatialInterface
    {
        $geometryType = $this->cursor->currentToken()?->type;
        $parser = null === $geometryType ? null : ($this->parsers[$geometryType] ?? null);
        if (null === $parser) {
            throw $this->cursor->createInvalidInputException('The supplied WKT geometry type is not supported.');
        }

        $this->cursor->moveNext();

        return $parser->parse($inheritedDimension);
    }

    /**
     * Register the parser for one geometry token.
     *
     * @param int                        $geometryType lexer token for the geometry type
     * @param WktGeometryParserInterface $parser       parser for that geometry
     */
    public function register(int $geometryType, WktGeometryParserInterface $parser): void
    {
        if (isset($this->parsers[$geometryType])) {
            throw new LogicException('A WKT parser is already registered for this geometry type.');
        }

        $this->parsers[$geometryType] = $parser;
    }
}
