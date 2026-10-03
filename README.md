# Tbl::class

Generate PHP constants from your database schema for IDE autocomplete and fewer SQL naming mistakes.

[![Latest Version](https://img.shields.io/packagist/v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![License](https://img.shields.io/packagist/l/eril/tbl-class)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)

**Version 2.0.0** introduces simplified naming strategies and the `independence` command. Read the [migration guide](docs/migration-v2.md) before regenerating classes in an existing project.

`tbl-class` reads a MySQL, PostgreSQL, or SQLite database and generates a `Tbl` class containing table names, column names, foreign key columns, and JOIN expressions. Enum values exposed by the database reader are documented in column comments.

```php
use Tbl\Tbl;

Tbl::users;                       // 'users'
Tbl::users__id;                   // 'id'
Tbl::fk__posts__users;            // 'user_id'
Tbl::on__posts__users;            // 'posts.user_id = users.id'
```

These examples assume a `posts.user_id` foreign key referencing `users.id` and the `full` naming strategy. The constants hold strings; they do not validate SQL queries or enforce database column types.

## Installation

Requires PHP 8.1 or later, Composer, and PDO with the driver for your database. Generation validates its output using the PHP CLI and requires `proc_open`.

```bash
composer require eril/tbl-class --dev
```

The generator can be a development dependency. Keep the generated class available through your application's production autoload configuration.

## Quick start

### 1. Create the configuration

Run commands from your project root:

```bash
php vendor/bin/tbl-class init
```

This creates `tblclass.yaml`. Running the CLI without arguments only lists commands.

### 2. Configure the database and output

```yaml
database:
  connection: null

  driver: mysql
  host: env(DB_HOST)
  port: 3306
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)

output:
  path: "./"
  namespace: ""
  naming:
    strategy: full
    overrides: {}
```

Set the referenced environment variables before running the generator. Environment expressions read process variables; a `.env` file is not loaded automatically.

For PostgreSQL, use `driver: pgsql` and the appropriate port, usually `5432`. For SQLite, use `driver: sqlite` and `database.path` pointing to an existing database file.

### 3. Generate the class

```bash
php vendor/bin/tbl-class generate
```

With the configuration above, this writes `Tbl/Tbl.php` containing `Tbl\Tbl`.

Generation escapes PHP literals, detects constant collisions, and validates a temporary file before replacing the previous output. Resolve any reported collision before regenerating; names are not silently changed to make them unique.

### 4. Configure autoloading

Merge this entry into your application's `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "Tbl\\": "Tbl/"
    }
  }
}
```

Then rebuild the autoloader:

```bash
composer dump-autoload
```

Import the generated class with `use Tbl\Tbl;`.

The generator adds a `Tbl` directory and namespace segment when needed. For example, `output.path: "./src/Database"` and `output.namespace: 'App\Database'` produce `src/Database/Tbl/Tbl.php` with the class `App\Database\Tbl\Tbl`. An existing `"App\\": "src/"` PSR-4 mapping covers that output.

## Custom PDO connection

To reuse your application's connection, provide a static method returning a PDO instance:

```yaml
include: bootstrap.php
database:
  connection: 'App\Database::getConnection'
  driver: mysql
  name: my_database
```

The optional `include` loads your bootstrap before resolving the callback. The reader still uses `driver` and `name` for schema introspection; host, port, user, and password are not used to create a new connection when a callback is configured.

## Naming strategies

The strategy controls both abbreviation and letter casing. Only these exact values are accepted:

| Strategy | Table constant | Column constant | JOIN constant |
| --- | --- | --- | --- |
| `full` | `users` | `users__id` | `on__posts__users` |
| `FULL` | `USERS` | `USERS__ID` | `ON__POSTS__USERS` |
| `short` | `users` | `usr__id` | `on__pst__usr` |
| `SHORT` | `USERS` | `USR__ID` | `ON__PST__USR` |

The abbreviated examples above use these explicit overrides:

```yaml
output:
  naming:
    strategy: SHORT
    overrides:
      users: usr
      posts: pst
```

Without overrides, `short` and `SHORT` use the bundled English, Portuguese, and Spanish dictionaries and abbreviation rules. A name may remain unchanged when no abbreviation is available.

Overrides affect table prefixes in column and relation constants, in either naming family. Table constants keep their full names. Strategy casing also applies to overrides; SQL values always preserve the original database identifiers.

The separator `__` and prefixes `fk__`, `on__`, and `enum__` are fixed. Mixed-case strategies such as `Full` and `Short` are rejected, as is the removed `case` option. Changing a strategy or override can rename constants used by your application.

## Column qualification and JOINs

Constants return unqualified column names. Dynamic helpers can qualify them with a table name or an explicit alias:

```php
Tbl::users__id;            // 'id'
Tbl::users__id();          // 'id'
Tbl::users__id('users');   // 'users.id'
Tbl::users__id('u');       // 'u.id'
Tbl::users__id(alias: 'u'); // 'u.id'
Tbl::users('u');           // 'users AS u'
```

Helpers use `__callStatic`; individual methods are not generated. Constants remain available for IDE autocomplete.

```php
use Tbl\Tbl;

$sql = 'SELECT '
    . Tbl::users__id('u') . ' AS user_id, '
    . Tbl::posts__id('p') . ' AS post_id'
    . ' FROM ' . Tbl::users('u')
    . ' JOIN ' . Tbl::posts('p')
    . ' ON ' . Tbl::on__posts__users('p', 'u')
    . ' WHERE ' . Tbl::users__id('u') . ' = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
```

Column aliases are local to each call. Omitting the alias, or passing `null` or `''`, returns the unqualified column name. Pass an alias or table name explicitly when qualification is needed. Calling `Tbl::users('u')` does not change later calls. No per-column lookup map is generated.

Use aliases defined by your code. Helpers assemble SQL fragments without quoting identifiers or binding values; use database-appropriate identifier quoting when needed and prepared statements for query values.

## Checking for changes

```bash
php vendor/bin/tbl-class check
php vendor/bin/tbl-class check --diff
```

Both commands are read-only. They compare the generated snapshot with table and column names, foreign keys, enum values exposed by the reader, the database driver, the output namespace, naming settings, and the recorded generator version.

Example diff:

```text
+ users.phone
- users.username
~ output.naming.strategy: "full" -> "FULL"
```

This is not a complete DDL comparison: column types, defaults, indexes, and nullability are not checked. General enum extraction from SQLite `CHECK` constraints is not supported. Manual edits to the generated class body are not verified.

Older generated files without a snapshot need one new generation before detailed comparisons are available.

## Independence: replace references with literal values

Use `independence` to convert references in a selected PHP directory:

```bash
php vendor/bin/tbl-class independence ./src --dry-run
php vendor/bin/tbl-class independence ./src
```

`--dry-run` lists each proposed replacement with its file and line, without writing files.

| Reference | Replacement |
| --- | --- |
| `Tbl::users` | `'users'` |
| `Tbl::users__id` | `'id'` |
| `Tbl::users('u')` | `'users AS u'` |
| `Tbl::users__id('u')` | `'u.id'` |
| `Tbl::on__posts__users('p', 'u')` | `'p.user_id = u.id'` |

The converter resolves namespaces, import aliases, and grouped imports. It preserves comments, strings, and formatting outside replaced expressions. It skips `vendor`, `.git`, symbolic links, and the selected generated file.

It parses the source and interprets supported helper operations without including or executing the files it reads. It does not connect to the database or run the configuration's `include` file.

To select an older generated class, or work without `tblclass.yaml`:

```bash
php vendor/bin/tbl-class independence ./src --generated ./backup/Tbl.php --dry-run
```

Use the class that matches your existing references, before regenerating with a different naming strategy.

Dynamic arguments, unknown members, class references, and unsupported helper operations remain unchanged and are reported with their file and line. Resolvable replacements are still applied when other references remain unresolved.

Imports, Composer autoload entries, dependencies, and generated files are not removed. Review references built through strings or reflection, code outside the selected directory, and unresolved references before removing the generated class.

All candidate PHP files are parsed before writing. Each changed file is linted and replaced atomically, but the entire directory operation is not a transaction: a write failure can leave earlier files already updated.

## CLI reference

| Command | Purpose |
| --- | --- |
| `init` | Create `tblclass.yaml` without overwriting an existing file |
| `generate` | Read the database and generate `Tbl.php` |
| `check [--diff]` | Check schema and generation settings without writing |
| `independence <directory> [--dry-run] [--generated <file>]` | Replace resolvable references with literals |
| `--help` | Show usage |
| `--version` | Show the CLI version |

Legacy `--generate` and `--check` flags remain available.

| Exit code | Meaning |
| --- | --- |
| `0` | Success; no detected differences or unresolved references for the selected operation |
| `1` | Operational error, detected changes, or unresolved independence references |
| `2` | Invalid CLI arguments, initial generation required, or an existing configuration when running `init` |

## Documentation

- [Getting started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Naming strategies](docs/naming.md)
- [Constants, aliases and JOINs](docs/usage.md)
- [CLI reference](docs/cli.md)
- [Independence](docs/independence.md)
- [Migrating to v2](docs/migration-v2.md)
- [Changelog](CHANGELOG.md)

## License

[MIT](LICENSE) © Eril TS Carvalho.
