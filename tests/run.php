<?php

require __DIR__ . '/../vendor/autoload.php';

use Eril\TblClass\Config;
use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Generators\PhpOutput;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;
use Eril\TblClass\Schema\SqliteSchemaReader;
use Symfony\Component\Yaml\Yaml;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

final class TestSchema implements SchemaReaderInterface
{
    public array $tables = ['users' => ['id', 'status'], 'posts' => ['id', 'user_id']];
    public array $enums = ['users' => ['status' => ['active', 'pending']]];
    public array $foreignKeys = [
        ['from_table' => 'posts', 'from_column' => 'user_id', 'to_table' => 'users', 'to_column' => 'id'],
    ];
    public function getDatabaseName(): string { return 'test'; }
    public function getTables(): array { return array_keys($this->tables); }
    public function getColumns(string $table): array { return $this->tables[$table]; }
    public function getEnumColumns(string $table): array { return $this->enums[$table] ?? []; }
    public function getForeignKeys(): array { return $this->foreignKeys; }
}

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function config(string $directory, string $strategy = 'full', string $namespace = 'Fixture', array $overrides = []): Config
{
    $file = $directory . '/tblclass.yaml';
    file_put_contents($file, Yaml::dump([
        'database' => ['driver' => 'sqlite', 'path' => $directory . '/test.sqlite', 'name' => 'test'],
        'output' => ['path' => $directory, 'namespace' => $namespace, 'naming' => ['strategy' => $strategy, 'overrides' => $overrides]],
    ], 5));
    return new Config($file);
}

function cli(string $directory, array $arguments): array
{
    $process = proc_open(array_merge([PHP_BINARY, __DIR__ . '/../bin/tbl-class'], $arguments), [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1],
    ], $pipes, $directory);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($process), $output];
}

// All fixtures are isolated in a fresh temporary directory, never the user's database/output.
$directory = sys_get_temp_dir() . '/tbl-class-test-' . bin2hex(random_bytes(8));
mkdir($directory);
try {
    $config = config($directory);
    $naming = new NamingResolver();
    verify($naming->getColumnConstName('users', 'class') === 'users__class', 'Valid existing column names must remain unchanged');
    verify($naming->getTableConstName('class', true) === '_class', 'Reserved class constant must be normalized');
    verify($naming->getTableConstName('123', true) === '_123', 'Numeric table name must be prefixed');
    $schema = new TestSchema();
    $generate = static fn() => (new FileClassGenerator($schema, $config))->run();
    $check = static fn() => (new FileClassGenerator($schema, $config, true))->run();
    verify($check()->isInitialRequired(), 'Missing output must require initial generation');
    verify($generate()->isSuccess(), 'Initial generation failed');
    $file = $config->getTblFile();
    $original = file_get_contents($file);
    verify($check()->isSuccess(), 'Unchanged schema must pass');
    $previousSnapshot = \Eril\TblClass\Introspection\GeneratedClassMetadata::extractSnapshot($file);
    unset($previousSnapshot['generation']['output.column_helpers']);
    $previousOutput = preg_replace('/@generation-snapshot [A-Za-z0-9+\/=]+/',
        '@generation-snapshot ' . base64_encode(json_encode($previousSnapshot)), $original);
    $previousOutput = preg_replace('/@schema-hash md5:[a-f0-9]{32}/',
        '@schema-hash md5:' . \Eril\TblClass\Introspection\SchemaHasher::hash($previousSnapshot), $previousOutput);
    file_put_contents($file, $previousOutput);
    verify($check()->isSchemaChanged(), 'Check must request regeneration for output without column helpers');
    file_put_contents($file, $original);
    require $file;
    verify(\Fixture\Tbl\Tbl::users__id === 'id', 'Generated class must load with correct values');
    verify(!method_exists(\Fixture\Tbl\Tbl::class, 'users__id'), 'Column helpers must use magic dispatch without explicit methods');
    verify(\Fixture\Tbl\Tbl::users__id() === 'users.id', 'Column helper must default to the real table');
    verify(\Fixture\Tbl\Tbl::users__id(alias: 'u') === 'u.id', 'Column helper must accept a named alias');
    verify(\Fixture\Tbl\Tbl::users__id('author') === 'author.id'
        && \Fixture\Tbl\Tbl::users__id('editor') === 'editor.id'
        && \Fixture\Tbl\Tbl::users__id() === 'users.id', 'Aliases must not retain state');
    verify(\Fixture\Tbl\Tbl::users__id('') === 'users.id'
        && \Fixture\Tbl\Tbl::users__id(null) === 'users.id', 'Empty/null aliases must use the table');
    $query = 'SELECT ' . \Fixture\Tbl\Tbl::users__id('u') . ' AS user_id, '
        . \Fixture\Tbl\Tbl::posts__id('p') . ' AS post_id FROM '
        . \Fixture\Tbl\Tbl::users('u') . ' JOIN ' . \Fixture\Tbl\Tbl::posts('p')
        . ' ON ' . \Fixture\Tbl\Tbl::on__posts__users('p', 'u');
    $joinDb = new PDO('sqlite::memory:');
    $joinDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $joinDb->exec('CREATE TABLE users (id INTEGER); CREATE TABLE posts (id INTEGER, user_id INTEGER); INSERT INTO users VALUES (1); INSERT INTO posts VALUES (2, 1)');
    verify($joinDb->query($query)->fetch(PDO::FETCH_ASSOC) === ['user_id' => 1, 'post_id' => 2], 'Qualified JOIN must execute without ambiguous IDs');
    verify(\Fixture\Tbl\Tbl::on__posts__users('p', 'u') === 'p.user_id = u.id', 'JOIN helper regression');

    $schema->tables['users'][] = 'phone';
    $result = $check();
    verify($result->isSchemaChanged() && in_array('+ users.phone', $result->getData()['diff']), 'Added column diff missing');
    verify(file_get_contents($file) === $original, 'Check must not write output');
    array_pop($schema->tables['users']);
    $schema->enums['users']['status'][] = 'blocked';
    verify(str_contains(implode('\n', $check()->getData()['diff']), '~ enum users.status:'), 'Enum changes must be detected');
    array_pop($schema->enums['users']['status']);
    $schema->foreignKeys = [];
    verify(str_contains(implode('\n', $check()->getData()['diff']), '- FK posts.user_id -> users.id'), 'Removed FK diff missing');
    $schema->tables = [];
    verify($check()->isSchemaChanged(), 'Removing all tables must be detected');
    verify(!$generate()->isSuccess() && file_get_contents($file) === $original, 'Empty generation must preserve output');
    $schema = new TestSchema();
    $changedConfig = config($directory, 'short');
    $result = (new FileClassGenerator($schema, $changedConfig, true))->run();
    verify(in_array('~ output.naming.strategy: "full" -> "short"', $result->getData()['diff']), 'Naming diff missing');
    $changedConfig = config($directory, 'full', 'Changed');
    verify((new FileClassGenerator($schema, $changedConfig, true))->run()->isSchemaChanged(), 'Namespace changes must be detected');
    $config = config($directory);
    $schema->tables = array_reverse($schema->tables, true);
    $schema->tables['users'] = array_reverse($schema->tables['users']);
    verify((new FileClassGenerator($schema, $config, true))->run()->isSuccess(), 'Reader ordering must not affect hash');

    $schema->tables = ['a-b' => ['id'], 'a b' => ['id']];
    $schema->foreignKeys = [];
    $result = (new FileClassGenerator($schema, $config))->run();
    verify(!$result->isSuccess() && str_contains($result->getMessage(), 'Constant collision'), 'Normalized collision must fail');
    verify(file_get_contents($file) === $original, 'Collision must preserve previous output');
    $schema = new TestSchema();
    $schema->foreignKeys[] = ['from_table' => 'posts', 'from_column' => 'editor_id', 'to_table' => 'users', 'to_column' => 'id'];
    verify(str_contains((new FileClassGenerator($schema, $config))->run()->getMessage(), 'Constant collision'), 'Duplicate FK names must fail without being renamed');
    verify(file_get_contents($file) === $original, 'FK collision must preserve previous output');
    $badConfig = config($directory, 'full', 'Invalid-namespace');
    verify(str_contains((new FileClassGenerator(new TestSchema(), $badConfig))->run()->getMessage(), 'Invalid output namespace'), 'Invalid namespace must be rejected');
    verify(file_get_contents($file) === $original, 'Invalid namespace must preserve previous output');

    try {
        PhpOutput::write($file, '<?php final class Broken { public const x = ; }');
        throw new RuntimeException('Invalid PHP unexpectedly accepted');
    } catch (RuntimeException $e) {
        verify(str_contains($e->getMessage(), 'Generated PHP is invalid'), 'Lint must reject invalid output');
    }
    verify(file_get_contents($file) === $original && glob(dirname($file) . '/.tbl-*') === [], 'Lint failure must preserve output and remove temporary files');

    $schema = new TestSchema();
    $schema->tables = ["9 odd'\\name*/" => ["a'b\\c", 'class', 'line' . "\n" . 'break']];
    $schema->foreignKeys = [];
    $schema->enums = ["9 odd'\\name*/" => ["a'b\\c" => ['*/ ?> injected', "one\ntwo"]]];
    $schema->foreignKeys = [[
        'from_table' => "9 odd'\\name*/", 'from_column' => "a'b\\c", 'to_table' => 'users', 'to_column' => 'id',
    ]];
    foreach (['full', 'short', 'FULL', 'SHORT'] as $index => $strategy) {
        $config = config($directory, $strategy, 'EscapedStrategy' . $index);
        $result = (new FileClassGenerator($schema, $config))->run();
        verify($result->isSuccess(), "Unusual identifiers failed for {$strategy}: " . $result->getMessage());
        require $config->getTblFile();
        $class = $config->getOutputNamespace() . '\\Tbl';
        $naming = new NamingResolver($config->getNamingConfig());
        $table = array_key_first($schema->tables);
        verify(constant($class . '::' . $naming->getTableConstName($table, true)) === $table, 'Table value must round-trip');
        verify(constant($class . '::' . $naming->getColumnConstName($table, "a'b\\c")) === "a'b\\c", 'Column value must round-trip');
        $method = $naming->getColumnConstName($table, "a'b\\c");
        verify(!method_exists($class, $method) && $class::$method() === $table . ".a'b\\c", 'Magic column helpers must preserve original identifiers under every naming strategy');
        verify($class::$method('x') === "x.a'b\\c", 'Qualified column must preserve escaped values');
        verify(constant($class . '::' . $naming->getOnJoinConstName($table, 'users')) === $table . ".a'b\\c = users.id", 'JOIN literal must round-trip');
    }

    $config = config($directory);
    $schema = new TestSchema();
    $legacy = "<?php\n/**\n * @schema-hash md5:" . str_repeat('a', 32) . "\n */\n";
    $standalone = new TestSchema();
    $standalone->tables = ['users' => ['id']];
    $standalone->foreignKeys = [];
    $standaloneConfig = config($directory, 'full', 'Standalone');
    verify((new FileClassGenerator($standalone, $standaloneConfig))->run()->isSuccess(), 'Generation without FKs failed');
    require $standaloneConfig->getTblFile();
    verify(\Standalone\Tbl\Tbl::users('u') === 'users AS u'
        && \Standalone\Tbl\Tbl::users__id('u') === 'u.id', 'Table/column helpers must work without FKs');
    $config = config($directory);
    file_put_contents($file, $legacy);
    $result = (new FileClassGenerator($schema, $config, true))->run();
    verify($result->isSchemaChanged() && str_contains($result->getData()['diff'][0], 'no snapshot'), 'Legacy output must explain migration');

    $pdo = new PDO('sqlite:' . $directory . '/test.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec('CREATE TABLE "odd\'table" ("odd\'column" TEXT)');
    $reader = new SqliteSchemaReader($pdo, 'test');
    verify($reader->getColumns("odd'table") === ["odd'column"], 'SQLite reader must escape identifiers');
    [$code, $output] = cli($directory, ['generate']);
    verify($code === 0, 'CLI generation failed: ' . $output);
    [$code, $output] = cli($directory, ['check', '--diff']);
    verify($code === 0 && str_contains($output, 'up to date'), 'CLI unchanged check failed: ' . $output);
    $beforeCheck = file_get_contents($file);
    $pdo->exec('ALTER TABLE users ADD COLUMN phone TEXT');
    [$code, $output] = cli($directory, ['check', '--diff']);
    verify($code === 1 && str_contains($output, '+ users.phone'), 'CLI diff failed: ' . $output);
    verify(file_get_contents($file) === $beforeCheck, 'CLI check must not write');
    [$code, $output] = cli($directory, ['--check', '--diff']);
    verify($code === 1 && str_contains($output, '+ users.phone'), 'Legacy check flag must support diff');
    [$code, $output] = cli($directory, ['generate', '--diff']);
    verify($code === 2 && str_contains($output, 'only available with check'), 'Invalid diff combination must fail');
    [$code, $output] = cli($directory, ['--diff']);
    verify($code === 2, 'Standalone diff must fail');
    file_put_contents($file, str_replace('@generation-snapshot ', '@generation-snapshot corrupt', $beforeCheck));
    [$code, $output] = cli($directory, ['check', '--diff']);
    verify($code === 1 && str_contains($output, 'Check failed:'), 'Invalid snapshot must report an error, not schema drift');
    unlink($file);
    [$code, $output] = cli($directory, ['check', '--diff']);
    verify($code === 2 && str_contains($output, 'Initial generation required'), 'Missing output CLI status incorrect');
    require __DIR__ . '/v2.php';
    echo "OK: {$checks} assertions\n";
} finally {
    unset($reader, $pdo);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($directory);
}
