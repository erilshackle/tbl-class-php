# Naming strategies

The strategy controls abbreviation and casing together. The four accepted values are case-sensitive:

| Strategy | Table | Column | JOIN |
| --- | --- | --- | --- |
| `full` | `authentication` | `authentication__id` | `on__configuration__authentication` |
| `FULL` | `AUTHENTICATION` | `AUTHENTICATION__ID` | `ON__CONFIGURATION__AUTHENTICATION` |
| `short` | `authentication` | `auth__id` | `on__cfg__auth` |
| `SHORT` | `AUTHENTICATION` | `AUTH__ID` | `ON__CFG__AUTH` |

These short examples use the bundled dictionary. SQL values remain unchanged: `AUTH__ID` still contains `'id'`.

## Overrides

```yaml
output:
  naming:
    strategy: short
    overrides:
      users: usr
      posts: pst
```

This gives `users`, `usr__id`, `fk__pst__usr`, and `on__pst__usr`. Table constants retain full table names. Overrides affect table prefixes in column and relationship constants, for both the full and short families.

With `SHORT`, the same overrides produce `USR__ID` and `ON__PST__USR`. Use overrides when you need an explicit, stable abbreviation.

## Abbreviation rules

`short` and `SHORT` use bundled English, Portuguese, and Spanish dictionaries and abbreviation rules. Names without an available abbreviation may stay unchanged. Names are resolved per table, without depending on which other tables were resolved first.

## Fixed conventions

The separator is `__`; relationship prefixes are `fk__` and `on__`. The enum naming prefix is also fixed as `enum__`, but the current file generator documents reader-provided enums in comments rather than emitting enum constants.

`Full`, `Short`, `case`, custom separators, custom prefixes, and dictionary configuration are not accepted. See [migration to v2](./migration-v2.md) for removed settings.

## Invalid identifiers and collisions

Invalid PHP identifier characters are replaced with `_`. Names beginning with digits gain a leading `_`, and the reserved name `class` becomes `_class`. The constant's value preserves the original database identifier.

If normalization or overrides produce duplicate constants, generation fails and preserves the previous output. Multiple foreign keys between the same pair of tables can also collide because relationship names identify the two tables, not each individual constraint.

Changing naming settings can rename application references. Review those references before regenerating; `check --diff` reports setting changes but does not rewrite your code.
