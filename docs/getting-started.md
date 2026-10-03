# Installation & first generation

## Requirements

- PHP 8.1 or later and Composer.
- PDO with the extension for your database.
- PHP CLI with `proc_open` enabled, used to lint generated output.
- An existing database with at least one table.

Node.js is only needed to build this documentation site, not to use the PHP library.

## Install

```bash
composer require eril/tbl-class --dev
php vendor/bin/tbl-class init
```

Run commands from the application root. `init` creates `tblclass.yaml` and refuses to overwrite an existing file.

## Configure

For MySQL:

```yaml
include: null
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

Set these process environment variables before generation. `.env` files are not loaded automatically. See [configuration](./configuration.md) for PostgreSQL, SQLite, bootstrap files, and custom PDO connections.

## Generate

```bash
php vendor/bin/tbl-class generate
```

The default output is `Tbl/Tbl.php`, containing the class `Tbl\Tbl`. Generation detects constant collisions and lints a temporary file before replacing existing output.

## Autoload

Merge this into your application's `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "Tbl\\": "Tbl/"
    }
  }
}
```

```bash
composer dump-autoload
```

Use the generated class after your application loads Composer's autoloader:

```php
use Tbl\Tbl;

$sql = 'SELECT ' . Tbl::users__id . ' FROM ' . Tbl::users;
```

This example requires a `users` table with an `id` column. Your generated constants follow your actual schema.

Keep the generated file available in production and place its mapping under `autoload`, even when the generator is installed under `require-dev`. See [autoloading](./autoload.md) for other output paths.

## Check for changes

```bash
php vendor/bin/tbl-class check --diff
```

An unchanged snapshot returns exit code `0`. Detected differences return `1`; a missing generated file returns `2`. [Learn what check compares](./check.md).
