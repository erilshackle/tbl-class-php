# Tbl::class

### Type-safe database schema constants para PHP

[![Latest Version](https://img.shields.io/packagist/v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![License](https://img.shields.io/packagist/l/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Stars](https://img.shields.io/github/stars/erilshackle/tbl-class-php?style=social)](https://github.com/erilshackle/tbl-class-php)

`tbl-class` generates **immutable PHP entry-point classes** that map your database schema  
(tables, columns and relations) into **compile-safe constants**.

It provides a stable abstraction layer between your database and your application code,
eliminating fragile string literals and runtime schema assumptions.

---

## Why tbl-class?

Database-driven PHP applications commonly rely on raw strings:

```php
SELECT * FROM users WHERE created_at > ?
```

This approach is fragile:

* typos are silent
* refactors are risky
* schema changes break code at runtime
* no compile-time guarantees

`tbl-class` replaces string-based access with **generated constants derived directly
from the database schema**:

```php
Tbl::users
Tbl::users__created_at
Tbl::fk__users__roles
Tbl::on__users__roles
```

Your application code becomes **schema-aware, explicit and refactor-safe**.

---

## Installation

```bash
composer require eril/tbl-class --dev
```

---

## Usage

### First run

```bash
tbl-class
```

On first execution, a configuration file is generated:

```text
tblclass.yaml
```

**Edit** the file, configure your **database connection**, then run the command again.

---

### Generate schema constants

```bash
tbl-class
```

This command:

* connects to the database
* introspects the schema
* generates PHP constants according to your configuration

---

### Check for schema changes (CI-friendly)

```bash
tbl-class --check
```

This mode:

* does **not** generate files
* compares the current database schema with the last generated version
* exits with a non-zero status code if changes are detected

Designed for CI pipelines and deployment safety checks.

> It's not meant to be checked overtime, but only

---

## Configuration

All configuration lives in `tblclass.yaml`.

A clean template is auto-generated on first run:

```yaml
# Enable or disable generation
enabled: true       

# Optional: include a PHP file before execution
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

  # MySQL / PostgreSQL
  host: env(DB_HOST)       # default: localhost
  port: env(DB_PORT)       # default: 3306 | 5432
  name: env(DB_NAME)       # database name
  user: env(DB_USER)
  password: env(DB_PASS)

  # SQLite only
  # path: env(DB_PATH)

# ------------------------------------------------------------
# Output configuration
# ------------------------------------------------------------
output:

  # Output directory
  path: "./"

  # PHP namespace for generated classes
  namespace: ""

  # ⚠ IMPORTANT
  # This strategy defines ALL generated constant names.
  # Changing it later WILL rename constants and MAY break code.
  #
  # Strategies:
  # - full   → table, table__column, fk__table__references
  # - short  → table, tbl__column,   fk__table__references
  # - abbr   → table, tbl__column,   fk__tbl__ref
  # - alias  → table, t__column,     fk__t__r
  # - upper  → TABLE, TABLE__COLUMN, FK__TABLE__REFERENCES
  naming:
    strategy: full
```

📘 **Full configuration reference:**
[https://github.com/erilshackle/tbl-class-php/wiki/config](https://github.com/erilshackle/tbl-class-php/wiki/config)

---

## Generated Output

Depending on the enabled generators, `tbl-class` produces PHP classes containing:

* table name constants
* column name constants
* foreign key references
* JOIN expressions derived from relations

Example usage:

```php
Tbl::users                       // returns "users"
Tbl::users(u)                    // returns "users AS u"
Tbl::users__email                // returns "email"
Tbl::fk__users__roles            // returns "role_id" or whataver you named it in DB
Tbl::on__users__roles()          // returns "users.role_id = roles.id"
Tbl::on__users__roles('u', 'r')  // // returns "u.role_id = r.id"
```

All output is **deterministic**, **static**, and **runtime-free**.

---

## Autoloading

After generation, add **one** of the following to your `composer.json`.

### PSR-4

```json
"autoload": {
  "psr-4": {
    "Tbl\\": "path/to/Tbl/"
  }
}
```

### Files

```json
"autoload": {
  "files": [
    "path/to/Tbl.php"
  ]
}
```

Then run:

```bash
composer dump-autoload
```

---

## Documentation

All advanced topics, design decisions and future extensions are documented in the Wiki:

📚 [https://github.com/erilshackle/tbl-class-php/wiki](https://github.com/erilshackle/tbl-class-php/wiki)

---

## What tbl-class is not

* ❌ Not an ORM
* ❌ Not a query builder
* ❌ Not a migration tool
* ❌ Not a runtime schema inspector

It is a **compile-time schema contract for PHP applications**.

---

## License

MIT © 2026 Eril TS Carvalho

```
