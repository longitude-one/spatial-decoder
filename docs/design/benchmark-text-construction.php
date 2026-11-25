<?php

declare(strict_types=1);

use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktLineStringFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPointFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Factory\WktPolygonFactory;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\Parser\PolygonWktParser;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktCoordinateReader;
use LongitudeOne\SpatialDecoder\Strategy\Wkt\WktTokenCursor;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;
use LongitudeOne\SpatialTypes\Builder\SpatialBuilder;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[, $path, $format, $shape, $output] = $argv;
$nested = 'collection' === $shape;
$extended = 'ewkt' === $format;
$polygon = '((0 0,1 0,1 1,0 0))';
$text = 'MULTIPOLYGON('.implode(',', array_fill(0, 256, $polygon)).')';
if ($nested) {
    $text = 'GEOMETRYCOLLECTION(GEOMETRYCOLLECTION('.$text.'))';
}
if ($extended) {
    $text = 'SRID=4326;'.$text;
}
$family = 'geography' === $output ? SpatialModelEnum::GEOGRAPHY : SpatialModelEnum::GEOMETRY;
$options = ['mode' => $family, 'mutable' => 'mutable' === $output];
memory_reset_peak_usage();
$start = hrtime(true);
if ('baseline' === $path) {
    $strategy = $extended ? new EwktDecoderStrategy($options) : new WktDecoderStrategy($options);
    $graph = $strategy->decode($text);
} else {
    // Fixture-specific prototype. Read coordinates with the production polygon reader.
    $cursor = new WktTokenCursor($text);
    if ($extended) {
        for ($i = 0; $i < 4; ++$i) {
            $cursor->moveNext();
        }
    }
    $builder = SpatialBuilder::create()->family($family)->srid($extended ? 4326 : 0);
    $builder = $options['mutable'] ? $builder->mutable() : $builder->immutable();
    $reader = new PolygonWktParser($cursor, new WktCoordinateReader($cursor), new WktPolygonFactory(new WktLineStringFactory(new WktPointFactory())));
    for ($i = 0; $i < ($nested ? 3 : 1); ++$i) {
        $cursor->moveNext();
        $cursor->expectSymbol('(');
    }
    $members = [];
    for ($i = 0; $i < 256; ++$i) {
        if ($i > 0) {
            $cursor->moveNext();
        }
        [, $rings] = $reader->consumePolygon(null);
        $members[] = $builder->createPolygon($rings);
    }
    $cursor->expectSymbol(')');
    $graph = $builder->createMultiPolygon($members);
    for ($i = 0; $i < ($nested ? 2 : 0); ++$i) {
        $cursor->expectSymbol(')');
        $graph = $builder->createCollection([$graph]);
    }
    $cursor->assertEnd();
}
printf("%.3f %d %s\n", (hrtime(true) - $start) / 1e6, memory_get_peak_usage(), hash('sha256', serialize($graph->toArray())));
