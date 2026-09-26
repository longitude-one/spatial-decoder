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
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor
 */
class WktGeometryDispatchTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedGeometryWkts(): iterable
    {
        yield 'empty input' => [''];
        yield 'unsupported geometry type' => ['POLYGON EMPTY'];
        yield 'unknown geometry word' => ['foo'];
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
     * Test rejection of unsupported top-level geometry types.
     *
     * @param string $wkt unsupported WKT input
     */
    #[DataProvider('unsupportedGeometryWkts')]
    public function testDecodeRejectsUnsupportedGeometryTypes(string $wkt): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WktDecoderStrategy())->decode($wkt);
    }
}
