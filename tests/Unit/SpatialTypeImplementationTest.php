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

use LongitudeOne\Core\Enum\GeometryTypeEnum;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException;
use LongitudeOne\SpatialDecoder\Strategy\Common\Lexer;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Common\Lexer
 */
class SpatialTypeImplementationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidInputs(): iterable
    {
        foreach (['GEOMETRY EMPTY', 'CURVE EMPTY', 'SURFACE EMPTY', 'SOLID EMPTY',
            'UNKNOWN EMPTY', 'CIRCULARSTRINGUNKNOWN EMPTY', 'POINT (1)', 'POINT (1 @ 2)',
            'TRIANGLE EMPTY', 'POLYHEDRALSURFACE EMPTY'] as $input) {
            yield $input => [$input];
        }
    }

    /** @return iterable<string, array{GeometryTypeEnum}> */
    public static function missingTypes(): iterable
    {
        foreach ([GeometryTypeEnum::CIRCULARSTRING, GeometryTypeEnum::COMPOUNDCURVE,
            GeometryTypeEnum::CURVEPOLYGON, GeometryTypeEnum::MULTICURVE,
            GeometryTypeEnum::MULTISURFACE, GeometryTypeEnum::TIN] as $type) {
            yield $type->name => [$type];
        }
    }

    /** Test all implemented geometry keywords remain available to parsers. */
    public function testImplementedTypesRemainLexerTokens(): void
    {
        foreach ([GeometryTypeEnum::POINT, GeometryTypeEnum::LINESTRING, GeometryTypeEnum::POLYGON,
            GeometryTypeEnum::TRIANGLE, GeometryTypeEnum::POLYHEDRALSURFACE,
            GeometryTypeEnum::MULTIPOINT, GeometryTypeEnum::MULTILINESTRING,
            GeometryTypeEnum::MULTIPOLYGON, GeometryTypeEnum::GEOMETRYCOLLECTION] as $type) {
            $lexer = new Lexer($type->name);
            self::assertTrue($lexer->moveNext());
            self::assertSame($type->name, $lexer->lookahead->value);
        }
    }

    /**
     * Test the enum retained when a case-insensitive keyword is recognized.
     *
     * @param GeometryTypeEnum $type recognized unimplemented type
     */
    #[DataProvider('missingTypes')]
    public function testLexerReportsMissingImplementation(GeometryTypeEnum $type): void
    {
        try {
            new Lexer(strtolower($type->name).' EMPTY');
            self::fail('The lexer must report the missing implementation.');
        } catch (NotYetImplementedException $exception) {
            self::assertSame($type, $exception->getSpatialType());
            self::assertStringContainsString($type->value, $exception->getMessage());
        }
    }

    /** Test lexical classification precedes validation of an unsupported body. */
    public function testMissingImplementationIsReportedBeforeBodyValidation(): void
    {
        $this->expectException(NotYetImplementedException::class);

        (new WktDecoderStrategy())->decode('CIRCULARSTRING (garbage)');
    }

    /**
     * Test other input errors keep the decoder invalid-input exception.
     *
     * @param string $input invalid or unsupported input
     */
    #[DataProvider('invalidInputs')]
    public function testOtherErrorsRemainInvalidInput(string $input): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            try {
                $strategy->decode($input);
                self::fail('Invalid input must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Test WKT, EWKT, dimensions and collection members share the classification.
     *
     * @param GeometryTypeEnum $type recognized unimplemented type
     */
    #[DataProvider('missingTypes')]
    public function testStrategiesReportMissingImplementation(GeometryTypeEnum $type): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $prefix = $strategy instanceof EwktDecoderStrategy ? 'SRID=4326;' : '';
            foreach ([' EMPTY', ' Z EMPTY', ' M EMPTY', ' ZM EMPTY'] as $body) {
                foreach ([$type->name.$body, 'GEOMETRYCOLLECTION ('.$type->name.$body.')'] as $geometry) {
                    try {
                        $strategy->decode($prefix.$geometry);
                        self::fail('The strategy must report the missing implementation.');
                    } catch (NotYetImplementedException $exception) {
                        self::assertSame($type, $exception->getSpatialType());
                    }
                }
            }
        }
    }
}
