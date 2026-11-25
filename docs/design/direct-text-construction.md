# Direct text construction experiment

PHP 8.5.13 (cli) (built: Nov 17 2026 14:30:51) (NTS)

Each cell lists three elapsed times in milliseconds and median peak PHP heap in MiB.
Each sample starts a fresh PHP process. The XY fixture contains 256 polygons and 1,024 positions.
The collection wraps the MultiPolygon in two collections. EWKT supplies SRID 4326.

Reproduce one sample with `php docs/design/benchmark-text-construction.php direct ewkt
collection mutable`.
Use the path, format, shape and output names below as arguments.

| Format | Shape | Output | baseline (ms / MiB) | direct (ms / MiB) |
| --- | --- | --- | --- | --- |
| wkt | polygon | default | 10.0, 9.6, 9.2 / 3.74 | 11.3, 10.8, 10.8 / 3.18 |
| wkt | polygon | mutable | 17.0, 16.0, 17.1 / 4.05 | 11.1, 10.7, 10.5 / 3.14 |
| wkt | polygon | geography | 15.6, 15.5, 16.2 / 3.99 | 11.0, 11.0, 10.8 / 3.18 |
| wkt | collection | default | 11.0, 10.7, 11.0 / 3.74 | 12.9, 12.9, 13.6 / 3.28 |
| wkt | collection | mutable | 19.1, 19.2, 19.0 / 4.13 | 12.7, 12.3, 12.6 / 3.24 |
| wkt | collection | geography | 19.4, 19.3, 18.8 / 4.01 | 13.0, 13.4, 13.0 / 3.28 |
| ewkt | polygon | default | 10.0, 9.8, 10.1 / 3.81 | 10.8, 10.8, 10.9 / 3.18 |
| ewkt | polygon | mutable | 17.0, 16.8, 16.6 / 4.54 | 10.6, 10.9, 11.1 / 3.14 |
| ewkt | polygon | geography | 16.0, 15.8, 16.3 / 4.38 | 10.8, 10.7, 10.8 / 3.18 |
| ewkt | collection | default | 11.9, 11.1, 11.0 / 3.84 | 13.1, 13.2, 16.2 / 3.28 |
| ewkt | collection | mutable | 19.9, 19.2, 19.7 / 4.65 | 12.4, 12.6, 12.2 / 3.24 |
| ewkt | collection | geography | 19.9, 19.3, 19.5 / 4.48 | 13.0, 13.4, 12.9 / 3.28 |

## Decision

Retain the production construction path. Across all four default-output scenarios the
prototype took longer in every sample, even with its simplified dispatch. Mutable and
Geography samples consistently ran faster and used less peak memory. The prototype uses
the production coordinate reader but assumes the fixture grammar, member count and SRID.
Its timings include fewer validation and dispatch steps. Coordinate hashes agree for
these fixtures. The results quantify an opportunity on these inputs, but do not
establish the gain of a contract-equivalent parser across the supported types and
layouts.
