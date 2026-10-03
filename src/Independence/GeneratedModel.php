<?php

namespace Eril\TblClass\Independence;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

final class GeneratedModel
{
    public readonly string $className;
    private LiteralEvaluator $evaluator;
    private array $methods = [];

    public function __construct(string $file)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("Generated class not readable: {$file}");
        }
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse(file_get_contents($file));
        $nodes = (new NodeTraverser(new NameResolver()))->traverse($nodes);
        $classes = (new NodeFinder())->find($nodes, static fn(Node $n) => $n instanceof Node\Stmt\Class_ && $n->name?->toString() === 'Tbl');
        if (count($classes) !== 1) {
            throw new RuntimeException('Expected exactly one generated Tbl class.');
        }
        $class = array_values($classes)[0];
        $this->className = $class->namespacedName->toString();
        $constants = [];
        foreach ($class->getConstants() as $statement) {
            if (!$statement->isPublic()) {
                continue;
            }
            foreach ($statement->consts as $constant) {
                if (!$constant->value instanceof Node\Scalar\String_) {
                    throw new RuntimeException('Generated constants must be literal strings.');
                }
                $constants[$constant->name->toString()] = $constant->value->value;
            }
        }
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic() && $method->isStatic()) {
                $this->methods[strtolower($method->name->toString())] = $method;
            }
        }
        $this->evaluator = new LiteralEvaluator($constants);
    }

    public function constant(string $name): string
    {
        if (strtolower($name) === 'class') {
            throw new RuntimeException('Class identity reference must be reviewed manually');
        }
        return $this->evaluator->constant($name);
    }

    public function call(string $name, array $args): string
    {
        $method = $this->methods[strtolower($name)] ?? null;
        if ($method !== null && strtolower($name) !== '__callstatic') {
            $variables = [];
            $remaining = $args;
            foreach ($method->params as $index => $param) {
                $key = array_key_exists($index, $remaining) ? $index : $param->var->name;
                if (array_key_exists($key, $remaining)) {
                    $variables[$param->var->name] = $remaining[$key];
                    unset($remaining[$key]);
                } elseif ($param->default !== null) {
                    $variables[$param->var->name] = $this->evaluator->expression($param->default, $variables);
                } else {
                    throw new RuntimeException('Missing helper argument');
                }
            }
            if ($remaining) {
                throw new RuntimeException('Unknown helper argument');
            }
        } else {
            $method = $this->methods['__callstatic'] ?? throw new RuntimeException('No generated helper available');
            $variables = ['name' => $name, 'args' => $args];
        }
        [$returned, $value] = $this->evaluator->statements($method->stmts ?? [], $variables);
        if (!$returned || !is_string($value)) {
            throw new RuntimeException('Helper did not resolve to a literal string');
        }
        return $value;
    }
}
