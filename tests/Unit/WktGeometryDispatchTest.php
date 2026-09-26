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

use LongitudeOne\Core\Diagnostic\DiagnosticValueFormatter;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\PointWktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParser
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktParserFactory
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\WktGeometryParserRegistry
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class WktGeometryDispatchTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedWkts(): iterable
    {
        yield 'empty input' => [''];
        yield 'unsupported geometry type' => ['TRIANGLE EMPTY'];
        yield 'unsupported curved geometry type' => ['CIRCULARSTRING (0 0, 1 1, 2 2)'];
        yield 'unknown geometry word' => ['foo'];
        yield 'EWKT SRID prefix' => ['SRID=4326;POINT (1 2)'];
        yield 'unknown punctuation' => ['POINT (1 @ 2)'];
    }

    /** Test exception messages include a sanitized representation of the input. */
    public function testDecodeErrorMessageSanitizesInvalidInput(): void
    {
        $input = "POINT EMP\nforged log entry";
        $formattedInput = DiagnosticValueFormatter::format($input);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('Invalid WKT input: "%s".', $formattedInput));

        (new WktDecoderStrategy())->decode($input);
    }

    /**
     * Test rejection of unsupported WKT representations.
     *
     * @param string $wkt unsupported WKT input
     */
    #[DataProvider('unsupportedWkts')]
    public function testDecodeRejectsUnsupportedWktRepresentations(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }
}
