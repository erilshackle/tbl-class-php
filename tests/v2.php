<?php

use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Independence\GeneratedModel;
use Eril\TblClass\Independence\IndependenceCommand;
use Eril\TblClass\Resolvers\NamingResolver;

foreach (['abbr', 'alias', 'upper', 'typo', 'Full', 'Short', 'sHoRt', ' full', 'FULL ', '', null, 1] as $strategy) {
    try {
        new NamingResolver(['strategy' => $strategy]);
        throw new RuntimeException('Invalid strategy accepted');
    } catch (InvalidArgumentException $e) {
        verify(str_contains($e->getMessage(), 'v2'), 'Migration error must explain v2');
    }
}
foreach ([['separator' => 'single'], ['fk_prefix' => 'f_'], ['case' => 'mixed'], ['case' => 'lower'], ['case' => 'upper'], ['overrides' => ['users' => 'bad-name']]] as $invalid) {
    try {
        new NamingResolver($invalid);
        throw new RuntimeException('Invalid naming accepted');
    } catch (InvalidArgumentException $e) {
        verify(true, 'Invalid naming rejected');
    }
}
$resolver = new NamingResolver(['strategy' => 'short', 'overrides' => ['users' => 'usr', 'posts' => 'pst']]);
foreach (['full' => 'authentication__id', 'FULL' => 'AUTHENTICATION__ID', 'short' => 'auth__id', 'SHORT' => 'AUTH__ID'] as $strategy => $expected) {
    $strategyResolver = new NamingResolver(['strategy' => $strategy]);
    verify($strategyResolver->getColumnConstName('authentication', 'id') === $expected, 'Strategy must control both abbreviation and casing without overrides');
    verify(!array_key_exists('case', $strategyResolver->getProfile()), 'Effective naming profile must not contain case');
}
verify($resolver->getColumnConstName('users', 'id') === 'usr__id', 'Override must apply to columns');
verify($resolver->getForeignKeyConstName('posts', 'users') === 'fk__pst__usr', 'Short must abbreviate FK endpoints');
verify($resolver->getOnJoinConstName('posts', 'users') === 'on__pst__usr', 'Short must abbreviate JOIN endpoints');
verify($resolver->getTableConstName('users', true) === 'users', 'Table constants stay full');
$before = $resolver->getColumnConstName('orders', 'id');
$resolver->getColumnConstName('other_orders', 'id');
verify($resolver->getColumnConstName('orders', 'id') === $before, 'Adding tables must not change existing names');
$collisionConfig = config($directory, 'short', 'Collision', ['users' => 'x', 'posts' => 'x']);
verify(str_contains((new FileClassGenerator(new TestSchema(), $collisionConfig))->run()->getMessage(), 'Constant collision'), 'Override collisions must be rejected');

foreach (['full', 'FULL', 'short', 'SHORT'] as $index => $strategy) {
    $upper = $strategy === strtoupper($strategy);
    $v2Config = config($directory, $strategy, 'V2Strategy' . $index, ['users' => 'usr', 'posts' => 'pst']);
    verify($v2Config->getNamingStrategy() === $strategy, 'Config must preserve uppercase strategies as literal values');
    verify((new FileClassGenerator(new TestSchema(), $v2Config))->run()->isSuccess(), 'v2 generation failed');
    require $v2Config->getTblFile();
    $v2Class = $v2Config->getOutputNamespace() . '\\Tbl';
    $name = $upper ? 'ON__PST__USR' : 'on__pst__usr';
    verify($v2Class::$name('p', 'u') === 'p.user_id = u.id', 'JOIN helper must work with both cases');
    $name = $upper ? 'USERS' : 'users';
    verify($v2Class::$name('u') === 'users AS u', 'Table SQL must retain original casing');
    $model = new GeneratedModel($v2Config->getTblFile());
    verify($model->call($name, ['u']) === $v2Class::$name('u'), 'Static evaluator must match generated helper');
}

$v2Config = config($directory, 'full', 'Independent');
verify((new FileClassGenerator(new TestSchema(), $v2Config))->run()->isSuccess(), 'Independence fixture generation failed');
$generated = $v2Config->getTblFile();
$generatedContent = file_get_contents($generated);
// Neither this source nor generated PHP may be executed by independence.
file_put_contents($generated, $generatedContent . "\nthrow new \\RuntimeException('GENERATED FILE EXECUTED');\n");
$scan = $directory . '/application';
mkdir($scan);
mkdir($scan . '/vendor');
mkdir($scan . '/.git');
$source = <<<'PHP'
<?php
namespace Example;
use Independent\Tbl\{Tbl as T};
// Keep T::users exactly as written in this comment.
$text = 'T::users';
return [
    T::users,
    \Independent\Tbl\Tbl::users__id,
    T::users('u'),
    T::users__id(alias: 'u'),
    T::users__id(),
    T::on__posts__users('p', 'u'),
    T:: /* preserve this */ posts__id,
];
PHP;
file_put_contents($scan . '/query.php', $source);
file_put_contents($scan . '/vendor/skip.php', '<?php broken syntax');
file_put_contents($scan . '/.git/skip.php', '<?php broken syntax');
file_put_contents($scan . '/unrelated.php', '<?php namespace Other; return Tbl::users;');
$runner = new IndependenceCommand();
$report = $runner->run($scan, $generated, true);
verify($report['replacements'] === 7 && !$report['pending'], 'Dry-run must resolve all literal references: ' . json_encode($report));
verify(file_get_contents($scan . '/query.php') === $source, 'Dry-run must not write');
[$code, $output] = cli($directory, ['independence', $scan, '--dry-run', '--generated', $generated]);
verify($code === 0 && str_contains($output, '7 replacements'), 'CLI preview failed: ' . $output);
verify(str_contains($output, 'query.php:') && str_contains($output, ' -> '), 'Dry-run must show concrete replacements');
$report = $runner->run($scan, $generated, false);
verify($report['replacements'] === 7, 'Apply count incorrect');
$converted = file_get_contents($scan . '/query.php');
verify(str_contains($converted, '// Keep T::users') && str_contains($converted, "'T::users'") && str_contains($converted, '/* preserve this */'), 'Strings and comments must survive');
verify((require $scan . '/query.php') === ['users', 'id', 'users AS u', 'u.id', 'users.id', 'p.user_id = u.id', 'id'], 'Converted file must return expected values without loading Tbl');
verify(str_contains(file_get_contents($scan . '/unrelated.php'), 'Tbl::users'), 'Unrelated Tbl classes must be untouched');
verify($runner->run($scan, $generated, false)['replacements'] === 0, 'Second run must be idempotent');
verify(file_get_contents($generated) === $generatedContent . "\nthrow new \\RuntimeException('GENERATED FILE EXECUTED');\n", 'Generated file must be preserved');

$dynamic = <<<'PHP'
<?php
use Independent\Tbl\Tbl as T;
$a = T::users__id($alias);
$b = T::{$method}('u');
$c = $class::users;
$d = T::class;
$e = T::users(...);
$f = T::missing;
$g = T::users;
PHP;
file_put_contents($scan . '/dynamic.php', $dynamic);
[$code, $output] = cli($directory, ['independence', $scan, '--generated', $generated]);
verify($code === 1 && str_contains($output, '6 unresolved references'), 'Dynamic references must return pending status: ' . $output);
verify(str_contains(file_get_contents($scan . '/dynamic.php'), 'T::users__id($alias)')
    && str_contains(file_get_contents($scan . '/dynamic.php'), "\$g = 'users';"), 'Only resolvable references may change');

file_put_contents($scan . '/a.php', '<?php use Independent\Tbl\Tbl; return Tbl::users;');
file_put_contents($scan . '/z.php', '<?php broken {');
try {
    $runner->run($scan, $generated, false);
    throw new RuntimeException('Invalid PHP should abort preflight');
} catch (RuntimeException $e) {
    verify(str_contains($e->getMessage(), 'No files were written'), 'Invalid PHP must abort before writes');
}
verify(str_contains(file_get_contents($scan . '/a.php'), 'Tbl::users'), 'Preflight failure must preserve earlier candidates');
unlink($scan . '/z.php');

// A helper containing unsupported operations is reported, never invoked.
$unsafe = $directory . '/unsafe.php';
file_put_contents($unsafe, '<?php namespace Unsafe; final class Tbl { public const users = "users"; public static function users($alias = null): string { file_put_contents("SHOULD_NOT_EXIST", "bad"); return "users"; }}');
$unsafeScan = $directory . '/unsafe-app';
mkdir($unsafeScan);
file_put_contents($unsafeScan . '/use.php', '<?php return \Unsafe\Tbl::users();');
$report = $runner->run($unsafeScan, $unsafe, false);
verify(count($report['pending']) === 1 && $report['replacements'] === 0, 'Unsupported helper must remain unresolved');
[$code] = cli($directory, ['independence']);
verify($code === 2, 'Independence requires an explicit directory');
[$code] = cli($directory, ['generate', '--dry-run']);
verify($code === 2, 'Dry-run must be scoped to independence');
[$code, $output] = cli($directory, ['--version']);
verify($code === 0 && str_contains($output, '2.0.0'), 'CLI must report v2.0.0');

// Legacy explicit helpers can be converted without loading the legacy class.
$legacyHelpers = $directory . '/legacy-helpers.php';
file_put_contents($legacyHelpers, '<?php namespace Legacy; final class Tbl { public const users__id = "id"; public static function users__id(?string $alias = null): string { return ($alias === null || $alias === "" ? "users" : $alias) . "." . self::users__id; }}');
$legacyModel = new GeneratedModel($legacyHelpers);
verify($legacyModel->call('users__id', ['alias' => 'u']) === 'u.id', 'Explicit legacy helper must resolve without execution');
verify($legacyModel->call('users__id', []) === 'users.id', 'Legacy helper defaults must be respected');

// Parser errors abort preflight, and configuration bootstrap must not execute.
$sideEffect = $directory . '/bootstrap.php';
file_put_contents($sideEffect, '<?php throw new RuntimeException("BOOTSTRAP EXECUTED");');
$configData = Symfony\Component\Yaml\Yaml::parseFile($directory . '/tblclass.yaml');
$configData['include'] = $sideEffect;
$configData['database']['driver'] = 'not-a-real-driver';
file_put_contents($directory . '/tblclass.yaml', Symfony\Component\Yaml\Yaml::dump($configData, 5));
[$code, $output] = cli($directory, ['independence', $unsafeScan, '--dry-run']);
verify($code === 0 && !str_contains($output, 'BOOTSTRAP EXECUTED'), 'Independence must not bootstrap includes or connect to a database');
$configData['output']['naming'] = ['strategy' => 'abbr'];
file_put_contents($directory . '/tblclass.yaml', Symfony\Component\Yaml\Yaml::dump($configData, 5));
[$code, $output] = cli($directory, ['generate']);
verify($code === 1 && str_contains($output, 'v2 naming.strategy') && !str_contains($output, 'BOOTSTRAP EXECUTED'), 'Legacy naming must fail before bootstrap/database access');
