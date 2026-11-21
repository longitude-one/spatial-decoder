# Spatial Decoder

[![Release](https://img.shields.io/github/v/release/longitude-one/spatial-decoder?include_prereleases&sort=semver&label=release)](https://github.com/longitude-one/spatial-decoder/releases)
[![PHP](https://img.shields.io/packagist/php-v/longitude-one/spatial-decoder?label=php)](https://packagist.org/packages/longitude-one/spatial-decoder)
[![License](https://img.shields.io/packagist/l/longitude-one/spatial-decoder)](https://github.com/longitude-one/spatial-decoder/blob/main/LICENSE)
[![CI](https://github.com/longitude-one/spatial-decoder/actions/workflows/ci.yml/badge.svg)](https://github.com/longitude-one/spatial-decoder/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/longitude-one/spatial-decoder/branch/main/graph/badge.svg)](https://codecov.io/gh/longitude-one/spatial-decoder/tree/main)
[![Monthly downloads](https://img.shields.io/packagist/dm/longitude-one/spatial-decoder)](https://packagist.org/packages/longitude-one/spatial-decoder)
[![Documentation](https://img.shields.io/badge/docs-v1-blue)](https://www.longitude-one.fr/spatial-decoder/v1/index.html)

Decode serialized spatial data into LongitudeOne spatial objects.
Choose a strategy for the input format, then validate the resulting geometry explicitly
when your application requires geometric validity.

## Installation

```bash
composer require longitude-one/spatial-decoder:^1.0.0-RC.0
```

Requires PHP 8.4 or later and `longitude-one/spatial-core` `^1.4.0`.
Composer installs the compatible dependencies automatically.

## Usage

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;

$decoder = new Decoder(new WktDecoderStrategy());
$point = $decoder->decode('POINT (1 2)');

// $point->toArray() === [1, 2]
```

## Documentation

Read the [official documentation](https://www.longitude-one.fr/spatial-decoder/v1/index.html).

See the [decoding guide](docs/decoding.md) for EWKT, EWKB, WKB geometries, curves, dimensions and unsupported types.

## Input formats

Built-in strategies decode WKT and EWKT, plus XY, XYZ, XYM and XYZM Points, LineStrings
and Polygons, MultiPoint, MultiLineString, MultiPolygon, CircularString, CompoundCurve, Triangle and nested GeometryCollection
in raw WKB (both byte orders), including EMPTY geometries. PolyhedralSurface supports XYZ and XYZM only.
Other WKB geometry types are not yet supported. Initial EWKB support accepts XY geometries with an optional root SRID;
see [EWKB decoding](docs/decoding.md#ewkb-decoding) for its limits. MySQL internal binary is not supported.

## Decoding and geometric validation

By default, WKT, EWKT, WKB and EWKB produce immutable Geometry objects, including collection members,
independently of the shared factory's mutable/immutable mode.
See [Strategy configuration](docs/strategy-configuration.md) to select Geography or mutable output.

The decoder checks syntax, numeric ordinates, dimensions and supported types.
It preserves geometrically invalid structures such as unclosed polygon rings;
decoding success does not establish geometric validity. Malformed representations
still raise an exception. See the [validation boundary](docs/decoding.md#decoding-and-geometric-validation).

## Contributing

See [Contributing](CONTRIBUTING.md) for development setup, checks and contribution guidelines.
