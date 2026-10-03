# Legacy EWKT test adaptations

These tests retain historical scenario and provider names from `wkt-parser`
while exercising the current decoder and, for the lexical scenarios identified
below, its internal lexer. They are compatibility scenarios, not a
promise to preserve the old parser API, result arrays, or diagnostic wording.
The current behavior remains covered by `tests/Unit/`.

The comparison below uses `wkt-parser` revision
[`7d3c8fd`](https://github.com/longitude-one/wkt-parser/tree/7d3c8fd5fd061c44349acc6bc8de8e995371b168),
particularly its [ParserTest.php](https://github.com/longitude-one/wkt-parser/blob/7d3c8fd5fd061c44349acc6bc8de8e995371b168/tests/LongitudeOne/Geo/WKT/Tests/ParserTest.php),
[ExceptionTest.php](https://github.com/longitude-one/wkt-parser/blob/7d3c8fd5fd061c44349acc6bc8de8e995371b168/tests/LongitudeOne/Geo/WKT/Tests/ExceptionTest.php),
and [Parser.php](https://github.com/longitude-one/wkt-parser/blob/7d3c8fd5fd061c44349acc6bc8de8e995371b168/lib/LongitudeOne/Geo/WKT/Parser.php).

## Lexical scenarios retained as internal tests

For story #14, the Product Owner explicitly approved retaining
[LexerTest.php](LexerTest.php) unchanged as an exception to the requirement that
migrated tests use the public `Decoder` API. These scenarios remain internal
regression coverage alongside the public decoding scenarios; they do not define
a public lexer contract.

The following assertions cannot be transposed to `Decoder::decode()` without
changing what they verify:

- `testTokenRecognition()` uses `tokenData()` to check token types, values and
  source positions exposed through `lookahead` after each `moveNext()`. Its
  cases cover geometry keywords, attached and spaced dimension suffixes,
  integer and floating-point literals (including scientific notation), `SRID`,
  and the complete token sequence of the SRID-prefixed `LINESTRING` fixture,
  including punctuation. Standalone keywords and numbers are lexical fragments,
  not complete geometry inputs. Even for the complete fixture, a decoded
  geometry does not expose token boundaries, token types or source positions.
- `testTokenRecognitionReuseLexer()` runs the same provider through one lexer,
  calling `setInput()` between cases and checking the resulting token sequences.
  It verifies lexer state reset and reuse. The public decoder does not expose
  that lexer instance, its `lookahead`, or its input-reset lifecycle.

Replacing these assertions with geometry results or decoding exceptions would
lose their lexical purpose. Both tests and their provider are therefore retained
as direct tests of `Strategy\Common\Lexer`, rather than counted as scenarios
ported to the public decoding API.

## Exception classes and messages

In the table, historical classes belong to `LongitudeOne\Geo\WKT\Exception`;
current classes belong to `LongitudeOne\SpatialDecoder\Exception`.

| Historical expectation                              | Migrated expectation.                                   | Reason and traceability                                                                                                                                                                                                                                                |
| --------------------------------------------------- | ------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `NotExistentException` for unknown types            | `InvalidArgumentException`                              | `ParserTest::notExistentValuesProvider()`, `testUnexpectedType()` and `ExceptionTest::testNotExistentException()` now exercise decoder rejection of unsupported geometry types. Messages identify the invalid input instead of repeating the old ISO-labelled wording. |
| `UnexpectedValueException` for malformed input      | `InvalidArgumentException`                              | `ParserTest::unexpectedValues()` retains the invalid inputs and named cases. Expected messages now describe SRID prefix, coordinate dimension, or syntax failures from the current decoder, rather than the old lexer's token names and line/column offsets.           |
| `NotInstantiableException`                          | `NonInstantiableGeometryTypeException`                  | The abstract-type providers in `ParserTest` and `ExceptionTest` use the decoder's dedicated exception. Messages name the recognized enum type; historical “Did you mean” suggestions and ISO-labelled wording are not retained.                                        |
| `NotYetImplementedException` from the old namespace | `NotYetImplementedException` from the decoder namespace | `ExceptionTest::notYetImplementedTypes()` expects the decoder's message, `Decoding of spatial type "…" is not yet implemented.`, rather than a message naming the old `Parser` class.                                                                                  |

These are adaptations to the existing implementation and exception API, not
new normative claims or a guarantee of identical diagnostics between libraries.

## No-argument parsing versus empty input

Historically, `Parser::parse(?string $input = null)` could use constructor input.
`ParserTest::testNullParser()` called `parse()` on a parser without input and
expected `UnexpectedValueException` with `No value provided`.

The current [Decoder::decode()](../../../lib/Decoder.php) requires an argument.
The migrated [testNullParser()](ParserTest.php) therefore calls `decode('')` and
expects the decoder's `InvalidArgumentException` for an unsupported geometry
value, including `Invalid WKT input: "".` in the message. This replaces the
historical scenario with empty-string rejection; it does not verify equivalent
no-argument behavior. Calling `decode()` without an argument would instead fail
PHP's argument-count check before parsing.

## Absent SRID: `null` to `0`

The old parser initialized its result-array `srid` field to `null` when no SRID
prefix was supplied. Historical fixture values retain that `null` for traceability.
The current EWKT strategy returns spatial objects whose default SRID is `0`, as
documented in the [public usage examples](../../../README.md#ewkt-decoding).

[SpecificTestCase](Utils/SpecificTestCase.php) explicitly compares
`$expectedSrid ?? 0` with `getSrid()` in both `assertParsed()` and
`assertGeometryCollectionParsed()`; child assertions use the same conversion.
This adapts an absent historical array value to the current object model.
Explicit numeric SRIDs are compared unchanged. It does not infer a coordinate
reference system or alter coordinates.
