# Offline EWKB interoperability corpus

`corpus.json` contains actual PostGIS output and independently authored expected
spatial graphs. PHPUnit reads this file without PostgreSQL, Docker, network access
or an encoder. It does not generate its expected values by decoding the fixtures.

```bash
composer test-local -- --filter 'EwkbPostgisTest|EwkbRejectionMatrixTest'
```

## Provenance and reproduction

The corpus was generated on 2026-11-21 using **PostGIS 3.5.7** and **PostgreSQL 16.15**.
The complete server versions are stored in `corpus.json`. This is runtime
interoperability evidence; the compatibility contract separately pins its source
analysis to PostGIS 3.6.1.

Docker image used:

```text
postgis/postgis:16-3.5-alpine
postgis/postgis@sha256:47e961a569fd52ff31f0fe205ed91eeab17d9f5fff6722e6d7ea6b588748b293
```

The recorded image is amd64; specify `--platform linux/amd64` when reproducing it
on another architecture. No PostgreSQL port or persistent volume is needed.

```bash
docker run --detach --rm --platform linux/amd64 --name decoder-ewkb-fixtures \
  --env POSTGRES_HOST_AUTH_METHOD=trust \
  postgis/postgis@sha256:47e961a569fd52ff31f0fe205ed91eeab17d9f5fff6722e6d7ea6b588748b293
# Wait until this succeeds before running the export:
docker exec decoder-ewkb-fixtures pg_isready -U postgres

docker exec -i decoder-ewkb-fixtures psql -U postgres -X -qAt -v ON_ERROR_STOP=1 \
  < tests/Functional/Postgis/Ewkb/generate.sql > /tmp/decoder-ewkb-corpus.json
cmp tests/Functional/Postgis/Ewkb/corpus.json /tmp/decoder-ewkb-corpus.json
docker stop decoder-ewkb-fixtures
```

Alternatively run `psql -X -qAt -v ON_ERROR_STOP=1 -f generate.sql` against an
existing database with PostGIS installed. Connection settings follow normal
`psql` options or environment variables. Version metadata will differ on another
server; inspect the fixture records separately before replacing the export.
Generation and live comparison are manual operations, separate from PHPUnit.
The SQL uses a transaction and a temporary-schema function; it writes no source
tables and rolls back after the export.

## Coverage

The 240 rows combine 15 families, four dimensions, SRID 0/4326, and EMPTY/populated.
Each row contains exact NDR and XDR bytes from both `ST_AsEWKB` and `ST_AsBinary`.
NDR is little endian; XDR is big endian. `ST_AsBinary` omits SRID metadata.

| Geometry families | XY | XYZ | XYM | XYZM |
| --- | --- | --- | --- | --- |
| Point, LineString, Polygon, CircularString, Triangle | Accept | Accept | Accept | Accept |
| MultiPoint, MultiLineString, MultiPolygon, GeometryCollection, CompoundCurve | Accept | Accept | Accept | Accept |
| PolyhedralSurface | Reject | Accept | Reject | Accept |
| CurvePolygon, MultiCurve, MultiSurface, TIN | Reject | Reject | Reject | Reject |

This gives **168 accepted rows / 336 EWKB byte sequences** and
**72 rejected rows / 144 EWKB byte sequences**. The same WKB matrix is exercised.
Every accepted EWKB graph is checked in Geometry/Geography and mutable/immutable
modes, including generated rings and vertices. Tests check type, dimension,
SRID, child order and counts, every coordinate, and nested EMPTY positions.
The coverage test requires every matrix key exactly once per byte order.

`generate.sql` contains the exact source WKT and authored XY expected trees.
It applies Z = 12.5 and M = -7.25 explicitly, including dimensioned EMPTY values,
and records the resulting EWKT for inspection. Expected trees are transformed
as JSON independently of the PostGIS binary writer and the PHP decoder.
The populated cases include polygon holes, multiple siblings, nested collections,
EMPTY aggregate members and linear/circular CompoundCurve parts.

WKB/EWKB pairs are compared with strict coordinate equality. When SRID is absent,
the complete objects are also compared. Canonical EWKB carrying extension flags
must fail through the WKB strategy. The existing WKB, legacy WKB and previous
PostGIS suites remain part of the full test run.

## Noncanonical and malformed inputs

Canonical output cannot exercise every accepted header or rejected input.
Manually constructed tests complement it:

| Test class | Additional contract evidence |
| --- | --- |
| `EwkbDimensionTest` | All flag/offset unions, redundant declarations, all-NaN EMPTY and mixed-NaN rejection |
| `EwkbDecoderStrategyTest` | Explicit zero and maximum uint32 SRIDs, raw-byte boundary and strategy reuse |
| `EwkbGraphTest` | Opposite member byte orders, conflicting/absent SRIDs, immediate-parent inheritance without sibling leakage |
| `EwkbDimensionStructureTest` | Dimensioned EMPTY values and unavailable surface layouts |
| `EwkbRejectionMatrixTest` | Every truncated prefix in all supported families/layouts and both byte orders, root/nested impossible counts and identifiers |

The rejection matrix also checks invalid markers, incompatible members, inconsistent
EMPTY dimensions and trailing hostile bytes. It exercises nested failures after a
completed sibling, the established public `InvalidArgumentException` implementing
`DecoderExceptionInterface`, safe feature diagnostics, unchanged previously
returned coordinates and successful object-equivalent decoding after each failure.
These wire tests are identified as manually constructed, never as PostGIS exports.

## Contract and vendor references

- [LongitudeOne EWKB compatibility contract](../../../../docs/ewkb-compatibility-v1.md).
- [PostGIS ST_AsEWKB](https://postgis.net/docs/ST_AsEWKB.html).
- [PostGIS ST_AsBinary](https://postgis.net/docs/ST_AsBinary.html).

EWKB is a PostGIS extension. The corpus verifies interoperability and the approved
LongitudeOne subset; it does not establish normative WKB requirements or expand
support to every family that PostGIS can serialize.
