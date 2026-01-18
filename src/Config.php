<?php

namespace Eril\TblClass;

use Symfony\Component\Yaml\Yaml;
use Exception;

class Config
{
    private array $config = [];
    private string $configFile;
    private bool $isNew = false;

    public function __construct(?string $configFile = null)
    {
        $this->configFile = $configFile ?: getcwd() . '/tblclass.yaml';
        $this->load();
    }

    private function load(): void
    {
        if (!file_exists($this->configFile)) {
            $this->isNew = true;
            $this->createCleanTemplate();
            return;
        }

        try {
            $yamlConfig = Yaml::parseFile($this->configFile);

            $defaults = [
                'include' => null,
                'database' => [
                    'driver' => 'mysql',
                    'connection' => null,
                    'host' => 'localhost',
                    'port' => 3306,
                    'name' => '',
                    'user' => 'root',
                    'password' => '',
                    'path' => 'database.sqlite'
                ],
                'output' => [
                    'path' => './',
                    'namespace' => '',
                    'naming' => [
                        'strategy' => 'full',   // full | abbr | alias
                        'dictionary' => 'all'   // en | pt | es | all
                    ]
                ]
            ];

            $this->config = array_replace_recursive($defaults, $yamlConfig);
            $this->isNew = false;
        } catch (Exception $e) {
            throw new Exception("Error parsing YAML config: " . $e->getMessage());
        }
    }

    private function createCleanTemplate(): void
    {
        $template = <<<YAML
# ------------------------------------------------------------
# tbl-class v1 configuration file
#
# Auto-generated on first run.
# Delete this file to regenerate a clean template.
# ------------------------------------------------------------
# Doc https://github.com/erilshackle/tbl-class-php/wiki/config
# ------------------------------------------------------------

# Enable or disable Tbl class generation
enabled: true       

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
  # - short  → table, tbl__column,   fk__table__references
  # - abbr   → table, tbl__column,   fk__tbl__ref
  # - alias  → table, t__column,     fk__t__r
  # - upper  → TABLE, TABLE__COLUMN, FK__TABLE__REFERENCES
  naming:
    strategy: full
    
YAML;

        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->configFile, $template) === false) {
            throw new Exception("Cannot create config file: " . $this->configFile);
        }

        $this->config = Yaml::parse($template);
        $this->isNew = true;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    private function resolveEnvVars($value, $default = null): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^env\(\s*([A-Z_][A-Z0-9_]*)\s*\)$/', $value, $m)) {
            return getenv($m[1]) ?:  $default;
        }

        if (preg_match('/^\${([A-Z_][A-Z0-9_]*)}$/', $value, $m)) {
            return getenv($m[1]) ?: $default;
        }

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $value)) {
            return getenv($value) ?: $default;
        }

        return $value;
    }

    public function get(string $key, $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $this->resolveEnvVars($value, $default);
    }

    public function getUserIncludingFile(): ?string
    {
        $file = $this->get('include');
        return $file && is_file($file) ? $file : null;
    }

    public function getNamingStrategy(): string
    {
        return $this->get('output.naming.strategy', 'full');
    }

    public function getDatabaseName(): string
    {
        return (string) $this->get('database.name', '');
    }

    public function getDriver(): string
    {
        return (string) $this->get('database.driver', 'mysql');
    }

    public function getOutputPath(): string
    {
        $path = rtrim($this->get('output.path', './'), '/');
        return preg_replace("/[tT]bl$/", "", $path) . '/' . "Tbl/";
    }

    public function isPsr4()
    {
        return $this->get('output.namespace') !== null;
    }

    public function getOutputNamespace(): string
    {
        $base = trim($this->get('output.namespace', ''), '\\');
        if ($base) {
            $base = str_ends_with($base, "Tbl") ? $base : $base . '\\Tbl';
            return $base;
        }
        return 'Tbl';
    }

    public function getTblFile()
    {
        return $this->getOutputPath() . 'Tbl.php';
    }


    public function getNamingConfig(): array
    {
        return $this->config['output']['naming'] ?? []; /////
    }

    public function getNamingProfileConfig(string $profile = 'conventional'): array
    {
        return match ($profile) {
            'conventional' => [
                'strategy'           => 'full',
                'table_concat_style' => 'literal',
                'separator'          => 'double',
                'casing'             => 'uppercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk__',
            ],
            'concise' => [
                'strategy'           => 'abbr',
                'table_concat_style' => 'literal',
                'separator'          => 'single',
                'casing'             => 'lowercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk__',
            ],
            'compact' => [
                'strategy'           => 'abbr',
                'table_concat_style' => 'concatenated',
                'separator'          => 'single',
                'casing'             => 'lowercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk_',
            ],
            'alias' => [
                'strategy'           => 'alias',
                'table_concat_style' => 'literal',
                'separator'          => 'single',
                'casing'             => 'lowercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk__',
            ],
            'uppercase' => [
                'strategy'           => 'full',
                'table_concat_style' => 'literal',
                'separator'          => 'double',
                'casing'             => 'uppercase',
                'enum_prefix'        => 'EV__',
                'fk_prefix'          => 'FK__',
            ],
            'lowercase' => [
                'strategy'           => 'full',
                'table_concat_style' => 'literal',
                'separator'          => 'single',
                'casing'             => 'lowercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk_',
            ],
            default => [
                'strategy'           => 'full',
                'table_concat_style' => 'literal',
                'separator'          => 'double',
                'casing'             => 'uppercase',
                'enum_prefix'        => 'ev__',
                'fk_prefix'          => 'fk__',
            ],
        };
    }




    public function hasConnectionCallback(): bool
    {
        return !empty($this->get('database.connection'));
    }

    public function getConnectionCallback(): ?callable
    {
        $callback = $this->get('database.connection');
        if (!$callback) {
            return null;
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback, 2);

            return function () use ($class, $method) {
                if (!method_exists($class, $method)) {
                    throw new Exception("Connection callback not found: {$class}::{$method}()");
                }
                return $class::$method();
            };
        }

        throw new Exception("Invalid connection callback format");
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }
}
