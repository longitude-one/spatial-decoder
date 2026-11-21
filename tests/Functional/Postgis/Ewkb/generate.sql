-- PostGIS interoperability evidence only; EWKB is a PostGIS extension.
-- Run with psql -X -qAt -v ON_ERROR_STOP=1 -f generate.sql > corpus.json
-- No permanent objects or source-table writes. Expected graphs below are authored
-- independently of ST_AsEWKB and the PHP decoder, including rings and vertices.
BEGIN;
CREATE FUNCTION pg_temp.expected_node(base jsonb, layout text, reference integer)
RETURNS jsonb LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE
    result jsonb := base || jsonb_build_object('dimension', layout, 'srid', reference);
    ordinates jsonb := base->'coordinates';
    members jsonb;
BEGIN
    IF base->>'type' = 'POINT' THEN
        IF jsonb_array_length(ordinates) > 0 THEN
            IF layout IN ('XYZ', 'XYZM') THEN
                ordinates := ordinates || '[12.5]'::jsonb;
            END IF;
            IF layout IN ('XYM', 'XYZM') THEN
                ordinates := ordinates || '[-7.25]'::jsonb;
            END IF;
        END IF;
        RETURN result || jsonb_build_object('coordinates', ordinates);
    END IF;
    SELECT coalesce(jsonb_agg(pg_temp.expected_node(value, layout, reference) ORDER BY ordinal), '[]'::jsonb)
      INTO members FROM jsonb_array_elements(base->'children') WITH ORDINALITY AS children(value, ordinal);
    RETURN result || jsonb_build_object('children', members);
END;
$$;
WITH examples(type, wkt, expected) AS (
VALUES
    ('POINT', 'POINT(-3.125 44.0625)', '{"type":"POINT","coordinates":[-3.125,44.0625]}'::jsonb),
    ('LINESTRING', 'LINESTRING(0 0,4 0,0 4)', '{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]}]}'::jsonb),
    ('POLYGON', 'POLYGON((0 0,4 0,0 4,0 0),(1 1,1 2,2 1,1 1))', '{"type":"POLYGON","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[0,0]}]},{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[1,1]},{"type":"POINT","coordinates":[1,2]},{"type":"POINT","coordinates":[2,1]},{"type":"POINT","coordinates":[1,1]}]}]}'::jsonb),
    ('MULTIPOINT', 'MULTIPOINT(EMPTY,(0 0),(4 0))', '{"type":"MULTIPOINT","children":[{"type":"POINT","coordinates":[]},{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]}]}'::jsonb),
    ('MULTILINESTRING', 'MULTILINESTRING(EMPTY,(0 0,4 0,0 4))', '{"type":"MULTILINESTRING","children":[{"type":"LINESTRING","children":[]},{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]}]}]}'::jsonb),
    ('MULTIPOLYGON', 'MULTIPOLYGON(EMPTY,((0 0,4 0,0 4,0 0)))', '{"type":"MULTIPOLYGON","children":[{"type":"POLYGON","children":[]},{"type":"POLYGON","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[0,0]}]}]}]}'::jsonb),
    ('GEOMETRYCOLLECTION', 'GEOMETRYCOLLECTION(POINT EMPTY,LINESTRING(0 0,4 0,0 4),GEOMETRYCOLLECTION(POINT(4 4),POLYGON((0 0,4 0,0 4,0 0))))', '{"type":"GEOMETRYCOLLECTION","children":[{"type":"POINT","coordinates":[]},{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]}]},{"type":"GEOMETRYCOLLECTION","children":[{"type":"POINT","coordinates":[4,4]},{"type":"POLYGON","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[0,0]}]}]}]}]}'::jsonb),
    ('CIRCULARSTRING', 'CIRCULARSTRING(4 0,5 1,6 0)', '{"type":"CIRCULARSTRING","children":[{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[5,1]},{"type":"POINT","coordinates":[6,0]}]}'::jsonb),
    ('COMPOUNDCURVE', 'COMPOUNDCURVE((0 0,4 0),CIRCULARSTRING(4 0,5 1,6 0))', '{"type":"COMPOUNDCURVE","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]}]},{"type":"CIRCULARSTRING","children":[{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[5,1]},{"type":"POINT","coordinates":[6,0]}]}]}'::jsonb),
    ('TRIANGLE', 'TRIANGLE((0 0,4 0,0 4,0 0))', '{"type":"TRIANGLE","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[0,0]}]}]}'::jsonb),
    ('POLYHEDRALSURFACE', 'POLYHEDRALSURFACE(((0 0,4 0,0 4,0 0)),((4 0,4 4,0 4,4 0)))', '{"type":"POLYHEDRALSURFACE","children":[{"type":"POLYGON","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[0,0]},{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[0,0]}]}]},{"type":"POLYGON","children":[{"type":"LINESTRING","children":[{"type":"POINT","coordinates":[4,0]},{"type":"POINT","coordinates":[4,4]},{"type":"POINT","coordinates":[0,4]},{"type":"POINT","coordinates":[4,0]}]}]}]}'::jsonb),
    ('CURVEPOLYGON', 'CURVEPOLYGON(CIRCULARSTRING(0 0,1 1,2 0,1 -1,0 0))', NULL),
    ('MULTICURVE', 'MULTICURVE((0 0,4 0))', NULL),
    ('MULTISURFACE', 'MULTISURFACE(((0 0,4 0,0 4,0 0)))', NULL),
    ('TIN', 'TIN(((0 0,4 0,0 4,0 0)))', NULL)
), layouts(layout) AS (VALUES ('XY'), ('XYZ'), ('XYM'), ('XYZM')),
reference_values(reference) AS (VALUES (0), (4326)), states(empty) AS (VALUES (false), (true)),
cases AS (
    SELECT type, layout, reference, empty, wkt,
           CASE WHEN empty THEN ST_GeomFromText(type || ' EMPTY') ELSE ST_GeomFromText(wkt) END AS geom,
           CASE WHEN empty AND expected IS NOT NULL THEN
               CASE WHEN type = 'POINT' THEN jsonb_build_object('type', type, 'coordinates', '[]'::jsonb)
                    ELSE jsonb_build_object('type', type, 'children', '[]'::jsonb) END
               ELSE expected END AS expected
      FROM examples CROSS JOIN layouts CROSS JOIN reference_values CROSS JOIN states
), dimensioned AS (
    SELECT *, ST_SetSRID(CASE layout
        WHEN 'XYZ' THEN ST_Force3DZ(geom, 12.5)
        WHEN 'XYM' THEN ST_Force3DM(geom, -7.25)
        WHEN 'XYZM' THEN ST_Force4D(geom, 12.5, -7.25)
        ELSE geom END, reference) AS spatial,
        expected IS NOT NULL AND (type <> 'POLYHEDRALSURFACE' OR layout IN ('XYZ', 'XYZM')) AS supported
    FROM cases
)
SELECT jsonb_pretty(jsonb_build_object(
    'postgis_full_version', postgis_full_version(),
    'postgis_version', postgis_lib_version(),
    'postgresql_version', current_setting('server_version'),
    'fixtures', jsonb_agg(jsonb_build_object(
        'id', type || '-' || layout || '-' || reference || CASE WHEN empty THEN '-empty' ELSE '-populated' END,
        'type', type, 'dimension', layout, 'srid', reference, 'empty', empty, 'supported', supported,
        'source_wkt', CASE WHEN empty THEN type || ' EMPTY' ELSE wkt END,
        'ewkt', ST_AsEWKT(spatial),
        'expected', CASE WHEN supported THEN pg_temp.expected_node(expected, layout, reference) ELSE NULL END,
        'ewkb_ndr', encode(ST_AsEWKB(spatial, 'NDR'), 'hex'),
        'ewkb_xdr', encode(ST_AsEWKB(spatial, 'XDR'), 'hex'),
        'wkb_ndr', encode(ST_AsBinary(spatial, 'NDR'), 'hex'),
        'wkb_xdr', encode(ST_AsBinary(spatial, 'XDR'), 'hex')
    ) ORDER BY type, layout, reference, empty)
)) FROM dimensioned;
ROLLBACK;
