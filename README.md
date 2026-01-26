# Tbl::class

### Type-safe database schema constants para PHP

[![Latest Version](https://img.shields.io/packagist/v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![License](https://img.shields.io/packagist/l/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Stars](https://img.shields.io/github/stars/erilshackle/tbl-class-php?style=social)](https://github.com/erilshackle/tbl-class-php)

`tbl-class` generates a **single, immutable PHP entry-point** that maps your database schema
(tables, columns and relations) into **compile-safe constants**.

It creates a stable abstraction layer between your database and your application code.

---

## Why tbl-class?

Database-driven applications often rely on **string literals** for table names, column names and joins:

```php
SELECT * FROM users WHERE created_at > ?
```

This approach is fragile:

* typos are silent
* refactors are dangerous
* schema changes break code at runtime

`tbl-class` solves this by generating a **canonical reference layer**:

```php
Tbl::users               // "users"
Tbl::users__created_at   // "created_at"
Tbl::fk__users__roles    // "role_id"
```

Your application no longer depends on raw strings — it depends on **generated constants**.

---

## Core Concepts

### 1. Single Entry Point

`tbl-class` generates **one final class** (`Tbl`) that exposes:

* table names
* column names
* foreign key columns
* JOIN helpers inferred from relations

No models.
No runtime reflection.
No magic at execution time.

---

### 2. Schema as Source of Truth

The database schema is the **only authority**.

* No annotations
* No config duplication
* No manual mapping

Change the schema → regenerate → constants update.

---

### 3. Deterministic Naming Strategy

All constants are generated using a **naming strategy** defined once in `tblclass.yaml`.

> ⚠ Changing the strategy after first generation **will rename constants**
> and may break existing code.

This design is intentional.

---

### 4. Zero Runtime Cost

The generated class contains:

* only `public const`
* no database access
* no I/O
* no runtime parsing

It is loaded once and fully optimized by OPcache.

---

## Installation

```bash
composer require eril/tbl-class
```

---

## Usage

### First Run

```bash
tbl-class
```

On first execution, a configuration file is created:

```yaml
tblclass.yaml
```

Edit it, configure your database connection, and run the command again.

---

### Generate Constants

```bash
tbl-class
```

This will:

* connect to the database
* read the schema
* generate `Tbl.php` in the configured output directory

---

### Check for Schema Changes (CI-friendly)

```bash
tbl-class --check
```

This command:

* does **not** regenerate files
* compares the current schema hash with the last generated one
* exits with non-zero code if changes are detected

Ideal for CI pipelines.

---

## Configuration Overview

All configuration lives in `tblclass.yaml`.

High-level sections:

```yaml
enabled: true

include: null

database:
  driver: mysql | pgsql | sqlite
  connection: null

output:
  path: "./"
  namespace: ""
  naming:
    strategy: full
```

Detailed configuration is documented in the project wiki.

---

## Generated Output

The generated `Tbl` class provides:

### Tables

```php
Tbl::USERS
```

### Columns

```php
Tbl::USERS__EMAIL
```

### Foreign Keys

```php
Tbl::FK__USERS__ROLES
```

### JOIN Helpers

```php
Tbl::on__users__roles()
Tbl::on__users__roles('u', 'r')
```

JOIN helpers are derived automatically from foreign keys.

---

## Autoloading

Depending on your configuration, add **one** of the following to `composer.json`:

### PSR-4

```json
"autoload": {
  "psr-4": {
    "App\\Tbl\\": "path/to/output/"
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

## Design Guarantees

`tbl-class` guarantees that:

* Generated files are deterministic
* Regeneration is idempotent
* No runtime dependency on database drivers
* No reflection or parsing at runtime
* No framework coupling

---

## What tbl-class Is Not

* ❌ Not an ORM
* ❌ Not a migration tool
* ❌ Not a query builder
* ❌ Not a runtime schema inspector

It is a **compile-time schema contract**.

---

## License

MIT © 2026 Eril TS Carvalho

```

---

<div align="center">
<strong>tbl::class — constantes type-safe para esquemas de base de dados em PHP.</strong>
</div>
