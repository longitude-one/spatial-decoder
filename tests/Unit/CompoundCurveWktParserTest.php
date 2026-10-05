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

namespace LongitudeOne\SpatialDecoder\Tests\Unit;

use LongitudeOne\Core\Enum\CoordinateDimensionEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktCompoundCurveFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPointFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\CompoundCurveWktParser;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserDispatcherInterface;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use LongitudeOne\SpatialTypes\Types\Dimension2\Geometry\CircularString;
use LongitudeOne\SpatialTypes\Types\Dimension3z\Geometry\Point;
use PHPUnit\Framework\TestCase;

/** @internal */
class CompoundCurveWktParserTest extends TestCase
{
    /** A dispatcher must not return a component with a conflicting dimension. */
    public function testRejectsDispatcherDimensionMismatch(): void
    {
        $parser = $this->parserReturning(new CircularString([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Compound-curve components must use the compound-curve coordinate dimension.');
        $parser->parse(CoordinateDimensionEnum::XYZ);
    }

    /** A circular-string token must resolve to a circular-string domain object. */
    public function testRejectsUnexpectedDispatcherGeometry(): void
    {
        $parser = $this->parserReturning(new Point());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A compound curve component must be a line string or circular string.');
        $parser->parse(CoordinateDimensionEnum::XYZ);
    }

    /**
     * Build a parser whose dispatcher violates one component postcondition.
     *
     * @param SpatialInterface $component geometry returned for the circular-string token
     */
    private function parserReturning(SpatialInterface $component): CompoundCurveWktParser
    {
        $cursor = new WktTokenCursor('(CIRCULARSTRING Z EMPTY)');
        $dispatcher = $this->createMock(WktGeometryParserDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('parseNext')
            ->with(CoordinateDimensionEnum::XYZ)->willReturn($component);

        return new CompoundCurveWktParser(
            $cursor,
            new WktCoordinateReader($cursor),
            new WktLineStringFactory(new WktPointFactory()),
            new WktCompoundCurveFactory(),
            $dispatcher
        );
    }
}
