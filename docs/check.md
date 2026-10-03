# Check & diff

```bash
php vendor/bin/tbl-class check
php vendor/bin/tbl-class check --diff
```

Both commands are read-only. They compare the live schema and generation settings against metadata embedded in the generated PHP file.

## What is compared

- Table and column names.
- Foreign key endpoints.
- Enum values exposed by the schema reader.
- Database name and driver.
- Output namespace and naming settings.
- Generator version and output format metadata.

Credentials are not included in the snapshot. Table, column, and foreign key read order does not cause drift; enum value order is preserved.

## Example output

```text
+ users.phone
- users.username
~ output.naming.strategy: "full" -> "FULL"
```

`+` means added, `-` means removed, and `~` means changed. This is a schema/configuration summary, not a patch to apply to PHP source.

## Result handling

| State | Exit code | Next step |
| --- | --- | --- |
| Up to date | `0` | Continue |
| Differences detected | `1` | Review, regenerate, and test the application |
| Legacy file with no snapshot | `1` | Generate once to save current metadata |
| Output missing | `2` | Run initial generation |
| Invalid snapshot or another operational error | `1` | Resolve the reported failure |

Check reads the destination selected by your current config. After changing `output.path`, generate at the new destination first.

## Limits

This is not a complete DDL comparison. Column types, defaults, indexes, and nullability are not checked. General enum extraction from SQLite `CHECK` constraints is not supported. Manual changes to the class body are not checked against its saved metadata.

## CI usage

Run check after preparing a database with the expected schema:

```bash
php vendor/bin/tbl-class check --diff
```

Let its exit code fail the CI step when the committed generated file is outdated. Check requires database access; [independence](./independence.md) does not.
