# Overview

Tbl::class reads your database schema and generates one PHP class containing strings you can use in SQL. Constants provide IDE autocomplete and centralize database names without replacing your query code.

## Generated output

For a `posts.user_id` foreign key referencing `users.id`, the `full` strategy produces:

```php
use Tbl\Tbl;

Tbl::users;                 // 'users'
Tbl::users__id;             // 'id'
Tbl::fk__posts__users;      // 'user_id'
Tbl::on__posts__users;      // 'posts.user_id = users.id'
```

Enum values exposed by the database reader appear in column comments. They are not generated as enum constants or PHP enum types.

The generated class also supports [alias helpers](./usage.md). Individual column methods are not generated; helper calls use `__callStatic`.

## Supported databases

- MySQL / MariaDB through `pdo_mysql`.
- PostgreSQL through `pdo_pgsql`.
- SQLite through `pdo_sqlite`.

You can configure connection parameters or supply a [custom PDO connection](./configuration.md#custom-pdo-connection).

## Workflow

1. Configure the database and choose your naming strategy.
2. Generate and autoload the class.
3. Reference its constants or helpers in your SQL.
4. Run `check --diff` after schema or generation-setting changes.
5. Regenerate and test your application when changes are expected.

Constants do not validate query syntax, enforce column types, quote SQL identifiers, or bind query values. Keep using prepared statements for values and database-appropriate quoting for special identifiers.

If you later want literal strings in your application, [independence](./independence.md) can replace references it can resolve statically.
