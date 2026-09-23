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
