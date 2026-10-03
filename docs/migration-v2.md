# Migrate to v2

Version 2 changes naming configuration and abbreviated relationship names. Keep your previous generated class until you have updated all application references.

## Naming changes

| Previous setting | v2 setting |
| --- | --- |
| `strategy: full` | `strategy: full` |
| `strategy: abbr` | `strategy: short` |
| `strategy: short` | `strategy: short`; foreign key and JOIN names are now abbreviated too |
| `strategy: upper` | `strategy: FULL` |
| `strategy: alias` | Choose `full` or `short` and specify `overrides` |

```yaml
output:
  naming:
    strategy: short
    overrides:
      users: usr
      purchase_orders: po
```

If you used an earlier v2 configuration with `case`, remove it. `full` plus `case: upper` becomes `FULL`; `short` plus `case: upper` becomes `SHORT`. For lowercase, use `full` or `short`.

Only `full`, `FULL`, `short`, and `SHORT` are accepted. Mixed-case values such as `Full` are rejected.

## Removed settings

Remove `separator`, `fk_prefix`, `join_prefix`, `enum_prefix`, `dictionary`, and `abbreviation`. Separators and prefixes are fixed; short strategies use the bundled dictionaries. Use overrides for explicit prefixes that remain stable across dictionary changes.

Table constant names remain full. Overrides affect column and relation prefixes. Uppercase strategies change PHP identifiers, not SQL values.

## Collisions

Duplicate names stop generation and preserve the existing file. They are not assigned automatic suffixes. Multiple foreign keys between the same tables can still collide under the current relationship naming convention.

## Keep using Tbl

1. Back up the old class and update configuration.
2. Run `check --diff` against your database to inspect changes.
3. Update affected references and run `generate`.
4. Run application tests and `check` again.

Check does not rewrite application references. Old files without a snapshot require one generation before detailed comparisons are available. The generator version is now recorded in generation metadata.

## Replace references with literals

Use the old class before changing the generated names:

```bash
php vendor/bin/tbl-class independence ./src --generated ./backup/Tbl.php --dry-run
php vendor/bin/tbl-class independence ./src --generated ./backup/Tbl.php
```

This requires no database connection. Unresolved references are reported and left intact; supported replacements still apply. See [independence](./independence.md) before cleaning up imports, autoload mappings, or dependencies.
