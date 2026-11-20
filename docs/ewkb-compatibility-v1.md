# EWKB decoding compatibility contract — v1 proposal

**Status: proposed; Technical Architect approval pending.** This document prepares
[Story #78](https://github.com/longitude-one/spatial-decoder/issues/78).
It does not approve the contract or introduce an EWKB decoder. Dependent
implementation remains blocked until Alexandre approves the decisions below.

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

All outcomes below are **proposed LongitudeOne decisions**, including rules
which retain existing WKB behavior or agree with PostGIS. Approval is required
for the entire v1 contract, not only the differences.

## Header and dimensional decisions

Read one byte-order marker, one uint32 type word, then a uint32 SRID only when
`0x20000000` is set. The type and SRID use that geometry's byte order.
`0x80000000` means Z and `0x40000000` means M.

| Input | Proposed v1 outcome | Evidence or difference |
| --- | --- | --- |
| Ordinary supported WKB base code | Accept XY | Existing LongitudeOne; PostGIS |
| Base code plus Z, M or both flags | Accept XYZ, XYM or XYZM respectively | PostGIS extension |
| Supported base code plus 1000, 2000 or 3000 | Accept XYZ, XYM or XYZM respectively | Existing LongitudeOne; SQL/MM; PostGIS |
| Dimensional offset and no Z/M flags | Accept the offset layout | Existing LongitudeOne; PostGIS |
| Dimensional offset and identical nonzero flag layout | Accept redundant declaration | Proposed compatibility choice; PostGIS accepts |
| Dimensional offset and different nonzero flag layout | Reject, including partial overlaps | Proposed stricter choice; PostGIS combines Z/M information |
| SRID flag with any accepted layout | Read SRID; preserve layout | PostGIS extension |
| `0x10000000` or any other unsupported type bits/value | Reject before construction | Proposed stricter choice; PostGIS masks the high nibble |
| Unknown base code, unsupported family or offset outside 0/1000/2000/3000 | Reject | Existing LongitudeOne subset |
| Missing bytes, byte-order marker outside 0/1, trailing bytes | Reject the complete input | Existing LongitudeOne |

Offset zero does not conflict with Z/M flags: the flag-only form is normal EWKB.
For nonzero offsets, compare the complete Z/M layouts rather than combining
them. Thus Z plus offset 1000 is accepted; Z plus offset 2000 or 3000 is rejected.
SRID alone is independent of this comparison.

## SRID decisions

| Location or value | Proposed v1 outcome | Evidence or difference |
| --- | --- | --- |
| Root without SRID field | Effective SRID 0 | Existing LongitudeOne default; PostGIS initial state |
| Root with explicit SRID 0 | Preserve 0 | Existing domain representation; PostGIS unknown SRID |
| Root with explicit SRID | Preserve the unsigned value within the range below | Proposed choice; PostGIS uses `clamp_srid` |
| Member without SRID field | Inherit its immediate parent's effective SRID | Proposed deterministic rule |
| Member with SRID equal to parent | Accept, including explicit 0 when parent is 0 | Proposed compatibility choice |
| Member with SRID different from parent | Reject, including explicit 0 under a nonzero parent | Proposed consistency rule |
| Missing or incomplete flagged SRID field | Reject | Existing bounded-read behavior extended to SRID |

Proposed accepted wire range: **0 through `min(4294967295, PHP_INT_MAX)`**.
On supported 64-bit PHP builds this is the complete unsigned 32-bit range.
The domain stores PHP integers; the wire field cannot represent values above
4294967295. Reject values unrepresentable by the runtime before any narrowing
conversion. Do not interpret the field as signed, clamp, wrap or query a CRS
registry. SRID metadata never transforms coordinates or changes axis order.

PostGIS reads the uint32 field through `clamp_srid(int32_t)`, maps nonpositive
values to its unknown SRID, and remaps values above its configured maximum.
Its reader shares SRID parse state across recursive calls and has a consistency
check TODO. This does not establish parent-scoped inheritance or rejection of
conflicting members. V1 deliberately specifies both rather than copying that
stateful behavior, which can let a member affect later siblings.

## Geometry and input decisions

| Topic | Proposed v1 outcome | Evidence or difference |
| --- | --- | --- |
| Byte order | Each root/member header selects 0 = big endian or 1 = little endian independently | Existing LongitudeOne; PostGIS |
| Simple families, all layouts | Point, LineString, Polygon, CircularString, Triangle | Existing LongitudeOne subset |
| Aggregate families, all layouts | MultiPoint, MultiLineString, MultiPolygon, GeometryCollection, CompoundCurve | Existing LongitudeOne subset |
| PolyhedralSurface | XYZ and XYZM only, including EMPTY | Existing LongitudeOne domain constraint |
| Other types | Reject CurvePolygon, MultiCurve, MultiSurface, TIN, abstract types and legacy aliases | Existing LongitudeOne WKB subset; narrower than PostGIS |
| Collection layout | Every member must declare exactly its parent's layout, recursively, including EMPTY | Existing LongitudeOne |
| Member types | Preserve existing Multi*, CompoundCurve and PolyhedralSurface member restrictions | Existing LongitudeOne |
| Point EMPTY | All declared ordinates NaN; reject mixed NaN/non-NaN | Existing LongitudeOne; stricter than PostGIS's X/Y NaN check |
| Other EMPTY values | Zero point/ring/member/patch count according to the existing WKB body | Existing LongitudeOne |
| EMPTY members | Preserve type, dimension, effective SRID, order and position | Existing LongitudeOne plus proposed SRID policy |
| Input representation | One geometry in raw bytes only | Existing LongitudeOne strategy boundary |
| Hexadecimal, bytea text, MySQL wrappers | Explicit conversion outside the strategy; no auto-detection | Proposed EWKB boundary retaining WKB behavior |
| Malformed counts, truncation, extra bytes, incompatible members | Reject without partial output; strategy remains reusable | Existing LongitudeOne |
| Failure exception | `LongitudeOne\SpatialDecoder\Exception\InvalidArgumentException` implementing `DecoderExceptionInterface` | Existing LongitudeOne binary contract |
| Output | Existing Geometry/Geography and mutable/immutable options apply throughout the graph | Existing LongitudeOne |
| Validation | Preserve constructible coordinates and structure; no topology checks or repair | Existing LongitudeOne |

This decision does not add a public strategy class or approve its future API.
Reuse existing spatial-core enums and spatial-types contracts. No new domain
types, shared primitives, dependency changes or dependency on spatial-encoder
(including tests) are authorized here.

## Representative hexadecimal examples

These are manually constructed wire examples, **not PostGIS-generated fixtures**.
Spaces separate fields for readability; remove them before `hex2bin()`.
The proposed acceptance column applies to a future EWKB strategy only.
Current `WkbDecoderStrategy` continues to reject EWKB flags.

| Example | Hexadecimal | Proposed outcome |
| --- | --- | --- |
| XY Point (1 2), no SRID | `01 01000000 000000000000f03f 0000000000000040` | Accept, SRID 0 |
| XY Point (1 2), SRID 4326 | `01 01000020 e6100000 000000000000f03f 0000000000000040` | Accept, SRID 4326 |
| Explicit SRID 0 | `01 01000020 00000000 000000000000f03f 0000000000000040` | Accept, preserve 0 |
| Maximum uint32 SRID | `01 01000020 ffffffff 000000000000f03f 0000000000000040` | Accept on 64-bit PHP, SRID 4294967295 |
| Z flag, Point (1 2 3) | `01 01000080 000000000000f03f 0000000000000040 0000000000000840` | Accept XYZ |
| M flag, Point (1 2 3) | `01 01000040 000000000000f03f 0000000000000040 0000000000000840` | Accept XYM |
| ZM flags, Point (1 2 3 4) | `01 010000c0 000000000000f03f 0000000000000040 0000000000000840 0000000000001040` | Accept XYZM |
| Offset 1000 plus matching Z | `01 e9030080 000000000000f03f 0000000000000040 0000000000000840` | Accept redundant XYZ |
| Offset 2000 plus conflicting Z | `01 d1070080 000000000000f03f 0000000000000040 0000000000000840 0000000000001040` | Reject conflict |
| Unknown high bit | `01 01000010 000000000000f03f 0000000000000040` | Reject unsupported bit |
| XY EMPTY, SRID 4326 | `01 01000020 e6100000 000000000000f87f 000000000000f87f` | Accept EMPTY |
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

# Explicit member SRID 0: reject conflict with parent 4326.
01 07000020 e6100000 01000000
00 20000001 00000000 3ff0000000000000 4000000000000000
```

## Approval record

Pending: Alexandre must approve or amend the complete v1 tables, specifically
the strict flag/offset comparison, unsigned SRID range, parent-scoped SRID
inheritance and conflict rejection, unsupported-bit rejection, and retained
geometry/EMPTY/input/error subset. No approval has been recorded.

Alternative choices include PostGIS-style dimension union, PostGIS SRID
clamping, or rejection of every redundant dimensional declaration. They change
which bytes are accepted or the information preserved and must not be selected
silently during dependent implementation.
