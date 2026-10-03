# Configuration

The CLI reads `tblclass.yaml` from the current working directory. Paths in configuration are resolved relative to that working directory, so run commands from your project root.

## Complete MySQL example

```yaml
# ------------------------------------------------------------
# tbl-class v2 configuration file
#
# Auto-generated on first run.
# Delete this file to regenerate a clean template.
# ------------------------------------------------------------
# Doc https://github.com/erilshackle/tbl-class-php/wiki/config
# ------------------------------------------------------------

# Optional: manually include a PHP file before execution
include: null

# ------------------------------------------------------------
# Database configuration
# ------------------------------------------------------------
database:

  # Optional custom connection resolver
  # Must return a PDO instance
  # Example: App\\Database::getConnection
  connection: null

  driver: mysql            # mysql | pgsql | sqlite

  # For MySQL / PostgreSQL
  host: env(DB_HOST)       # default: localhost
  port: env(DB_PORT)       # default: 3306 | 5432
  name: env(DB_NAME)       # database name
  user: env(DB_USER)       # e.g. root
  password: env(DB_PASS)   # e.g. secret

  # SQLite only
  # path: env(DB_PATH)     # e.g. database.sqlite

# ------------------------------------------------------------
# Output configuration
# ------------------------------------------------------------
output:

  # Output directory
  path: "./"

  # PHP namespace for the generated Tbl class
  namespace: ""


  # ⚠ IMPORTANT
  # This strategy defines ALL generated constant names.
  # Changing it later WILL rename constants and MAY break code.
  #
  # Strategies:
  # - full   → table, table__column, fk__table__references
  # - short  → table, tbl__column, fk__tbl__ref
  # Separators and prefixes are fixed: __, fk__, on__, enum__
  naming:
    strategy: full          # full | FULL | short | SHORT
    overrides: {}           # e.g. {users: usr, purchase_orders: po}
```

This generates `src/Database/Tbl/Tbl.php`, containing `App\Database\Tbl\Tbl`.

## Database settings

| Key | Purpose |
| --- | --- |
| `connection` | Optional `Class::method` callback returning PDO |
| `driver` | `mysql`, `pgsql`, or `sqlite`; also selects the schema reader |
| `name` | Database name used by the schema reader |
| `host` | MySQL / PostgreSQL host; defaults to `localhost` |
| `port` | Defaults to `3306` for MySQL and `5432` for PostgreSQL |
| `user` | Defaults to `root` for MySQL and `postgres` for PostgreSQL |
| `password` | Defaults to an empty string |
| `path` | SQLite file path; defaults to `database.sqlite` |

### PostgreSQL

```yaml
database:
  connection: null
  driver: pgsql
  host: env(DB_HOST)
  port: 5432
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)
```

### SQLite

```yaml
database:
  connection: null
  driver: sqlite
  name: application
  path: ./database/application.sqlite
```

The file must already exist. To introspect an in-memory database, supply a custom connection callback that creates and populates the schema before returning PDO.

## Custom PDO connection

```yaml
include: bootstrap.php
database:
  connection: 'App\Database::getConnection'
  driver: mysql
  name: application
```

The static method must be available after bootstrap and return a PDO instance:

```php
namespace App;

final class Database
{
    public static function getConnection(): \PDO
    {
        return new \PDO(
            'mysql:host=localhost;dbname=application;charset=utf8mb4',
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
```

With a callback, connection creation does not use `host`, `port`, `user`, or `password` from YAML. `driver` and `name` still configure schema introspection and must match the connection.

## Bootstrap and environment variables

`include` is a top-level setting. If it points to an existing PHP file, generation and check load that file with `include_once` before connecting. It can load your application or an environment loader. `independence` does not run this file.

Prefer explicit environment expressions:

```yaml
database:
  host: env(DB_HOST)
  name: '${DB_NAME}'
```

These read process variables through `getenv()`. The resolver also treats bare uppercase tokens as environment names for general settings. Missing or false-like environment values fall back to the setting's default. Naming strategies such as `FULL` and `SHORT` are literal values, not environment expressions.

## Output location and namespace

| Configuration | Result |
| --- | --- |
| `path: "./"`, `namespace: ""` | `Tbl/Tbl.php`, class `Tbl\Tbl` |
| `path: "./src/Database"`, `namespace: 'App\Database'` | `src/Database/Tbl/Tbl.php`, class `App\Database\Tbl\Tbl` |
| `path: "./src/Database/Tbl"`, `namespace: 'App\Database\Tbl'` | Same result; the trailing `Tbl` segment is not added twice |

An empty namespace still produces `namespace Tbl;`. Configure [Composer autoloading](./autoload.md) for the effective namespace and path.

## Naming

```yaml
output:
  naming:
    strategy: SHORT
    overrides:
      users: usr
      posts: pst
```

Only `strategy` and `overrides` are accepted under `naming`. See [naming strategies](./naming.md) for exact output examples and collision handling.
