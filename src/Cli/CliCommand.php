<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Config;
use Eril\TblClass\GeneratorResult;
use Eril\TblClass\Generators\TblClassGenerator;
use Eril\TblClass\Resolvers\ConnectionResolver;
use Eril\TblClass\Schema\MySqlSchemaReader;
use Eril\TblClass\Schema\PgSqlSchemaReader;
use Eril\TblClass\Schema\SchemaReaderInterface;
use Eril\TblClass\Schema\SqliteSchemaReader;
use Exception;
use PDO;
use Throwable;

class CliCommand
{
    private Config $config;
    private PDO $pdo;
    private ?SchemaReaderInterface $schema = null;
    private ?string $output = null;
    private bool $forceGenerate = false;
    private bool $check = false;

    final public function run(array $argv): void
    {
        try {
            $this->parseArgs($argv);
            $this->bootstrap();
            $this->connect();
            $result = $this->execute();
            $this->handleResult($result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    private function parseArgs(array $argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($i === 0) continue;

            switch ($arg) {
                case '--check':
                case '-c':
                    $this->check = true;
                    break;
                case '--generate':
                    $this->forceGenerate = true;
                    break;
                case '--help':
                case '-h':
                    $this->help();
                    exit(0);
                case '--version':
                case '-v':
                    $this->version();
                    exit(0);
                default:
                    if ($arg[0] !== '-') {
                        $this->output = $arg;
                    } else {
                        CliPrinter::error("Unknown option: {$arg}");
                        CliPrinter::line("Use --help to see available options");
                        exit(1);
                    }
            }
        }
    }

    private function bootstrap(): void
    {
        $this->config = new Config();
        $configFile = basename($this->config->getConfigFile());

        if ($this->config->isNew()) {
            CliPrinter::success("Config created: \033[1m{$configFile}");

            CliPrinter::line(str_repeat("-", 80));
            CliPrinter::line("⚠ IMPORTANT – Naming Statregy", 'red');
            CliPrinter::line("The naming strategy defined in tblclass.yaml affects ALL generated constants");
            CliPrinter::line("Changing this strategy later WILL rename constants and MAY break existing code");
            CliPrinter::line("Choose your strategy carefully before first use");
            CliPrinter::line(str_repeat("-", 80));

            CliPrinter::warn("Edit the configuration file and run the command again → set enabled: true");

            exit(0);
        }

        CliPrinter::info("Using config: \033[1m{$configFile}");

        $enabled = $this->config->get('enabled', false);

        if (!$enabled && !$this->forceGenerate && !$this->check) {
            CliPrinter::warn("Generation of constants is currently DISABLED in your tblclass.yaml (`enabled: false`)");
            CliPrinter::line("Open tblclass.yaml and SET [enabled: TRUE] to ENABLE generation", 'blue');
            exit(0);
        }

        $autoload = $this->config->getUserIncludingFile();
        if ($autoload) {
            $filename = basename($autoload);
            try {
                include_once $autoload;
            } catch (Throwable $e) {
                CliPrinter::error("Included: {$filename}\n"
                    . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getMessage());
                exit(1);
            }
            CliPrinter::line("→ Includes: {$filename}", 'blue');
        }

        // Show database info
        $driver = $this->config->getDriver();
        $dbName = $this->config->getDatabaseName();
        CliPrinter::line("→ Database: {$dbName} ({$driver})", 'blue');
    }

    private function connect(): void
    {
        CliPrinter::info("Connecting to database...");

        try {
            $this->pdo = ConnectionResolver::fromConfig($this->config);

            $this->schema = match ($this->config->getDriver()) {
                'mysql'  => new MySqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
                'pgsql'  => new PgSqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
                'sqlite' => new SqliteSchemaReader($this->pdo, $this->config->getDatabaseName()),
                default  => throw new Exception('Unsupported database driver'),
            };

            CliPrinter::success("Connected to database");
        } catch (Exception $e) {
            CliPrinter::error("Connection failed");
            throw $e;
        }
    }

    private function execute(): GeneratorResult
    {
        if ($this->check) {
            CliPrinter::info("Checking for schema changes...");
        } else {
            CliPrinter::info("Generating database constants...");
        }

        $generator = new TblClassGenerator(
            $this->schema,
            $this->config,
            $this->check
        );

        return $generator->run();
    }

    private function handleResult(GeneratorResult $result): void
    {
        if ($this->check) {
            $this->handleCheckResult($result);
        } else {
            $this->handleGenerationResult($result);
        }
    }

    private function handleCheckResult(GeneratorResult $result): void
    {
        if ($result->isSuccess()) {
            CliPrinter::success($result->getMessage());
            exit(0);
        }

        if ($result->isSchemaChanged()) {
            CliPrinter::errorIcon("Schema changed");
            CliPrinter::line("Database schema has been modified since last generation", 'yellow');
            CliPrinter::line("Run the command without --check to regenerate", 'cyan');
            exit(1);
        }

        if ($result->isInitialRequired()) {
            CliPrinter::warn("Initial generation required");
            CliPrinter::line("No previously generated file found", 'cyan');
            CliPrinter::line("Run the command without --check to generate", 'cyan');
            exit(2);
        }

        CliPrinter::error("Check failed: " . $result->getMessage());
        exit(1);
    }

    private function handleGenerationResult(GeneratorResult $result): void
    {
        if ($result->isSuccess()) {
            $message = $result->getMessage();
            $lines = explode("\n", $message);

            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    CliPrinter::success($line);
                    continue;
                }
                if (!empty(trim($line))) {
                    CliPrinter::line($line);
                }
            }

            if (!class_exists("Tbl")) {
                $this->printFinalInstructions();
            }
            exit(0);
        }

        CliPrinter::error("Generation failed: " . $result->getMessage());
        exit(1);
    }

    private function printFinalInstructions(): void
    {
        $namespace = $this->config->get('output.namespace', '');
        $namespace = $namespace ? $this->config->getOutputNamespace() : '';
        $outputFile = str_replace(getcwd() . '/', '', $this->config->getTblFile());
        $namespace = str_replace('\\', '\\\\', $namespace);
        CliPrinter::line("");
        CliPrinter::line("Next steps:", 'bold');

        if ($namespace) {
            CliPrinter::line("Add to your composer.json:", 'cyan');
            CliPrinter::line("  \"autoload\": {");
            CliPrinter::line("    \"psr-4\": {");
            CliPrinter::line("      \"" . trim($namespace, '\\') . "\\\\\": \"" . dirname($outputFile) . "/\"", 'bold');
            CliPrinter::line("    }");
            CliPrinter::line("  }");
        } else {
            CliPrinter::line("Add to your composer.json:", 'cyan');
            CliPrinter::line("  \"autoload\": {");
            CliPrinter::line("    \"files\": [");
            CliPrinter::line("      \"{$outputFile}\"", 'bold');
            CliPrinter::line("    ]");
            CliPrinter::line("  }");
        }

        CliPrinter::line("");
        CliPrinter::line("Then run:", 'cyan');
        CliPrinter::line("  composer dump-autoload", 'bold');
        CliPrinter::line("");
    }

    private function handleException(Exception $e): void
    {
        $message = $e->getMessage();

        CliPrinter::error("Error: {$message}");

        // Helpful tips
        if (str_contains($message, 'DB_NAME') || str_contains($message, 'database name')) {
            CliPrinter::line("");
            CliPrinter::line("Tip:", 'yellow');
            CliPrinter::line("  The database name is not configured.");
            CliPrinter::line("  Set 'database.name' in your config file");
            CliPrinter::line("  Or use environment variable: export DB_NAME=your_database");
        } elseif (str_contains($message, 'connection failed') || str_contains($message, 'SQLSTATE')) {
            CliPrinter::line("");
            CliPrinter::line("Tip:", 'yellow');
            CliPrinter::line("  Could not connect to the database.");
            CliPrinter::line("  Check your credentials in the config file");
            CliPrinter::line("  Make sure the database server is running");
        } elseif (str_contains($message, 'No tables found')) {
            CliPrinter::line("");
            CliPrinter::line("Tip:", 'yellow');
            CliPrinter::line("  The database is empty or has no tables.");
            CliPrinter::line("  Check if you're connecting to the right database");
            CliPrinter::line("  Create some tables before generating constants");
        }

        CliPrinter::line("");
        exit(1);
    }

    private function help(): void
    {
        echo <<<HELP
\033[1mTBL-CLASS - Database Schema to PHP Constants\033[0m

\033[1mUsage:\033[0m
  tbl-class [options]

\033[1mOptions:\033[0m
  --check, -c    Check for schema changes without generating
  --help, -h     Display this help message
  --version, -v  Display version information

\033[1mExamples:\033[0m
  Generate constants (output path is read from config):
    tbl-class

  Check for schema changes:
    tbl-class --check

  Get help:
    tbl-class --help

\033[1mExit codes:\033[0m
  0  Success
  1  Error / Schema changed
  2  Initial generation required

HELP;
    }

    private function version(): void
    {
        $version = '1.0.0';

        echo <<<VERSION
\033[1mtbl-class \033[32mv{$version}\033[0m

Database schema to PHP constants generator
© 2026 Eril TS Carvalho - TblClass

VERSION;
    }
}
