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
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface;
use LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException;
use LongitudeOne\SpatialDecoder\Exception\NonInstantiableGeometryTypeException;
use LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \LongitudeOne\SpatialDecoder\Exception\NonInstantiableGeometryTypeException
 * @covers \LongitudeOne\SpatialDecoder\Strategy\Common\Lexer
 */
class NonInstantiableGeometryTypeTest extends TestCase
{
    /** @return iterable<string, array{GeometryTypeEnum}> */
    public static function abstractTypes(): iterable
    {
        foreach (GeometryTypeEnum::cases() as $type) {
            if (!$type->isInstantiable()) {
                yield $type->name => [$type];
            }
        }
    }

    /** @param GeometryTypeEnum $type recognized non-instantiable type */
    #[DataProvider('abstractTypes')]
    public function testStrategiesRejectAbstractTypesBeforeBodyValidation(GeometryTypeEnum $type): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            $prefixes = $strategy instanceof EwktDecoderStrategy ? ['', 'SRID=4326;'] : [''];
            foreach ($prefixes as $prefix) {
                foreach ([' EMPTY', ' Z EMPTY', ' M EMPTY', ' ZM EMPTY', 'Z EMPTY', 'M EMPTY', 'ZM EMPTY', ' (garbage)'] as $body) {
                    foreach ([strtolower($type->name).$body, 'GEOMETRYCOLLECTION ('.$type->name.$body.')'] as $geometry) {
                        try {
                            (new Decoder($strategy))->decode($prefix.$geometry);
                            self::fail('A non-instantiable geometry must be rejected.');
                        } catch (NonInstantiableGeometryTypeException $exception) {
                            self::assertContains(DecoderExceptionInterface::class, class_implements($exception));
                            self::assertSame($type, $exception->getSpatialType());
                            self::assertSame(\sprintf('Geometry type "%s" is recognized but is not instantiable.', $type->value), $exception->getMessage());
                        }
                    }
                }
            }
        }
    }

    /** Verify lexical boundaries and the three distinct public error contracts. */
    public function testUnknownAndUnimplementedTypesRemainDistinct(): void
    {
        foreach ([new WktDecoderStrategy(), new EwktDecoderStrategy()] as $strategy) {
            foreach (['UNKNOWN', 'CURVEUNKNOWN', 'GEOMETRYUNKNOWN', 'SURFACEUNKNOWN', 'SOLIDUNKNOWN', 'CURVEPOLYGON'] as $keyword) {
                try {
                    $strategy->decode($keyword.' EMPTY');
                    self::fail('An unknown or unimplemented type must be rejected.');
                } catch (DecoderExceptionInterface $exception) {
                    self::assertSame('CURVEPOLYGON' === $keyword ? NotYetImplementedException::class : InvalidArgumentException::class, $exception::class);
                }
            }
        }
    }
}
