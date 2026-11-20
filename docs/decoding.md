# Decoding spatial data

Turn WKT or EWKT text, or WKB binary data, into spatial objects. The default output is immutable Geometry.
The text formats describe points, lines, polygons and collections using readable coordinates.
See [the README](../README.md) for installation.

## Decode a point

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\WktDecoderStrategy;

$decoder = new Decoder(new WktDecoderStrategy());
$point = $decoder->decode('POINT (1 2)');

// $point->toArray() === [1, 2]
```

Coordinates keep their original order and values. By default the result is immutable,
independently of the shared factory's mutable/immutable mode.
See [Strategy configuration](strategy-configuration.md) to select Geography or mutable output.

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

## WKB decoding

Use `WkbDecoderStrategy` for raw binary Point, LineString, Polygon, MultiPoint,
MultiLineString, MultiPolygon, CircularString, CompoundCurve, Triangle and nested GeometryCollection in XY, XYZ, XYM or XYZM,
including EMPTY. PolyhedralSurface supports XYZ and XYZM only, including EMPTY.
Both little-endian and big-endian inputs are supported. The binary structures use
ISO/IEC CD 13249-3:201x(E), clause 5.1.58, with the encoder-compatible surface
representations described below.

The decoder requires `longitude-one/spatial-core` `^1.4.0` for shared WKB
correspondences. A type recognized by core is decoded only when listed above;
other types remain unsupported.

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\WkbDecoderStrategy;

$decoder = new Decoder(new WkbDecoderStrategy());
$binary = hex2bin('0101000000000000000000f03f00000000000000c0');
$point = $decoder->decode($binary);

// $point->toArray() === [1.0, -2.0]
// $point->getSrid() === 0
```

The hexadecimal literal is converted to binary by the application; the strategy
accepts only raw bytes. The X and Y ordinates retain their order and values.
WKB carries no SRID, so output uses SRID `0`, as WKT does. The `mode` and `mutable`
options have the same defaults and accepted values as the text strategies.

The type identifier determines the layout; coordinate count never changes it:

| Layout | Point | LineString | Polygon | Ordinates |
| --- | ---: | ---: | ---: | --- |
| XY | 1 | 2 | 3 | X, Y |
| XYZ | 1001 | 1002 | 1003 | X, Y, Z |
| XYM | 2001 | 2002 | 2003 | X, Y, M |
| XYZM | 3001 | 3002 | 3003 | X, Y, Z, M |

The dimensional identifiers and ordinate order follow ISO/IEC CD 13249-3:201x(E),
clause 5.1.58, Table 15 and rules bv–cc. This local source is a Committee Draft,
not a final published edition. These dimensional layouts extend the XY scope
of the Epic's OGC reference. M remains a measure, distinct from Z.

For Points, EMPTY follows the existing LongitudeOne WKB encoder convention: every ordinate
is an IEEE-754 quiet NaN (`7ff8000000000000` in big-endian order,
`000000000000f87f` in little-endian order). This interoperability convention is
not claimed as an OGC SFA 1.2.1 requirement. All-NaN inputs produce an EMPTY
Point with the dimension declared by the type identifier; mixed NaN and
non-NaN ordinates are rejected. Output family and mutability apply equally to
EMPTY Points, whose `toArray()` is `[]`.

LineStrings preserve their point sequence. Polygons preserve the exterior ring
first, followed by interior rings in their original order. Rings contain a point
count and coordinates, without a separate geometry header. A zero point count
represents LineString EMPTY; a zero ring count represents Polygon EMPTY. Their
declared type and dimension are preserved, as are EMPTY components. These forms
match the existing LongitudeOne WKB encoder.

```php
$line = $decoder->decode(hex2bin(
    '010200000002000000'
    . '000000000000f03f00000000000000c0'
    . '00000000000010400000000000001440'
));

// $line->toArray() === [[1.0, -2.0], [4.0, 5.0]]
```

The `mode` and `mutable` options apply to the entire graph, including rings and
vertices. Decoding does not close or reorient rings, check intersections or
relationships between rings, or repair geometrically invalid structures.
Constructible invalid geometries remain accepted, consistently with WKT.

### WKB collections

Collections preserve member count, order and geometry type. MultiPoint accepts
only Points, MultiLineString only LineStrings, and MultiPolygon only Polygons.
GeometryCollection accepts any supported type, including nested collections.
Each member has its own complete WKB header and may use a different byte order
from its parent or siblings.

| Collection | XY | XYZ | XYM | XYZM |
| --- | ---: | ---: | ---: | ---: |
| MultiPoint | 4 | 1004 | 2004 | 3004 |
| MultiLineString | 5 | 1005 | 2005 | 3005 |
| MultiPolygon | 6 | 1006 | 2006 | 3006 |
| GeometryCollection | 7 | 1007 | 2007 | 3007 |

These identifiers and nested headers follow ISO/IEC CD 13249-3:201x(E),
clause 5.1.58, collection grammar and Table 15. Every member must declare the
same coordinate dimension as its containing collection, recursively. A member's
header determines its dimension; the decoder never infers, converts or drops
ordinates to match the parent. This also applies to EMPTY members and nested
EMPTY collections.

A zero member count produces an EMPTY collection of the declared type and
dimension. EMPTY members remain separate members, preserving their positions.
For example, this MultiPoint contains a big-endian Point followed by a
little-endian EMPTY Point:

```php
$multiPoint = $decoder->decode(hex2bin(
    '010400000002000000'
    . '00000000013ff00000000000004000000000000000'
    . '0101000000000000000000f87f000000000000f87f'
));

// $multiPoint->toArray() === [[1.0, 2.0], []]
```

The output family and mutability options apply to every member, ring and vertex.
An invalid member rejects the complete input; no partial collection is returned.

### WKB curves

CircularString preserves its ordered control points. CompoundCurve preserves its
ordered LineString and CircularString components, each with its own complete
header and independent byte-order marker. Both types are supported at the root
and inside GeometryCollection, with all four coordinate layouts.

| Curve | XY | XYZ | XYM | XYZM |
| --- | ---: | ---: | ---: | ---: |
| CircularString | 8 | 1008 | 2008 | 3008 |
| CompoundCurve | 9 | 1009 | 2009 | 3009 |

These representations extend the Epic's OGC SFA 1.2.1 baseline. Their binary
structures and identifiers follow ISO/IEC CD 13249-3:201x(E), clause 5.1.58,
circular-string and compound-curve productions, rules r–y and Table 15.
Only the identifiers listed above are supported; the draft's alternative XY
identifiers `1000001` and `1000002` are outside the current encoder-compatible subset.

```php
$curve = $decoder->decode(hex2bin(
    '010900000002000000' // Little-endian CompoundCurve, two components.
    . '010200000001000000000000000000f03f0000000000000040' // LineString (1 2).
    . '000000000800000000' // Big-endian CircularString EMPTY.
));

// $curve->toArray() === [[[1.0, 2.0]], []]
```

CircularString stores a point count followed by coordinates, without individual
Point headers. CompoundCurve stores a component count followed by complete WKB
geometries. A zero count produces an EMPTY curve of the declared type and
dimension, matching the LongitudeOne encoder. EMPTY linear and circular
components keep their types and positions.

CompoundCurve accepts only LineString and CircularString components with exactly
its declared dimension, including when EMPTY. A nested CompoundCurve component
is rejected, consistently with the existing spatial model contract. The `mode`
and `mutable` options apply to every component and control point.

The decoder does not linearize arcs, merge components, connect disconnected parts,
repair control points or add geometric validity checks. Constructible short or
even-length circular sequences remain unchanged; use spatial-validator when
geometric validity is required.

### WKB surfaces

Triangle retains its type, ordered rings and coordinates. PolyhedralSurface
retains its ordered Polygon patches and their rings. Both types can appear at
the root or inside GeometryCollection; neither is converted to Polygon or MultiPolygon.

| Surface | XY | XYZ | XYM | XYZM |
| --- | ---: | ---: | ---: | ---: |
| Triangle | 17 | 1017 | 2017 | 3017 |
| PolyhedralSurface | Unsupported | 1015 | Unsupported | 3015 |

These identifiers extend the Epic's OGC SFA 1.2.1 baseline and appear in
ISO/IEC CD 13249-3:201x(E), clause 5.1.58, Table 15. However, that Committee
Draft's editor's note 4-406 leaves the WKB productions for Triangle and
PolyhedralSurface unspecified. The structures below follow the existing
LongitudeOne WKB encoder contract; they are not claimed as complete grammar
requirements from that draft.

Triangle uses a ring count followed by the same ring encoding as Polygon,
without nested ring headers. PolyhedralSurface uses a patch count followed by
complete Polygon WKB values. Each patch has its own byte-order marker and must
declare exactly the surface's dimension. Other patch types are rejected,
including Triangle. XYZ and XYZM are the layouts available in the spatial model;
XY and XYM PolyhedralSurface inputs are rejected even when EMPTY.

```php
$surface = $decoder->decode(hex2bin(
    '01f703000001000000' // Little-endian PolyhedralSurface Z, one patch.
    . '00000003eb00000000' // Big-endian Polygon Z EMPTY.
));

// $surface->toArray() === [[]]
```

A zero ring count produces Triangle EMPTY; a zero patch count produces
PolyhedralSurface EMPTY. Their declared type and dimension are retained.
EMPTY patches, rings and vertices keep their positions. The `mode` and `mutable`
options apply to every patch, ring and vertex, consistently with EWKT construction.
Decoding adds no triangle validity, patch topology or surface closure checks.

### Rejected WKB input

Other geometry types, unsupported dimensional identifiers, hexadecimal text,
EWKB and MySQL binary wrappers are rejected. Empty input,
invalid byte-order markers, truncation, impossible element counts and trailing bytes
are also rejected, including inside nested collections. Incompatible collection
member types or dimensions, including CompoundCurve components and PolyhedralSurface patches, are rejected.
A declared count cannot exceed what the remaining bytes can hold.
These rejections raise the library's `InvalidArgumentException`. The strategy
can be reused after a successful or failed decoding call.

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

Both text strategies decode Point, LineString, Polygon, MultiPoint, MultiLineString,
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

Successful decoding means the representation could be read and constructed, not that its geometry is valid.
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
