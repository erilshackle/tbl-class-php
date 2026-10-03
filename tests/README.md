# Tests

Run the suite with `composer test`. PHP needs `pdo_sqlite` and `proc_open`.

## Reading a test

Each test prepares its own inputs, performs one operation, and checks the expected result. For example, `CheckTest::testSchemaChangesAreReportedWithoutWriting`:

1. Creates a SQLite schema and generates the initial file.
2. Changes the schema using SQL.
3. Runs check and verifies the reported difference and unchanged file contents.

`assertSame($expected, $actual)` compares both value and type. `expectException(...)` means the following operation must throw. An assertion in `generate()` checks that fixture generation succeeded before the behavior under test can be exercised.

A `#[DataProvider(...)]` runs the same rule separately for each named input. For example, `helperCalls()` lists the method, arguments, and expected SQL. Each row appears as its own result, so a failure identifies the exact alias case. `OutputProfiles` supplies explicit expected constant names for all eight strategy/override combinations; it does not call the naming implementation to calculate expectations.

## Organization

| Location | Responsibility |
| --- | --- |
| `Unit/NamingResolverTest.php` | Strategies, overrides, invalid settings, stable names |
| `Unit/PhpOutputTest.php` | PHP identifiers, comments, duplicate constants |
| `Unit/SchemaSnapshotTest.php` | Hash normalization and enum differences |
| `Integration/SqliteSchemaReaderTest.php` | Introspection of a real SQLite database |
| `Integration/GenerationTest.php` | Valid PHP output and preservation on errors |
| `Integration/GeneratedHelpersTest.php` | Constants, aliases, JOIN execution, all output profiles |
| `Integration/CheckTest.php` | Drift, metadata, configuration changes, read-only behavior |
| `Integration/IndependenceTest.php` | Source conversion, unresolved references, no execution |
| `Integration/GeneratedModelTest.php` | Interpretation of explicit legacy helpers |
| `Integration/CliTest.php` | Real command execution, console messages, exit codes |
| `Support/` | Temporary workspace, memory database, output profiles |

Every database integration test starts with a fresh `sqlite::memory:` connection. CLI commands run in separate PHP processes, so a connection fixture recreates the database from `schema.sql` on every invocation. Changing that SQL simulates drift without a database file or server.

Only generated PHP, configuration, and source-conversion fixtures use temporary files. Each test owns and removes its directory. Generated classes have unique namespaces so tests can run in random order without class redeclarations.

SQLite does not have native enums. Enum rendering uses a PHPUnit reader stub, and enum comparison uses explicit snapshots. This does not test MySQL or PostgreSQL introspection; those readers require separate database-specific integration suites.

## Useful commands

```sh
composer test -- --testdox
composer test -- --testsuite unit
composer test -- --testsuite integration
composer test -- --filter testAliasesDoNotPersistBetweenCalls
composer test -- --order-by=random --random-order-seed=20261003
```
