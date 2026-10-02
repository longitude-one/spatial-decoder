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
use LongitudeOne\SpatialTypes\Types\Dimension4zm\Geometry\Point;
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
        yield 'unknown geometry word' => ['foo'];
        yield 'EWKT SRID prefix' => ['SRID=4326;POINT (1 2)'];
        yield 'unknown punctuation' => ['POINT (1 @ 2)'];
    }

    /** Test separate dimension markers continue to be accepted. */
    public function testDecodeAcceptsSeparateDimensionMarker(): void
    {
        $point = (new WktDecoderStrategy())->decode('POINT ZM (1 2 3 4)');
        self::assertInstanceOf(Point::class, $point);
        self::assertSame(1, $point->getX());
        self::assertSame(2, $point->getY());
        self::assertSame(3, $point->getZ());
        self::assertSame(4, $point->getM());
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

    /** Test compact dimension suffixes are rejected with guidance to use standard WKT syntax. */
    public function testDecodeRejectsCompactDimensionSuffix(): void
    {
        $this->assertCompactDimensionRejected('POINTM (1 2 3)', 'POINTM', 'POINT M');
        $this->assertCompactDimensionRejected('POINTZ (1 2 3)', 'POINTZ', 'POINT Z');
        $this->assertCompactDimensionRejected('POINTZM (1 2 3 4)', 'POINTZM', 'POINT ZM');
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

    /**
     * Assert a compact dimension form is rejected with the requested guidance.
     *
     * @param string $wkt          compact WKT input
     * @param string $compactForm  attached-dimension form in the diagnostic
     * @param string $standardForm separate-dimension form suggested to the user
     */
    private function assertCompactDimensionRejected(string $wkt, string $compactForm, string $standardForm): void
    {
        try {
            (new WktDecoderStrategy())->decode($wkt);
            self::fail('Compact dimension suffixes must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(\sprintf(
                'The compact dimension suffix in "%s" is not valid OGC WKT (Simple Feature Access 1.2.1). Use "%s" or consider the more permissive EWKT strategy.',
                $compactForm,
                $standardForm
            ), $exception->getMessage());
        }
    }
}
