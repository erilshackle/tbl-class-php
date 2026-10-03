# Constants, aliases & JOINs

The examples use `full` naming, tables `users` and `posts`, and a foreign key from `posts.user_id` to `users.id`.

## Constants

```php
use Tbl\Tbl;

Tbl::users;                 // 'users'
Tbl::users__id;             // 'id'
Tbl::fk__posts__users;      // 'user_id'
Tbl::on__posts__users;      // 'posts.user_id = users.id'
```

Columns are unqualified strings. When both joined tables have an `id` column, qualify it explicitly.

## Table and column aliases

```php
Tbl::users('u');             // 'users AS u'
Tbl::users__id('u');         // 'u.id'
Tbl::users__id(alias: 'u');  // 'u.id'
Tbl::users__id('users');     // 'users.id'
```

An alias is local to the call. `Tbl::users('u')` does not register `u` for subsequent calls.

```php
Tbl::users__id();       // 'id'
Tbl::users__id(null);   // 'id'
Tbl::users__id('');     // 'id'
```

Table helpers return the original table name when no alias, `null`, or an empty string is supplied. Foreign key helpers behave like column helpers:

```php
Tbl::fk__posts__users('p'); // 'p.user_id'
```

## JOIN expressions

The first argument qualifies the referencing table; the second qualifies the referenced table:

```php
Tbl::on__posts__users();          // 'posts.user_id = users.id'
Tbl::on__posts__users('p');       // 'p.user_id = users.id'
Tbl::on__posts__users(null, 'u'); // 'posts.user_id = u.id'
Tbl::on__posts__users('p', 'u');  // 'p.user_id = u.id'
```

Use positional arguments for JOIN helpers. Pass `null` to retain an endpoint's original table name.

## Complete query

```php
use Tbl\Tbl;

$sql = 'SELECT '
    . Tbl::users__id('u') . ' AS user_id, '
    . Tbl::posts__id('p') . ' AS post_id'
    . ' FROM ' . Tbl::users('u')
    . ' JOIN ' . Tbl::posts('p')
    . ' ON ' . Tbl::on__posts__users('p', 'u')
    . ' WHERE ' . Tbl::users__id('u') . ' = ?';

$statement = $pdo->prepare($sql);
$statement->execute([$userId]);
```

Aliases should come from your code. Helpers concatenate SQL fragments; they do not quote identifiers or bind values.

## Self JOIN

Given `users.parent_id` referencing `users.id`:

```php
Tbl::on__users__users('child', 'parent'); // 'child.parent_id = parent.id'
```

This allows the same table to appear twice with independent aliases.

## Helper implementation

The generated class uses `__callStatic` without explicit per-column methods or a column lookup map. Constants remain available for IDE autocomplete. Calling an unknown helper throws `BadMethodCallException`.
