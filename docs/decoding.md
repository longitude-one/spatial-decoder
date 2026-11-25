# Decoding spatial data

Turn WKT or EWKT text, or WKB, EWKB and MySQL binary data, into spatial objects. The default output is immutable Geometry.
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

WKB failures raise `LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException`,
which implements `DecoderExceptionInterface`. The message identifies the cause;
applications should catch the exception type rather than match its exact text.
This applies at the root and inside nested members.

| Error category | Rejected input |
| --- | --- |
| Header | Empty input, incomplete headers or byte-order markers other than `0` and `1` |
| Representation or identifier | Unknown or unsupported types and dimensions, EWKB flags, hexadecimal text or MySQL binary wrappers |
| Truncation | Incomplete counters, ordinates, rings, curve components, patches or nested geometries |
| Impossible count | A declared count exceeding what the available bytes can hold, including very large counters in short inputs |
| Member contract | Incompatible member types or dimensions, including EMPTY components and patches |
| Coordinate contract | Mixed NaN and non-NaN Point ordinates, or PolyhedralSurface without Z |
| Extra data | Any bytes remaining after the single root geometry |

Impossible counters are rejected before allocating or constructing the declared
number of elements. A failure returns no partially decoded object and does not
alter an object returned by an earlier call. The same strategy instance remains
usable after every rejection.

These checks do not validate geometric topology. Constructible short lines,
unclosed rings and disconnected curve components remain accepted without repair.

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

### Internal binary decoding

`WkbDecoderStrategy` delegates geometry bodies, recursive member decoding and
spatial construction to the internal `BinaryGeometryDecoder`. `WkbDialect`
interprets every root and member header. The shared reader rejects mixed NaN
sequences and maps all-NaN sequences to EMPTY, the approved common WKB/EWKB
policy. Each decode creates a fresh bounded `WkbReader`;
failed calls do not leave cursor or construction state in the strategy.

These implementation classes are internal, not supported public extension
points. Both binary strategies use this engine with their own header dialect; sharing
the engine does not enable EWKB flags or SRIDs in WKB. The public constructor,
output options, accepted geometry families and rejection behavior are unchanged.

## EWKB decoding

Use `EwkbDecoderStrategy` for the supported subset of EWKB, a PostGIS extension
separate from standard WKB. Select the strategy explicitly;
no format or transport auto-detection is performed.

| Boundary | `WkbDecoderStrategy` | `EwkbDecoderStrategy` |
| --- | --- | --- |
| Format | Supported ISO/SQL-MM WKB subset | Supported PostGIS EWKB subset, also accepting ordinary supported WKB |
| Embedded SRID | Not supported; output SRID `0` | Optional uint32 SRID after each flagged type word |
| Dimensions | Base code plus offsets 0/1000/2000/3000 | The same offsets and PostGIS Z/M flags, combined by logical OR |
| Transport | One geometry in raw bytes | One geometry in raw bytes |
| Byte order | Little endian (NDR) or big endian (XDR), per header | Little endian (NDR) or big endian (XDR), per header |

Hexadecimal strings, PostgreSQL `bytea` text such as `\x0101...`, and MySQL binary
wrappers are not raw WKB/EWKB. Convert the database driver's representation in
the application before decoding; do not strip bytes from an already binary value.

### Decode EWKB bytes

It reuses the WKB geometry bodies and supports optional SRIDs at root and member headers in either byte order.
Point, LineString, Polygon, CircularString, Triangle, MultiPoint, MultiLineString,
MultiPolygon, GeometryCollection and CompoundCurve support XY, XYZ, XYM and XYZM.
PolyhedralSurface supports XYZ and XYZM only, including EMPTY.

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwkbDecoderStrategy;

$decoder = new Decoder(new EwkbDecoderStrategy());
$hex = '0101000020e6100000000000000000f03f00000000000000c0';
$binary = hex2bin($hex); // Convert this known hexadecimal fixture to raw bytes.
$point = $decoder->decode($binary);

// $point->toArray() === [1.0, -2.0]
// $point->getSrid() === 4326
```

For PostgreSQL hex-format `bytea` text, remove its `\x` prefix before calling
`hex2bin()` and handle a `false` conversion result in your application. Escape-format
`bytea` text requires its own driver-aware conversion; it is not hexadecimal.

### Output models and options

All supported types and layouts listed in this section are available as Geometry or Geography,
mutable or immutable, including EMPTY values. Immutable Geometry is the default.
To select mutable Geography, reuse the binary value above:

```php
use LongitudeOne\Core\Enum\SpatialModelEnum;

$decoder = new Decoder(new EwkbDecoderStrategy([
    'mode' => SpatialModelEnum::GEOGRAPHY,
    'mutable' => true,
]));
$point = $decoder->decode($binary);

// $point->getFamily() === SpatialModelEnum::GEOGRAPHY
// $point->getSrid() === 4326
```

The options apply to every member, ring and vertex. Geography does not imply
reprojection or geometric validation. See [Strategy configuration](strategy-configuration.md)
for option types and defaults; there is no `srid` option.

### Dimensions, EMPTY and SRID

At the root, an absent SRID and an explicit zero both produce SRID `0`. Explicit values are
preserved from `0` through `min(4294967295, PHP_INT_MAX)`, without clamping,
coordinate transformation or axis reordering. Existing `mode` and `mutable`
options apply; immutable Geometry remains the default.

The Z (`0x80000000`) and M (`0x40000000`) flags select XYZ, XYM or XYZM.
SQL/MM offsets 1000, 2000 and 3000 are also accepted. Flag and offset declarations
are combined using logical OR: Z plus offset 2000 selects XYZM and requires four
ordinates. Identical declarations are accepted without adding ordinates. The
header alone determines the layout; payload length never supplies missing dimensions.
Coordinates retain X, Y, Z, M order, with XYM using X, Y, M.

All-NaN coordinate tuples become EMPTY with their declared dimension and SRID;
mixed NaN/numeric tuples are rejected. Zero point, ring, member or patch counts
retain the declared EMPTY geometry type and layout. Members must declare the
parent layout, including EMPTY members, whose positions are preserved.

Every nested header selects its own byte order. A member without an SRID field
inherits its immediate parent's effective SRID. An explicit member SRID is
preserved, even when it differs from its parent's value or is zero. It applies
to that member's descendants without affecting later siblings. This also holds
for EMPTY members. Polygon rings and sequence vertices have no separate headers
and retain their containing geometry's metadata. No SRID consistency validation,
normalization, reprojection or curve linearization is performed.

For example, under a collection with SRID 4326, a nested collection explicitly
declaring 3857 and its members without SRID fields retain 3857. A later sibling
without an SRID field still inherits 4326. The `mode` and `mutable` options apply
throughout the graph, including generated rings and vertices.
See the [EWKB compatibility contract](ewkb-compatibility-v1.md) for the complete contract.

### EWKB failures and compatibility limits

EWKB failures raise `LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException`,
implementing `DecoderExceptionInterface`. Catch the exception type; messages can
mention WKB because the binary engine is shared. Do not match exact message text.

| Failure category | Examples |
| --- | --- |
| Transport or header | Hex/bytea text, MySQL wrappers, empty input, invalid byte-order marker, incomplete header |
| Unsupported feature | Unknown bits including `0x10000000`, unknown types, CurvePolygon, MultiCurve, MultiSurface, TIN or abstract types |
| Truncation or count | Incomplete flagged SRID, ordinates or members; counters exceeding the available bytes |
| Member contract | Wrong Multi* member type, mismatched dimensions, nested CompoundCurve component, non-Polygon surface patch |
| Coordinate contract | Mixed NaN/numeric ordinates; XY or XYM PolyhedralSurface, including EMPTY |
| Extra data | Any bytes after the one root geometry |

Failure returns no partial graph and leaves the strategy reusable. Unsupported
binary families use `InvalidArgumentException`, rather than the text strategies'
`NotYetImplementedException` or `NonInstantiableGeometryTypeException`.
Invalid constructor options raise Symfony OptionsResolver exceptions before decoding.

### Extension references

The [PostGIS ST_AsEWKB documentation](https://postgis.net/docs/ST_AsEWKB.html)
describes EWKB output with SRID and NDR/XDR byte order;
[ST_GeomFromEWKB](https://postgis.net/docs/ST_GeomFromEWKB.html) identifies EWKB as a
PostGIS-specific format rather than an OGC standard. Standard WKB dimensional
identifiers are described in the [WKB section](#wkb-decoding), independently of EWKB flags.

LongitudeOne preserves unsigned SRIDs without PostGIS clamping, scopes inheritance
to the immediate parent, rejects unsupported bits and uses the common WKB/EWKB
NaN-to-EMPTY policy. These are project compatibility choices, not standard WKB
requirements or a promise to accept every PostGIS type. The
[versioned contract and pinned PostGIS 3.6.1 source references](ewkb-compatibility-v1.md#evidence-and-scope)
record the evidence and approved differences. Standard `WkbDecoderStrategy`
continues to reject EWKB flags.

The [offline PostGIS interoperability corpus](https://github.com/longitude-one/spatial-decoder/tree/main/tests/Functional/Postgis/Ewkb)
records the tested combinations, exact bytes and reproducible generation SQL.

## MySQL spatial columns

Pass the raw binary column value to `MySQLDecoderStrategy`.
For a query such as `SELECT location FROM places WHERE id = 1`,
read the column as a binary string.

```php
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\MySQLDecoderStrategy;

$geometry = (new Decoder(new MySQLDecoderStrategy()))->decode($row['location']);
$srid = $geometry->getSrid();
```

The first four bytes carry the little-endian SRID for the entire geometry.
The strategy accepts the seven classic XY geometry types in little-endian
order. Only GeometryCollection may be empty. The usual `mode` and `mutable`
options apply throughout the result. Both Geometry and Geography preserve X/Y
order. A zero SRID decodes as zero.

For `SELECT ST_AsBinary(location)`, use `WkbDecoderStrategy`. That SQL
expression returns WKB without the column's SRID prefix.
