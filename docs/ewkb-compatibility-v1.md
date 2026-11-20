# EWKB decoding compatibility contract — v1

**Status: six Technical Architect decisions recorded on 2026-11-20; final
contract review pending.** This document addresses
[Story #78](https://github.com/longitude-one/spatial-decoder/issues/78).
It records the decisions listed in the approval record below, without introducing
an EWKB decoder or claiming blanket approval of every retained baseline rule.

EWKB is a PostGIS extension. Its flags and SRID rules are not standard WKB
requirements. Existing WKB decoding remains unchanged.

## Evidence and scope

PostGIS evidence is pinned to **3.6.1**, commit
`f533623505f73939a2d25d66ff252fb6619c4ac7`:

- [Binary reader](https://github.com/postgis/postgis/blob/f533623505f73939a2d25d66ff252fb6619c4ac7/liblwgeom/lwin_wkb.c):
  `lwtype_from_wkb_state`, `lwgeom_from_wkb_state`, `lwcollection_from_wkb_state`,
  `lwpoint_from_wkb_state`.
- [Flag definitions](https://github.com/postgis/postgis/blob/f533623505f73939a2d25d66ff252fb6619c4ac7/liblwgeom/liblwgeom.h.in):
  `WKBZOFFSET`, `WKBMOFFSET`, `WKBSRIDFLAG`.
- [SRID conversion](https://github.com/postgis/postgis/blob/f533623505f73939a2d25d66ff252fb6619c4ac7/liblwgeom/lwutil.c): `clamp_srid`.

These are source observations, not results from a running PostGIS server.
LongitudeOne evidence is the existing [WKB contract](decoding.md#wkb-decoding),
`WkbDecoderStrategy`, `WkbReader`, and the installed spatial-types
`Reference/SpatialReference.php`. The latter stores a PHP integer without an
additional SRID range check.

SQL/MM context was checked against the local **ISO/IEC CD 13249-3:201x(E)**,
clause 5.1.58 and Table 15. It is a Committee Draft, not a final published
edition. Its dimensional identifiers do not establish EWKB flag or SRID policy.
Its editor's note 4-406 leaves Triangle and PolyhedralSurface WKB productions
unspecified; those bodies retain the existing LongitudeOne compatibility rules.

The approval record identifies the six decisions explicitly approved by Alexandre.
Other rows retain the existing decoder baseline for review; they are not claimed
to have received separate or blanket Technical Architect approval.

## Header and dimensional decisions

Read one byte-order marker, one uint32 type word, then a uint32 SRID only when
`0x20000000` is set. The type and SRID use that geometry's byte order.
`0x80000000` means Z and `0x40000000` means M.

| Input | V1 outcome | Evidence or difference |
| --- | --- | --- |
| Ordinary supported WKB base code | Accept XY | Existing LongitudeOne; PostGIS |
| Base code plus Z, M or both flags | Accept XYZ, XYM or XYZM respectively | PostGIS extension |
| Supported base code plus 1000, 2000 or 3000 | Accept XYZ, XYM or XYZM respectively | Existing LongitudeOne; SQL/MM; PostGIS |
| Dimensional offset and no Z/M flags | Accept the offset layout | Existing LongitudeOne; PostGIS |
| Dimensional offset and identical nonzero flag layout | Accept redundant declaration | Approved decision 1; PostGIS accepts |
| Dimensional offset and different nonzero flag layout | Combine Z/M declarations and read the resulting layout | Approved decision 1; PostGIS dimension union |
| SRID flag with any accepted layout | Read SRID; preserve layout | PostGIS extension |
| `0x10000000` or any other unsupported type bits/value | Reject before construction | Approved decision 4; PostGIS masks the high nibble |
| Unknown base code, unsupported family or offset outside 0/1000/2000/3000 | Reject | Existing LongitudeOne subset |
| Missing bytes, byte-order marker outside 0/1, trailing bytes | Reject the complete input | Existing LongitudeOne |

Combine the Z/M booleans from flags and dimensional offsets using logical OR.
Offset zero adds neither Z nor M; flag-only declarations remain valid. Z plus
offset 1000 is XYZ; Z plus offset 2000 or 3000 is XYZM and requires four
ordinates. Do not reject a combination merely because the declarations differ.
SRID is independent of dimensional union.

## SRID decisions

| Location or value | V1 outcome | Evidence or difference |
| --- | --- | --- |
| Root without SRID field | Effective SRID 0 | Existing LongitudeOne default; PostGIS initial state |
| Root with explicit SRID 0 | Preserve 0 | Existing domain representation; PostGIS unknown SRID |
| Root with explicit SRID | Preserve the unsigned value within the range below | Approved decision 2; PostGIS uses `clamp_srid` |
| Member without SRID field | Inherit its immediate parent's effective SRID | Approved decision 3 |
| Member with SRID equal to parent | Accept, including explicit 0 when parent is 0 | Approved decision 3 |
| Member with SRID different from parent | Accept and preserve it, including explicit 0 under a nonzero parent | Approved decision 3 |
| Missing or incomplete flagged SRID field | Reject | Existing bounded-read behavior extended to SRID |

Approved accepted wire range: **0 through `min(4294967295, PHP_INT_MAX)`**.
On supported 64-bit PHP builds this is the complete unsigned 32-bit range.
The domain stores PHP integers; the wire field cannot represent values above
4294967295. Reject values unrepresentable by the runtime before any narrowing
conversion. Do not interpret the field as signed, clamp, wrap or query a CRS
registry. SRID metadata never transforms coordinates or changes axis order.

PostGIS reads the uint32 field through `clamp_srid(int32_t)`, maps nonpositive
values to its unknown SRID, and remaps values above its configured maximum.
Its reader shares SRID parse state across recursive calls and has a consistency
check TODO. V1 instead scopes inheritance to the immediate parent: an explicit
member SRID affects that member and its descendants without changing later
siblings. Distinct explicit SRIDs are preserved; no SRID consistency check,
normalization or repair is performed. The decoder does not call a validator,
and this contract makes no promise that an external validator checks SRID
consistency. Applications decide which validation they need.

Construction was checked with the installed spatial-types in Geometry and
Geography, mutable and immutable modes: GeometryCollection and MultiPoint
retain parent SRID 4326 alongside member SRIDs 3857 and 0. Preserve the graph
when building it; do not apply a later recursive `withSrid()` or propagating
`setSrid()` that would replace member metadata.

## Geometry and input decisions

| Topic | V1 outcome | Evidence or difference |
| --- | --- | --- |
| Byte order | Each root/member header selects 0 = big endian or 1 = little endian independently | Existing LongitudeOne; PostGIS |
| Simple families, all layouts | Point, LineString, Polygon, CircularString, Triangle | Existing LongitudeOne subset |
| Aggregate families, all layouts | MultiPoint, MultiLineString, MultiPolygon, GeometryCollection, CompoundCurve | Existing LongitudeOne subset |
| PolyhedralSurface | XYZ and XYZM only, including EMPTY | Existing LongitudeOne domain constraint |
| Other types | Reject CurvePolygon, MultiCurve, MultiSurface, TIN, abstract types and legacy aliases | Approved decision 6; existing WKB subset, narrower than PostGIS |
| Collection layout | Every member must declare exactly its parent's layout, recursively, including EMPTY | Existing LongitudeOne |
| Member types | Preserve existing Multi*, CompoundCurve and PolyhedralSurface member restrictions | Existing LongitudeOne |
| Point NaN ordinates | Preserve every ordinate, including mixed or all-NaN sequences; never convert NaN to EMPTY | Approved decision 5; differs from WKB and PostGIS |
| Other EMPTY values | Zero point/ring/member/patch count according to the existing WKB body | Existing LongitudeOne |
| Count-based EMPTY members | Preserve type, dimension, effective SRID, order and position | Existing LongitudeOne plus approved SRID policy |
| Input representation | One geometry in raw bytes only | Existing LongitudeOne strategy boundary |
| Hexadecimal, bytea text, MySQL wrappers | Explicit conversion outside the strategy; no auto-detection | Proposed EWKB boundary retaining WKB behavior |
| Malformed counts, truncation, extra bytes, incompatible members | Reject without partial output; strategy remains reusable | Existing LongitudeOne |
| Failure exception | `LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException` implementing `DecoderExceptionInterface` | Existing LongitudeOne binary contract |
| Output | Existing Geometry/Geography and mutable/immutable options apply throughout the graph | Existing LongitudeOne |
| Validation | Preserve constructible coordinates and structure; no topology checks or repair | Existing LongitudeOne |

NaN payload bytes need not survive construction as PHP floats; the decision
preserves NaN ordinate values and their positions, not IEEE-754 payload bits.
A Point `(NaN, NaN, 12)` remains XYZ with those three values, as does
`(NaN, NaN, NaN)`. Neither is automatically EMPTY. V1 defines no special
NaN-based Point EMPTY representation. Count-based EMPTY geometries remain
available. Existing WKB all-NaN-to-EMPTY conversion and mixed-NaN rejection
remain unchanged; EWKB must not silently reuse that conversion.

This decision does not add a public strategy class or approve its future API.
Reuse existing spatial-core enums and spatial-types contracts. No new domain
types, shared primitives, dependency changes or dependency on spatial-encoder
(including tests) are authorized here.

## Representative hexadecimal examples

These are manually constructed wire examples, **not PostGIS-generated fixtures**.
Spaces separate fields for readability; remove them before `hex2bin()`.
The acceptance column applies to a future EWKB strategy only.
Current `WkbDecoderStrategy` continues to reject EWKB flags.

| Example | Hexadecimal | V1 outcome |
| --- | --- | --- |
| XY Point (1 2), no SRID | `01 01000000 000000000000f03f 0000000000000040` | Accept, SRID 0 |
| XY Point (1 2), SRID 4326 | `01 01000020 e6100000 000000000000f03f 0000000000000040` | Accept, SRID 4326 |
| Explicit SRID 0 | `01 01000020 00000000 000000000000f03f 0000000000000040` | Accept, preserve 0 |
| Maximum uint32 SRID | `01 01000020 ffffffff 000000000000f03f 0000000000000040` | Accept on 64-bit PHP, SRID 4294967295 |
| Z flag, Point (1 2 3) | `01 01000080 000000000000f03f 0000000000000040 0000000000000840` | Accept XYZ |
| M flag, Point (1 2 3) | `01 01000040 000000000000f03f 0000000000000040 0000000000000840` | Accept XYM |
| ZM flags, Point (1 2 3 4) | `01 010000c0 000000000000f03f 0000000000000040 0000000000000840 0000000000001040` | Accept XYZM |
| Offset 1000 plus matching Z | `01 e9030080 000000000000f03f 0000000000000040 0000000000000840` | Accept redundant XYZ |
| Offset 2000 plus Z | `01 d1070080 000000000000f03f 0000000000000040 0000000000000840 0000000000001040` | Accept XYZM, four ordinates |
| Unknown high bit | `01 01000010 000000000000f03f 0000000000000040` | Reject unsupported bit |
| XY all-NaN, SRID 4326 | `01 01000020 e6100000 000000000000f87f 000000000000f87f` | Preserve two NaN ordinates |
| XYZ mixed NaN | `01 01000080 000000000000f87f 000000000000f87f 0000000000002840` | Preserve `(NaN, NaN, 12)` |
| XYZ all-NaN | `01 01000080 000000000000f87f 000000000000f87f 000000000000f87f` | Preserve three NaN ordinates |
| Missing flagged SRID | `01 01000020` | Reject truncation |

Nested examples use a little-endian GeometryCollection with SRID 4326 and one
big-endian Point (1 2), demonstrating independent byte order:

```text
# Member SRID absent: accept and inherit 4326.
01 07000020 e6100000 01000000
00 00000001 3ff0000000000000 4000000000000000

# Explicit matching member SRID: accept.
01 07000020 e6100000 01000000
00 20000001 000010e6 3ff0000000000000 4000000000000000

# Explicit member SRID 0: accept and preserve 0 under parent 4326.
01 07000020 e6100000 01000000
00 20000001 00000000 3ff0000000000000 4000000000000000

# Two siblings: explicit 3857, then absent SRID inherits parent 4326.
01 07000020 e6100000 02000000
00 20000001 00000f11 3ff0000000000000 4000000000000000
00 00000001 3ff0000000000000 4000000000000000
```

## Approval record

Alexandre explicitly approved these six decisions in the working conversation
on **2026-11-20**:

1. Combine flag and offset Z/M information as PostGIS does; differing declarations
   are accepted when the resulting coordinate sequence is readable.
2. Preserve the unsigned SRID exactly within the runtime's integer capacity,
   without PostGIS clamping.
3. Preserve every explicit member SRID, even when different from its parent;
   inherit the immediate parent only when the field is absent. No sibling leakage,
   SRID consistency validation, normalization or implicit validator call.
4. Reject unrecognized bits and types; do not mask `0x10000000`.
5. Preserve mixed and all-NaN ordinate sequences without automatic EMPTY
   conversion or rejection based on their mixture.
6. Retain the existing supported geometry families; reject CurvePolygon,
   MultiCurve, MultiSurface and TIN. Future support will be separate Stories
   after decoder v1; this change does not create them.

The remaining baseline rows are documented for final contract review, without
claiming additional approvals that were not discussed. No merge, Story Done
status or dependent EWKB implementation is authorized by this document.
