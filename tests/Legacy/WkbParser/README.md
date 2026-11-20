# Legacy WKB parser compatibility tests

This suite tracks historical WKB parser scenarios through the public Decoder API.
Use this inventory to distinguish preserved binary behavior from explicit
representation exclusions and adaptations to the current object model.

## Provenance

The fixtures come from `longitude-one/wkb-parser` commit
[`86bfca8ec4e94190f5107c0dbb1b98f7ea27a2ae`](https://github.com/longitude-one/wkb-parser/tree/86bfca8ec4e94190f5107c0dbb1b98f7ea27a2ae).
The complete historical test directory contains
[ParserTest.php](https://github.com/longitude-one/wkb-parser/blob/86bfca8ec4e94190f5107c0dbb1b98f7ea27a2ae/tests/LongitudeOne/Geo/WKB/Tests/ParserTest.php)
and
[ReaderTest.php](https://github.com/longitude-one/wkb-parser/blob/86bfca8ec4e94190f5107c0dbb1b98f7ea27a2ae/tests/LongitudeOne/Geo/WKB/Tests/ReaderTest.php).
The original [MIT license](LICENSE) accompanies the adapted data.

[fixtures.json](fixtures.json) preserves all 182 `goodBinaryData` entries,
their original hex strings and expected arrays, plus all 15 `badBinaryData`
entries with original exception class names and messages. Bad binary inputs are
stored as equivalent hex strings, including the exact truncated bytes.
The added `disposition` field records the migration decision per success fixture.
No legacy parser code, Composer dependency, or network access is required to run these tests.

## Approved contract and adaptations

These tests implement [Story #64](https://github.com/longitude-one/spatial-decoder/issues/64)
under [Epic #5](https://github.com/longitude-one/spatial-decoder/issues/5).
They use `Decoder(new WkbDecoderStrategy())` exclusively.
The binary-only contract and EWKB exclusions were established in
[Story #57](https://github.com/longitude-one/spatial-decoder/issues/57);
the supported types follow Stories #59–#62, and consistent rejection follows
[Story #63](https://github.com/longitude-one/spatial-decoder/issues/63).
These are compatibility tests, not independent evidence of normative compliance.
New architecture and normative fixtures remain in `tests/Unit/Strategy/`.

- **Preserved (40 fixtures):** unchanged binary bytes and coordinate values,
  type, order, EMPTY values and dimensions. Historical result arrays become
  immutable spatial objects. Absent SRID `null` becomes the existing default `0`.
  Integer literals in expected coordinates become exact doubles when compared
  with decoded binary doubles; assertions use strict equality without tolerance.
  GeometryCollection and CompoundCurve member type tags are checked on child
  objects, together with their coordinates, dimension, SRID and member count.
- **EWKB (134 fixtures):** unchanged bytes now expect `InvalidArgumentException`.
  Z/M/SRID bit flags remain outside the WKB strategy. They are not rewritten into
  SQL/MM offsets. This records an intentional migration exclusion, not support
  for historical EWKB behavior. Some names ending in `WithSrid` contain no SRID
  flag; classification follows bytes, not names. In particular,
  `ndrPointValueWithSrid` contains plain XY WKB and remains a success.
- **Unsupported types (8 fixtures without EWKB flags):** CurvePolygon, MultiCurve
  and MultiSurface now expect `InvalidArgumentException` under the existing type
  allow-list. EWKB variants of these types are classified as EWKB first.
- **Malformed inputs (15 fixtures, two invocation styles):** original bytes
  still fail. Legacy `UnexpectedValueException`/`InvalidArgumentException`
  namespaces and reader-specific diagnostic messages are replaced by the current
  decoder's `InvalidArgumentException`. Original messages remain in the fixture
  file as provenance; platform-dependent `unpack()` wording is not a decoder API
  promise. An EWKB or unsupported root may reject before the historical inner
  mismatch is reached; these cases assert rejection, not equivalent error location.
- **Hex forms (all 182 fixtures, five forms each):** raw hex and prefixes `x`,
  `X`, `0x`, `0X` now expect rejection. They are never silently decoded as bytes.
  A subsequent valid binary decode verifies reuse after every rejection.
- **Construction and reuse:** `Parser($input)->parse()` becomes an explicit
  `Decoder::decode($input)`. `testReusedParser` reuses one decoder across the
  40 compatible binary fixtures. Unsupported binary cases remain executable
  rejection tests; every hex reuse form is exercised by `testLegacyHexForms`.

No source scenario uses a MySQL SRID-prefixed binary wrapper. MySQL binary is
outside this migration and remains covered as rejection in the separate new
strategy tests. No runtime behavior, public API, or representation support is changed.

## Parser scenario inventory

Each row below maps one original provider entry. For every row, the five
historical methods `testParserRawHex`, `testParserPrependLowerXHex`,
`testParserPrependUpperXHex`, `testParserPrependLower0XHex` and
`testParserPrependUpper0XHex` map to `testLegacyHexForms` with that same dataset
name (five rejection checks). Binary mappings are listed separately below.
The standalone `testReusedParser` maps as described above.

| Original `goodBinaryData` dataset | Binary test | Reason |
| --- | --- | --- |
| `ndrEmptyPointValue` | `testParserBinary` | preserved |
| `ndrPointValue` | `testParserBinary` | preserved |
| `xdrPointValue` | `testParserBinary` | preserved |
| `ndrPointZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointZOGCValue` | `testParserBinary` | preserved |
| `ndrPointMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrEmptyPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrEmptyPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPointValueWithSrid` | `testParserBinary` | preserved |
| `xdrPointValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPointZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPointMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrEmptyPointZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPointZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPointZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrEmptyLineStringValue` | `testParserBinary` | preserved |
| `ndrLineStringValue` | `testParserBinary` | preserved |
| `xdrLineStringValue` | `testParserBinary` | preserved |
| `ndrLineStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrLineStringZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrLineStringZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrEmptyPolygonValue` | `testParserBinary` | preserved |
| `ndrPolygonValue` | `testParserBinary` | preserved |
| `xdrPolygonValue` | `testParserBinary` | preserved |
| `ndrPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonValue` | `testParserBinary` | preserved |
| `xdrMultiRingPolygonValue` | `testParserBinary` | preserved |
| `ndrMultiRingPolygonZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiRingPolygonZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiRingPolygonZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointValue` | `testParserBinary` | preserved |
| `xdrMultiPointValue` | `testParserBinary` | preserved |
| `ndrMultiPointZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPointZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPointZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringValue` | `testParserBinary` | preserved |
| `xdrMultiLineStringValue` | `testParserBinary` | preserved |
| `ndrMultiLineStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiLineStringZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiLineStringZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonValue` | `testParserBinary` | preserved |
| `xdrMultiPolygonValue` | `testParserBinary` | preserved |
| `ndrMultiPolygonZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiPolygonZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiPolygonZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrEmptyGeometryCollectionValue` | `testParserBinary` | preserved |
| `ndrGeometryCollectionValueWithEmptyPoint` | `testParserBinary` | preserved |
| `ndrGeometryCollectionValue` | `testParserBinary` | preserved |
| `xdrGeometryCollectionValue` | `testParserBinary` | preserved |
| `ndrGeometryCollectionZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionZValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrGeometryCollectionZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionZMValueWithSrid` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCircularStringValue` | `testParserBinary` | preserved |
| `xdrCircularStringValue` | `testParserBinary` | preserved |
| `ndrCircularStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCircularStringZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCircularStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCircularStringMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCircularStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCircularStringZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCompoundCurveValue` | `testParserBinary` | preserved |
| `xdrCompoundCurveValue` | `testParserBinary` | preserved |
| `ndrCompoundCurveZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCompoundCurveZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCompoundCurveMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCompoundCurveMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCompoundCurveZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCompoundCurveZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCurvePolygonValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `ndrCurvePolygonCompoundCurveValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `xdrCurvePolygonCompoundCurveValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `ndrCurvePolygonZCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCurvePolygonZCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCurvePolygonMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCurvePolygonMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrCurvePolygonZMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrCurvePolygonZMVCompoundCurvealue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiCurveValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `ndrMultiCurveCompoundCurveValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `xdrMultiCurveCompoundCurveValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `ndrMultiCurveZCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiCurveZCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiCurveMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiCurveMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiCurveZMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiCurveZMCompoundCurveValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiSurfaceValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `xdrMultiSurfaceValue` | `testUnsupportedLegacyBinary` | unsupported type |
| `ndrMultiSurfaceZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiSurfaceZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiSurfaceMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiSurfaceMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrMultiSurfaceZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrMultiSurfaceZMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPolyhedralSurfaceZValue` | `testUnsupportedLegacyBinary` | EWKB |
| `ndrPolyhedralSurfaceMValue` | `testUnsupportedLegacyBinary` | EWKB |
| `xdrGeometryCollectionValue2` | `testParserBinary` | preserved |
| `xdrMultiPointValue2` | `testParserBinary` | preserved |
| `xdrMultiLineStringValue2` | `testParserBinary` | preserved |
| `xdrMultiPolygonValue2` | `testParserBinary` | preserved |
| `xdrMultiPointZOGCValue` | `testParserBinary` | preserved |
| `xdrMultiLineStringZOGCValue` | `testParserBinary` | preserved |
| `xdrMultiPolygonZOGCValue` | `testParserBinary` | preserved |
| `xdrPointValue2` | `testParserBinary` | preserved |
| `xdrLineStringValue2` | `testParserBinary` | preserved |
| `xdrPolygonValue2` | `testParserBinary` | preserved |
| `xdrPointZOGCValue2` | `testParserBinary` | preserved |
| `xdrLineStringZOGCValue` | `testParserBinary` | preserved |
| `xdrPolygonZOGCValue` | `testParserBinary` | preserved |

Every `badBinaryData` row maps to both `testBadBinaryData` and
`testBadBinaryDataWithPreparedParser`, preserving its dataset name:

| Dataset | Migration |
| --- | --- |
| `badByteOrder` | Both rejection tests; current exception API |
| `badSimpleType` | Both rejection tests; current exception API |
| `shortNDRPoint` | Both rejection tests; current exception API |
| `badPointSize` | Both rejection tests; current exception API |
| `badPointInMultiPoint` | Both rejection tests; current exception API |
| `unexpectedLineStringInMultiPoint` | Both rejection tests; current exception API |
| `badLineStringInMultiLineString` | Both rejection tests; current exception API |
| `badPolygonInMultiPolygon` | Both rejection tests; current exception API |
| `badCircularStringInCompoundCurve` | Both rejection tests; current exception API |
| `unexpectedPointInCompoundCurve` | Both rejection tests; current exception API |
| `badCompoundCurveInCurvePolygon` | Both rejection tests; current exception API |
| `badCircularStringInCurvePolygon` | Both rejection tests; current exception API |
| `unexpectedPolygonInMultiCurve` | Both rejection tests; current exception API |
| `unexpectedPointInMultiSurface` | Both rejection tests; current exception API |
| `unexpectedPointInPolyhedralSurface` | Both rejection tests; current exception API |

## Excluded internal API scenarios

`ParserTest::testGeometryType` checks legacy class constants rather than
public decoding. Every `wkbGeometryType` dataset below is excluded for that
reason; importing these constants would introduce the old parser API into the
new suite. Type decoding itself is exercised through geometry fixtures.

- `wkbPoint`: legacy constant identity; excluded.
- `wkbLineString`: legacy constant identity; excluded.
- `wkbPolygon`: legacy constant identity; excluded.
- `wkbMultiPoint`: legacy constant identity; excluded.
- `wkbMultiLineString`: legacy constant identity; excluded.
- `wkbMultiPolygon`: legacy constant identity; excluded.
- `wkbGeometryCollection`: legacy constant identity; excluded.
- `wkbPointZ`: legacy constant identity; excluded.
- `wkbLineStringZ`: legacy constant identity; excluded.
- `wkbPolygonZ`: legacy constant identity; excluded.
- `wkbMultiPointZ`: legacy constant identity; excluded.
- `wkbMultiLineStringZ`: legacy constant identity; excluded.
- `wkbMultiPolygonZ`: legacy constant identity; excluded.
- `wkbGeometryCollectionZ`: legacy constant identity; excluded.
- `wkbPointM`: legacy constant identity; excluded.
- `wkbLineStringM`: legacy constant identity; excluded.
- `wkbPolygonM`: legacy constant identity; excluded.
- `wkbMultiPointM`: legacy constant identity; excluded.
- `wkbMultiLineStringM`: legacy constant identity; excluded.
- `wkbMultiPolygonM`: legacy constant identity; excluded.
- `wkbGeometryCollectionM`: legacy constant identity; excluded.
- `wkbPointZM`: legacy constant identity; excluded.
- `wkbLineStringZM`: legacy constant identity; excluded.
- `wkbPolygonZM`: legacy constant identity; excluded.
- `wkbMultiPointZM`: legacy constant identity; excluded.
- `wkbMultiLineStringZM`: legacy constant identity; excluded.
- `wkbMultiPolygonZM`: legacy constant identity; excluded.
- `wkbGeometryCollectionZM`: legacy constant identity; excluded.
- `wkbPointSRID`: legacy constant identity; excluded.
- `wkbLineStringSRID`: legacy constant identity; excluded.
- `wkbPolygonSRID`: legacy constant identity; excluded.
- `wkbMultiPointSRID`: legacy constant identity; excluded.
- `wkbMultiLineStringSRID`: legacy constant identity; excluded.
- `wkbMultiPolygonSRID`: legacy constant identity; excluded.
- `wkbGeometryCollectionSRID`: legacy constant identity; excluded.
- `wkbPointSRIDZ`: legacy constant identity; excluded.
- `wkbLineStringSRIDZ`: legacy constant identity; excluded.
- `wkbPolygonSRIDZ`: legacy constant identity; excluded.
- `wkbMultiPointSRIDZ`: legacy constant identity; excluded.
- `wkbMultiLineStringSRIDZ`: legacy constant identity; excluded.
- `wkbMultiPolygonSRIDZ`: legacy constant identity; excluded.
- `wkbGeometryCollectionSRIDZ`: legacy constant identity; excluded.
- `wkbPointSRIDM`: legacy constant identity; excluded.
- `wkbLineStringSRIDM`: legacy constant identity; excluded.
- `wkbPolygonSRIDM`: legacy constant identity; excluded.
- `wkbMultiPointSRIDM`: legacy constant identity; excluded.
- `wkbMultiLineStringSRIDM`: legacy constant identity; excluded.
- `wkbMultiPolygonSRIDM`: legacy constant identity; excluded.
- `wkbGeometryCollectionSRIDM`: legacy constant identity; excluded.
- `wkbPointSRIDZM`: legacy constant identity; excluded.
- `wkbLineStringSRIDZM`: legacy constant identity; excluded.
- `wkbPolygonSRIDZM`: legacy constant identity; excluded.
- `wkbMultiPointSRIDZM`: legacy constant identity; excluded.
- `wkbMultiLineStringSRIDZM`: legacy constant identity; excluded.
- `wkbMultiPolygonSRIDZM`: legacy constant identity; excluded.
- `wkbGeometryCollectionSRIDZM`: legacy constant identity; excluded.

`ReaderTest` exercises standalone byte fragments, reader methods, cursor
positions, byte-order initialization and `load()` state. None returns a spatial
object through the public Decoder API. These tests are explicitly excluded,
without transplanting or claiming equivalent internal reader coverage.
Their malformed geometry equivalents are covered separately by new strategy
structure tests; that does not make these fragment assertions migrated tests.

| Original provider | Dataset | Reason for exclusion |
| --- | --- | --- |
| `goodTestData` / `testGood` | `readBinaryByteOrder` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readHexByteOrder` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readPrefixedHexByteOrder` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readNDRBinaryLong` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRBinaryLong` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readNDRHexLong` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRHexLong` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readNDRBinaryFloat` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRBinaryFloat` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readNDRHexFloat` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRHexFloat` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRBinaryFloats` | Internal reader fragment or lifecycle API |
| `goodTestData` / `testGood` | `readXDRPosition` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readNullPackage` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readEmptyPackage` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readBinaryBadByteOrder` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readBinaryWithoutByteOrder` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readHexWithoutByteOrder` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readBinaryShortFloat` | Internal reader fragment or lifecycle API |
| `badTestData` / `testBad` | `readHexShortFloat` | Internal reader fragment or lifecycle API |

`ReaderTest::testReaderReuse` is also excluded: it reloads binary/hex byte-order
and single-double fragments, which cannot be decoded as geometries. Decoder
reuse is tested independently above rather than presented as equivalent reader behavior.

## Execution

Both `phpunit.dist.xml` and `phpunit.ci.xml` discover `tests/` recursively.
The existing CI workflow runs `composer test` or `composer coverage-ci` using
those configurations, so the new directory requires no suite or workflow change.

```bash
vendor/bin/phpunit --no-coverage tests/Legacy/WkbParser
composer test
composer check
```

Local execution verifies discovery and behavior. A successful remote CI run
must still be observed on the published revision before claiming CI success.
