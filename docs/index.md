---
layout: home
hero:
  name: Tbl::class
  text: Your schema. In PHP constants.
  tagline: Keep writing SQL. Let your IDE help with table names, columns, and relationships.
  actions:
    - theme: brand
      text: Get started
      link: /getting-started
    - theme: alt
      text: Explore the configuration
      link: /configuration
features:
  - title: Names your IDE knows
    details: Generate a PHP class from MySQL, PostgreSQL, or SQLite and use constants directly in your queries.
  - title: Explicit aliases
    details: Qualify columns and build JOIN expressions with aliases local to each call.
  - title: Check before you ship
    details: Compare your schema and generation settings with the saved snapshot using check --diff.
  - title: Leave with your values
    details: Convert resolvable Tbl references to literal strings with the independence command.
---

```php
use Tbl\Tbl;

Tbl::users;                            // 'users'
Tbl::users__id('u');                    // 'u.id'
Tbl::on__posts__users('p', 'u');         // 'p.user_id = u.id'
```

These examples use `full` naming and a foreign key from `posts.user_id` to `users.id`.
