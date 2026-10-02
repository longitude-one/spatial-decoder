# Spatial Decoder

The decoder module provides an interface for decoding spatial data into `SpatialInterface` objects.

This library provides five strategies for decoding spatial data from different formats:

* A strategy for decoding Extended Well-Known Binary (EWKB) into spatial interfaces.
* A strategy for decoding ISO Well-Known Binary (WKB) into spatial interfaces.
* A strategy for decoding the internal MySQL storage format into spatial interfaces.
* A strategy for decoding Well-Known Text (WKT) into spatial interfaces.
* A strategy for decoding Extended Well-Known Text (EWKT), including SRID information, into spatial interfaces.

Feel free to provide additional strategies for decoding other formats into spatial interfaces.

See the [strategy guide](docs/strategies.md) for a comparison of the five formats, usage examples, implementation limitations, and links to reference documentation.

## EWKT decoding

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;

$decoder = new Decoder(new EwktDecoderStrategy());
$point = $decoder->decode('SRID=4326;POINT (1 2)');
// $point->getSrid() === 4326

$point = $decoder->decode('POINT (1 2)');
// $point->getSrid() === 0
```

EWKT reuses the supported WKT geometry syntax, including explicit Z, M and ZM
markers and EMPTY geometries. The optional `SRID=<value>;` prefix is accepted
only before the root geometry; the reference applies to all collection members.
Coordinates retain their original order and values.

SRIDs are unsigned decimal integers from `0` through `PHP_INT_MAX`, with leading
zeroes accepted. Prefix keywords are case-insensitive and whitespace between
tokens is accepted by the shared lexer. Negative values, signs, fractions,
exponents, overflow and nested prefixes raise the decoder's
`InvalidArgumentException`. WKT decoding continues to reject SRID prefixes.

This implements the SRID extension of [PostGIS EWKT](https://postgis.net/docs/ST_GeomFromEWKT.html)
for the geometry syntax already supported by this decoder, not every PostGIS
geometry type or parser extension. It preserves supported SRIDs without applying
PostGIS-specific SRID normalization. The [PostGIS grammar](https://github.com/postgis/postgis/blob/master/liblwgeom/lwin_wkt_parse.y)
places its optional SRID at the root; EWKT is a vendor extension, not OGC WKT.

## Recognized types awaiting implementation

`LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException` is available
for strategies that recognize a valid spatial type whose spatial object cannot
yet be instantiated. Construct it with the recognized `GeometryTypeEnum` value:

```php
throw new \LongitudeOne\SpatialDecoder\Exception\NotYetImplementedException(\LongitudeOne\Core\Enum\GeometryTypeEnum::CIRCULARSTRING);
```

The exception implements `DecoderExceptionInterface` and extends `\RuntimeException`.
Consumers can retrieve the same enum value with `getSpatialType()`; its message
identifies that type and states that decoding is not yet implemented. It is
independent of the input format and must not be used for malformed input, unknown
types, or types invalid for the applicable format.

This exception adds a shared contract for strategies; existing decoding strategies
and their supported types are unchanged.

## Current status
![longitude-one/spatial--decoder](https://img.shields.io/badge/longitude--one-spatial--decoder-blue)
![Stable release](https://img.shields.io/github/v/release/longitude-one/spatial-decoder)
![Minimum PHP Version](https://img.shields.io/packagist/php-v/longitude-one/spatial-decoder.svg?maxAge=3600)
[![Packagist License](https://img.shields.io/packagist/l/longitude-one/spatial-decoder)](https://github.com/longitude-one/spatial-decoder/blob/main/LICENSE)

[![Last integration test](https://github.com/longitude-one/spatial-decoder/actions/workflows/php-oldest.yaml/badge.svg)](https://github.com/longitude-one/spatial-decoder/actions/workflows/php-oldest.yaml)
[![Downloads](https://img.shields.io/packagist/dm/longitude-one/spatial-decoder.svg)](https://packagist.org/packages/longitude-one/spatial-decoder)
[![codecov](https://codecov.io/gh/longitude-one/spatial-decoder/branch/main/graph/badge.svg)](https://codecov.io/gh/longitude-one/spatial-decoder)

## Installation

```bash
composer require longitude-one/spatial-decoder:1.0.0-RC.0
```
