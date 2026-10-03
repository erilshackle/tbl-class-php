# Autoloading

The generator produces one `Tbl.php` file. Your application must load that file independently of the generator's development dependency.

## Default output

```yaml
output:
  path: "./"
  namespace: ""
```

This writes `Tbl/Tbl.php` with the fully qualified class name `Tbl\Tbl`. Merge this mapping into Composer:

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

```php
use Tbl\Tbl;

echo Tbl::users;
```

## Existing application namespace

```yaml
output:
  path: ./src/Database
  namespace: 'App\Database'
```

This produces `src/Database/Tbl/Tbl.php` and `App\Database\Tbl\Tbl`. An existing mapping of `"App\\": "src/"` covers it:

```php
use App\Database\Tbl\Tbl;
```

## Composer files autoload

You can load the file eagerly instead:

```json
{
  "autoload": {
    "files": ["Tbl/Tbl.php"]
  }
}
```

The class remains namespaced as `Tbl\Tbl`; using `files` does not make it global. Generate the file before executing an application whose autoloader includes it.

## Production deployments

Keep the mapping under `autoload`, not `autoload-dev`, if production code uses Tbl. Include the generated file in your deployment, either by committing it in your application or generating it during a build with database access.

The YAML `include` option loads a bootstrap during CLI generation and check. It does not configure application autoloading. See [configuration](./configuration.md#bootstrap-and-environment-variables).
