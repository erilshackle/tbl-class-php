<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Config;
use Eril\TblClass\GeneratorResult;
use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Introspection\GeneratedClassMetadata;
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
    private ?string $command = null;
    private bool $check = false;
    private bool $diff = false;

    final public function run(array $argv): void
    {
        try {
            $this->parseArgs($argv);
            if ($this->command === null) {
                $this->listCommands();
                return;
            }

            if ($this->command === 'init') {
                $this->initialize();
                return;
            }

            $this->bootstrap();
            $this->connect();
            $result = $this->execute();
            $this->handleResult($result);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function parseArgs(array $argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($i === 0) continue;

            switch ($arg) {
                case '--diff':
                    $this->diff = true;
                    break;
                case '--check':
                case '-c':
                    $this->setCommand('check');
                    $this->check = true;
                    break;
                case '--generate':
                    $this->setCommand('generate');
                    break;
                case '--help':
                case '-h':
                case 'help':
                    $this->help();
                    exit(0);
                case '--version':
                case '-v':
                    $this->version();
                    exit(0);
                case 'init':
                case 'generate':
                case 'check':
                    $this->setCommand($arg);
                    $this->check = $arg === 'check';
                    break;
                default:
                    $kind = str_starts_with($arg, '-') ? 'option' : 'command';
                    CliPrinter::error("Unknown {$kind}: {$arg}");
                    CliPrinter::line("Use --help to see available commands");
                    exit(2);
            }
        }
        if ($this->diff && $this->command !== 'check') {
            CliPrinter::error('--diff is only available with check');
            exit(2);
        }
    }

    private function setCommand(string $command): void
    {
        if ($this->command !== null) {
            CliPrinter::error("Only one command can be specified");
            exit(2);
        }

        $this->command = $command;
    }

    private function initialize(): void
    {
        $configFile = getcwd() . '/tblclass.yaml';
        if (file_exists($configFile)) {
            CliPrinter::error("Config already exists: tblclass.yaml");
            exit(2);
        }

        new Config($configFile);
        CliPrinter::success("Config created: \033[1mtblclass.yaml");
        CliPrinter::line("Edit the configuration, then run: tbl-class generate");
    }

    private function listCommands(): void
    {
            $version = TBLCLASS_VERSION;
                CliPrinter::line("tbl-class v" . TBLCLASS_VERSION);
                CliPrinter::line();
                CliPrinter::line("Commands:");
                CliPrinter::line("  init      Create the configuration file");
                CliPrinter::line("  generate  Generate the Tbl class");
                CliPrinter::line("  check     Check for schema changes");
                CliPrinter::line("  help      Show detailed help");
    }

    private function bootstrap(): void
    {
        $configFile = getcwd() . '/tblclass.yaml';
        if (!is_file($configFile)) {
            throw new Exception("Config file not found. Run 'tbl-class init' first.");
        }

        $this->config = new Config($configFile);
        $configFile = basename($this->config->getConfigFile());

        CliPrinter::info("Using config: \033[1m{$configFile}");

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

        if ($this->check) {
            $hash = substr(GeneratedClassMetadata::extractSchemaHash($this->config->getTblFile()) ?? '', 0, 16);
            CliPrinter::line("→ SavedHash: {$hash}..", 'blue');
        } else {
            $naming = $this->config->getNamingStrategy();
            CliPrinter::line("→ Strategy: {$naming}", 'blue');
        }
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

        $generator = new FileClassGenerator(
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
            CliPrinter::errorIcon($result->getMessage());
            if ($this->diff) {
                foreach ($result->getData()['diff'] ?? [] as $change) {
                    CliPrinter::line($change);
                }
            }
            CliPrinter::line("Run 'tbl-class generate' to regenerate", 'cyan');
            exit(1);
        }

        if ($result->isInitialRequired()) {
            CliPrinter::warn("Initial generation required");
            CliPrinter::line("No previously generated file found", 'cyan');
            CliPrinter::line("Run 'tbl-class generate' to generate", 'cyan');
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
        $namespace = $this->config->isPsr4() ? $this->config->getOutputNamespace() : '';
        $namespace = str_replace('\\', '\\\\', $namespace);
        $outputFile = str_replace(getcwd() . '/', '', $this->config->getTblFile());
        CliPrinter::line("");
        CliPrinter::line("Next step:", 'bold');

        CliPrinter::out("Add to your \033[1mcomposer.json", 'cyan');
        CliPrinter::out(", then run \033[4mcomposer dump-autoload", 'cyan');
        CliPrinter::line(":", 'cyan');
        if ($namespace) {
            CliPrinter::line("  \"autoload\": {");
            CliPrinter::line("    \"psr-4\": {");
            CliPrinter::line("      \"" . trim($namespace, '\\') . "\\\\\": \"" . dirname($outputFile) . "/\"", 'bold');
            CliPrinter::line("    }");
            CliPrinter::line("  }");
        } else {
            CliPrinter::line("  \"autoload\": {");
            CliPrinter::line("    \"files\": [");
            CliPrinter::line("      \"{$outputFile}\"", 'bold');
            CliPrinter::line("    ]");
            CliPrinter::line("  }");
        }
        CliPrinter::line("");
    }

    private function handleException(Throwable $e): void
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
    tbl-class <command>

\033[1mCommands:\033[0m
    init           Create tblclass.yaml
    generate       Generate the Tbl class
    check          Check for schema changes without generating
    help           Display detailed help

\033[1mOptions:\033[0m
  --help, -h     Display this help message
  --version, -v  Display version information
  --diff         Show schema/configuration differences (check only)

\033[1mExamples:\033[0m
    Initialize configuration:
        tbl-class init

    Generate the Tbl class:
        tbl-class generate

  Check for schema changes:
        tbl-class check
        tbl-class check --diff

    Legacy flags remain available:
        tbl-class --generate
        tbl-class --check

  Get help:
    tbl-class --help

\033[1mExit codes:\033[0m
  0  Success
  1  Error / Schema changed
    2  Invalid command/config or initial generation required

HELP;
    }

    private function version(): void
    {
        $version = TBLCLASS_VERSION;

        echo <<<VERSION
\033[1mtbl-class \033[32mv{$version}\033[0m

Database schema to PHP constants generator
© 2026 Eril TS Carvalho - TblClass

VERSION;
    }
}
