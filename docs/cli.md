# CLI reference

Run the executable from the directory containing `tblclass.yaml`:

```bash
php vendor/bin/tbl-class --help
```

## Commands

| Command | Behavior |
| --- | --- |
| `init` | Create a configuration template; refuse to overwrite an existing file |
| `generate` | Connect to the database and generate the class |
| `check` | Compare the current schema and settings with saved metadata |
| `check --diff` | Also print detected differences |
| `independence <directory>` | Replace resolvable references with literal values |
| `--help` | Show usage |
| `--version` | Show the version |

With no arguments, the CLI lists commands. Legacy `--generate` and `--check` flags remain available; `--check --diff` is supported.

## Options

| Option | Scope |
| --- | --- |
| `--diff` | `check` only |
| `--dry-run` | Preview `independence` without writing |
| `--generated <file>` | Select the PHP class read by `independence` |

Invalid option combinations return `2`. Only one command can be specified per invocation.

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | Success; no drift or unresolved references for the selected operation |
| `1` | Operational error, schema/configuration drift, or unresolved independence references |
| `2` | Invalid CLI arguments, initial generation required, or existing config during `init` |

Use the accompanying message to distinguish drift from operational failures.

## Generation failures

Generation rejects an empty schema, invalid namespaces, duplicate constant names, and invalid generated PHP. Output is written to a temporary file in the destination directory and linted before replacing the previous file.

See [check & diff](./check.md) and [independence](./independence.md) for their comparison and conversion boundaries.
