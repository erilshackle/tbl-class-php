# Independence

`independence` replaces resolvable Tbl references in PHP files with literal values. Start with a preview:

```bash
php vendor/bin/tbl-class independence ./src --dry-run
php vendor/bin/tbl-class independence ./src
```

The preview prints each replacement with its file and line. Without `--dry-run`, the command writes changed files.

## Supported replacements

| Reference | Replacement |
| --- | --- |
| `Tbl::users` | `'users'` |
| `Tbl::users__id` | `'id'` |
| `Tbl::users('u')` | `'users AS u'` |
| `Tbl::users__id()` | `'id'` |
| `Tbl::users__id(alias: 'u')` | `'u.id'` |
| `Tbl::on__posts__users('p', 'u')` | `'p.user_id = u.id'` |

The converter resolves namespaces, fully qualified names, import aliases, and grouped imports. It preserves comments, strings, and formatting outside replaced expressions.

## Select a generated class

By default, the command finds the generated file through `tblclass.yaml`. You can select a file explicitly, without that configuration:

```bash
php vendor/bin/tbl-class independence ./src --generated ./backup/Tbl.php --dry-run
```

Select the class matching your existing references. When changing naming strategies, convert with the old class before regenerating.

## No database or source execution

The command parses source and interprets supported helper operations. It does not execute the scanned files, include the generated file, connect to the database, or run the configuration's bootstrap. Supported explicit helpers in older generated classes can also be interpreted.

## Unresolved references

Dynamic arguments, dynamic member or class names, unknown constants, `Tbl::class`, first-class callables, and unsupported helper operations remain unchanged and are reported. For example:

```php
Tbl::users__id($alias); // Requires runtime information; remains unresolved.
```

Resolvable replacements are still applied when others are unresolved. In that case, the command returns `1`.

## Scope and file handling

Only PHP files in the selected directory are considered. The scan skips `vendor`, `.git`, symbolic links, and the selected generated file.

All candidates are parsed before writing. A parse failure aborts without changes. Each changed file is linted and replaced atomically, but the entire directory operation is not a transaction: a later write failure may leave earlier files updated.

Imports, Composer mappings, dependencies, and generated files are not deleted. After conversion, review unresolved references, other source directories, and references constructed through strings or reflection. Run application tests before removing unused imports, autoload entries, and the generated class.
