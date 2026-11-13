# Decoding spatial data

Turn WKT or EWKT text into immutable Geometry objects.
These formats describe points, lines, polygons and collections using readable coordinates.
See [the README](../README.md) for installation.

## Decode a point

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;

$decoder = new Decoder(new WktDecoderStrategy());
$point = $decoder->decode('POINT (1 2)');

// $point->toArray() === [1, 2]
```

Coordinates keep their original order and values. The result is immutable,
independently of the shared factory's mutable/immutable mode.

## EWKT decoding

Use EWKT when the text includes a spatial reference identifier (SRID).

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;

$decoder = new Decoder(new EwktDecoderStrategy());
$point = $decoder->decode('SRID=4326;POINT (1 2)');

// $point->getSrid() === 4326
```

The prefix is optional; without it, the SRID is `0`. Put it before the whole
geometry: it applies to every collection member. Use an unsigned integer from
`0` to `PHP_INT_MAX`. Declaring an SRID does not transform coordinates.

## Choose WKT or EWKT

Z adds a third spatial coordinate; M adds a measure associated with the position.

| Input | WKT | EWKT |
| --- | --- | --- |
| `POINT (1 2)` — XY | Yes | Yes |
| `POINT Z (1 2 3)` — XYZ | Yes | Yes |
| `POINT M (1 2 3)` — XYM | Yes | Yes |
| `POINT ZM (1 2 3 4)` — XYZM | Yes | Yes |
| `POINTZ (1 2 3)` — compact marker | No | Yes |
| `POINT (1 2 3)` — inferred XYZ | No | Yes |
| `POINT (1 2 3 4)` — inferred XYZM | No | Yes |
| `SRID=4326;POINT (1 2)` | No | Yes |

Use an explicit `M` marker for XYM. Coordinates must match the declared dimension.
WKT collection members may inherit a dimension from their containing collection,
never from a preceding sibling. EWKT also supports dimension inference.

Use `EMPTY` for an empty geometry, such as `POINT EMPTY`; empty parentheses are
rejected. Unmarked EMPTY uses its inherited dimension, or XY by default.

## Supported geometries

Both strategies decode Point, LineString, Polygon, MultiPoint, MultiLineString,
MultiPolygon, GeometryCollection, CircularString and CompoundCurve.
Collections may be nested.

- **CircularString** describes circular arcs using control points:
  `CIRCULARSTRING (0 0,1 1,2 0)`. Curves are preserved without conversion to line segments.
- **CompoundCurve** combines linear and circular parts:
  `COMPOUNDCURVE ((0 0,1 1),CIRCULARSTRING (1 1,2 0,3 1))`.
  Write linear parts as coordinate lists, without a `LINESTRING` keyword.

EWKT additionally decodes Triangle in XY, XYZ, XYM and XYZM, and PolyhedralSurface
in XYZ or XYZM. PolyhedralSurface requires Z even when EMPTY.
Standard WKT does not decode these two types.

## Decoding and geometric validation

Successful decoding means the text could be read, not that its geometry is valid.
An unclosed polygon ring, too few line points or disconnected curve parts can still
produce an object. The decoder preserves them without repair.

Validate the result explicitly with spatial-validator when your application needs
geometric validity. Syntax errors, nonnumeric coordinates and incompatible dimensions
still raise an exception during decoding.

## Handle errors

Library decoding exceptions implement
`LongitudeOne\SpatialDecoder\Exception\DecoderExceptionInterface`.

| Exception | Meaning |
| --- | --- |
| `InvalidArgumentException` | Invalid input, dimensions or an unknown geometry name |
| `NotYetImplementedException` | Recognized type without decoding support: CurvePolygon, MultiCurve, MultiSurface or TIN |
| `NonInstantiableGeometryTypeException` | Abstract geometry category: Geometry, Curve, Surface or Solid |

The last two exceptions expose the recognized type through `getSpatialType()`.
They can be raised before the rest of the text is checked, including inside
collections; neither confirms that the full input is syntactically valid.
