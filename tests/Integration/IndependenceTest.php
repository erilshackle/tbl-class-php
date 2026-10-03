<?php

namespace Tests\Integration;

use Eril\TblClass\Independence\IndependenceCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SqliteTestCase;

final class IndependenceTest extends SqliteTestCase
{
    private string $generated;
    private string $class;
    private string $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBlog();
        $config = $this->generate();
        $this->generated = $config->getTblFile();
        $this->class = $config->getOutputNamespace() . '\\Tbl';
        $this->application = $this->directory . '/app';
        mkdir($this->application);
    }

    private function consumer(string $expression): string
    {
        return $this->write('app/query.php', '<?php use ' . $this->class . ' as T; return ' . $expression . ';');
    }

    private function convert(bool $dryRun = false): array
    {
        return (new IndependenceCommand())->run($this->application, $this->generated, $dryRun);
    }

    public static function literalReferences(): array
    {
        return ['table constant' => ['T::users', 'users'], 'column constant' => ['T::users__id', 'id'],
            'table alias' => ["T::users('u')", 'users AS u'], 'column alias' => ["T::users__id(alias: 'u')", 'u.id'],
            'column no alias' => ['T::users__id()', 'id'], 'null alias' => ['T::users__id(null)', 'id'],
            'join aliases' => ["T::on__posts__users('p', 'u')", 'p.user_id = u.id']];
    }

    #[DataProvider('literalReferences')]
    public function testReplacesLiteralReferenceWithoutLoadingGeneratedClass(string $expression, string $expected): void
    {
        $file = $this->consumer($expression);
        $report = $this->convert();
        self::assertSame(1, $report['replacements']);
        self::assertSame([], $report['pending']);
        self::assertSame($expected, require $file);
        self::assertFalse(class_exists($this->class, false));
    }

    public static function unresolvedReferences(): array
    {
        return ['variable argument' => ['T::users__id($alias)'], 'variable method' => ["T::{\$method}('u')"],
            'variable class' => ['$class::users'], 'class name' => ['T::class'],
            'first class callable' => ['T::users(...)'], 'unknown constant' => ['T::missing']];
    }

    #[DataProvider('unresolvedReferences')]
    public function testUnresolvedReferenceIsReportedAndPreserved(string $expression): void
    {
        $file = $this->consumer($expression);
        $original = file_get_contents($file);
        $report = $this->convert();
        self::assertCount(1, $report['pending']);
        self::assertSame(0, $report['replacements']);
        self::assertSame($original, file_get_contents($file));
    }

    public function testDryRunReportsReplacementWithoutWriting(): void
    {
        $file = $this->consumer('T::users');
        $original = file_get_contents($file);
        $report = $this->convert(true);
        self::assertSame(1, $report['replacements']);
        self::assertCount(1, $report['changes']);
        self::assertSame($original, file_get_contents($file));
    }

    public function testSecondRunHasNothingToReplace(): void
    {
        $this->consumer('T::users');
        $this->convert();
        self::assertSame(0, $this->convert()['replacements']);
    }

    public function testCommentsStringsAndSurroundingFormattingArePreserved(): void
    {
        $prefix = '<?php use ' . $this->class . ' as T;' . "\n// T::users\n\$text = 'T::users';\nreturn ";
        $file = $this->write('app/query.php', $prefix . 'T:: /* keep */ users;');
        $this->convert();
        self::assertStringStartsWith($prefix, file_get_contents($file));
        self::assertStringContainsString('/* keep */', file_get_contents($file));
        self::assertSame('users', require $file);
    }

    public function testResolvesGroupedImportsAndFullyQualifiedNames(): void
    {
        $namespace = substr($this->class, 0, -4);
        $file = $this->write('app/query.php', '<?php namespace Consumer; use ' . $namespace . '\\{Tbl as T}; return [T::users, \\' . $this->class . '::users__id];');
        self::assertSame(2, $this->convert()['replacements']);
        self::assertSame(['users', 'id'], require $file);
    }

    public function testUnrelatedTblClassIsUntouched(): void
    {
        $source = '<?php namespace Other; return Tbl::users;';
        $file = $this->write('app/query.php', $source);
        self::assertSame(0, $this->convert()['replacements']);
        self::assertSame($source, file_get_contents($file));
    }

    public function testVendorAndGitDirectoriesAreNotParsed(): void
    {
        $this->consumer('T::users');
        $this->write('app/vendor/broken.php', '<?php broken {');
        $this->write('app/.git/broken.php', '<?php broken {');
        self::assertSame(1, $this->convert()['replacements']);
    }

    public function testLiteralReferencesAreConvertedEvenWhenOthersRemainPending(): void
    {
        $file = $this->consumer('[T::users, T::users__id($alias)]');
        $report = $this->convert();
        self::assertSame(1, $report['replacements']);
        self::assertCount(1, $report['pending']);
        self::assertStringContainsString("['users', T::users__id(\$alias)]", file_get_contents($file));
    }

    public function testParseFailureAbortsBeforeAnyFileIsWritten(): void
    {
        $file = $this->consumer('T::users');
        $original = file_get_contents($file);
        $this->write('app/z.php', '<?php broken {');
        try {
            $this->convert();
            self::fail('Invalid source must abort conversion.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('No files were written', $error->getMessage());
        }
        self::assertSame($original, file_get_contents($file));
    }

    public function testNeitherGeneratedNorConsumerTopLevelCodeIsExecuted(): void
    {
        file_put_contents($this->generated, "\nthrow new \\RuntimeException('generated executed');", FILE_APPEND);
        $this->write('app/query.php', '<?php throw new \\RuntimeException("consumer executed"); return \\' . $this->class . '::users;');
        self::assertSame(1, $this->convert()['replacements']);
    }

    public function testGeneratedFileInsideScanDirectoryIsPreserved(): void
    {
        $original = file_get_contents($this->generated);
        $this->consumer('T::users');
        (new IndependenceCommand())->run($this->directory, $this->generated, false);
        self::assertSame($original, file_get_contents($this->generated));
    }

    public function testUnsupportedHelperIsNotExecuted(): void
    {
        $marker = $this->directory . '/executed.txt';
        $this->generated = $this->write('unsafe.php', '<?php namespace Unsafe; class Tbl { public static function users() { file_put_contents(' . var_export($marker, true) . ', "bad"); return "users"; }}');
        $this->write('app/query.php', '<?php return \\Unsafe\\Tbl::users();');
        $report = $this->convert();
        self::assertCount(1, $report['pending']);
        self::assertSame(0, $report['replacements']);
        self::assertFileDoesNotExist($marker);
    }
}
