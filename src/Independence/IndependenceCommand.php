<?php

namespace Eril\TblClass\Independence;

use Eril\TblClass\Generators\PhpOutput;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;
use Throwable;

final class IndependenceCommand
{
    /** Plans all files before writing. Each replacement is linted and written atomically. */
    public function run(string $directory, string $generatedFile, bool $dryRun): array
    {
        $root = realpath($directory);
        $generated = realpath($generatedFile);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException("Scan directory does not exist: {$directory}");
        }
        $model = new GeneratedModel($generatedFile);
        $plans = [];
        $report = ['files' => [], 'changes' => [], 'pending' => [], 'replacements' => 0, 'dryRun' => $dryRun];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static fn(\SplFileInfo $entry) => !$entry->isLink()
                && (!$entry->isDir() || !in_array($entry->getFilename(), ['vendor', '.git'], true))
        ));
        $files = [];
        foreach ($iterator as $entry) {
            if ($entry->isFile() && strtolower($entry->getExtension()) === 'php' && $entry->getRealPath() !== $generated) {
                $files[] = $entry->getPathname();
            }
        }
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                throw new RuntimeException("Cannot read: {$file}");
            }
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            try {
                $nodes = $parser->parse($source);
                $nodes = (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($nodes);
            } catch (Throwable $e) {
                throw new RuntimeException("Cannot parse {$file}: {$e->getMessage()}. No files were written.", 0, $e);
            }
            $edits = [];
            $this->walk($nodes, $model, $source, $file, $edits, $report['pending']);
            if (!$edits) {
                continue;
            }
            usort($edits, static fn($a, $b) => $b[0] <=> $a[0]);
            $updated = $source;
            foreach ($edits as [$start, $end, $replacement]) {
                $report['changes'][] = [
                    'file' => $file,
                    'line' => substr_count(substr($source, 0, $start), "\n") + 1,
                    'before' => substr($source, $start, $end - $start + 1),
                    'after' => $replacement,
                ];
                $updated = substr_replace($updated, $replacement, $start, $end - $start + 1);
            }
            $parser->parse($updated);
            $plans[$file] = [$source, $updated];
            $report['files'][$file] = count($edits);
            $report['replacements'] += count($edits);
        }
        if (!$dryRun) {
            foreach ($plans as $file => [$before, $after]) {
                if (file_get_contents($file) !== $before) {
                    throw new RuntimeException("File changed during scan; refusing to overwrite: {$file}");
                }
                PhpOutput::write($file, $after);
            }
        }
        return $report;
    }

    private function walk(mixed $nodes, GeneratedModel $model, string $source, string $file, array &$edits, array &$pending): void
    {
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                $this->walk($node, $model, $source, $file, $edits, $pending);
            }
            return;
        }
        if (!$nodes instanceof Node) {
            return;
        }
        $node = $nodes;
        // Imports are kept: removing unused imports/autoload is a separate, manual cleanup.
        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            return;
        }
        if ($node instanceof Expr\ClassConstFetch || $node instanceof Expr\StaticCall) {
            if (!$node->class instanceof Node\Name) {
                $pending[] = "{$file}:{$node->getStartLine()}: Dynamic class reference; cannot determine whether it uses Tbl.";
            } elseif ($this->isTarget($node->class, $model)) {
                try {
                    if (!$node->name instanceof Node\Identifier) {
                        throw new RuntimeException('Dynamic member name');
                    }
                    if ($node instanceof Expr\ClassConstFetch) {
                        $value = $model->constant($node->name->toString());
                    } else {
                        if ($node->isFirstClassCallable()) {
                            throw new RuntimeException('Callable reference');
                        }
                        $args = [];
                        foreach ($node->getArgs() as $argument) {
                            if ($argument->unpack || $argument->byRef) {
                                throw new RuntimeException('Unpacked or reference argument');
                            }
                            if ($argument->value instanceof Node\Scalar\String_) {
                                $literal = $argument->value?->value;
                            } elseif ($argument->value instanceof Expr\ConstFetch && strtolower($argument->value->name->toString()) === 'null') {
                                $literal = null;
                            } else {
                                throw new RuntimeException('Helper arguments must be literal strings or null');
                            }
                            if ($argument->name === null) {
                                $args[] = $literal;
                            } else {
                                $args[$argument->name->toString()] = $literal;
                            }
                        }
                        $value = $model->call($node->name->toString(), $args);
                    }
                    $start = $node->getStartFilePos();
                    $end = $node->getEndFilePos();
                    $comments = '';
                    foreach (token_get_all('<?php ' . substr($source, $start, $end - $start + 1)) as $token) {
                        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                            $comments .= $token[1] . "\n";
                        }
                    }
                    $replacement = var_export($value, true);
                    $edits[] = [$start, $end, $comments === '' ? $replacement : '(' . $comments . $replacement . ')'];
                } catch (Throwable $e) {
                    $pending[] = "{$file}:{$node->getStartLine()}: {$e->getMessage()}";
                }
                return;
            }
        } elseif ($node instanceof Node\Name && $this->isTarget($node, $model)) {
            $pending[] = "{$file}:{$node->getStartLine()}: Class reference requires manual review.";
            return;
        }
        foreach ($node->getSubNodeNames() as $key) {
            $this->walk($node->$key, $model, $source, $file, $edits, $pending);
        }
    }

    private function isTarget(Node\Name $name, GeneratedModel $model): bool
    {
        $resolved = $name->getAttribute('resolvedName', $name);
        return strcasecmp(ltrim($resolved->toString(), '\\'), $model->className) === 0;
    }
}
