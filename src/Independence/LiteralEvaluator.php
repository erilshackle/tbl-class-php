<?php

namespace Eril\TblClass\Independence;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use RuntimeException;

/** Interprets only the pure operations used by generated helpers. Never runs PHP source. */
final class LiteralEvaluator
{
    public function __construct(private array $constants = []) {}

    public function expression(Node $node, array &$variables): mixed
    {
        if ($node instanceof Scalar\String_ || $node instanceof Scalar\Int_ || $node instanceof Scalar\Float_) {
            return $node->value;
        }
        if ($node instanceof Scalar\InterpolatedString) {
            $value = '';
            foreach ($node->parts as $part) {
                $value .= $part instanceof Node\InterpolatedStringPart ? $part->value : $this->expression($part, $variables);
            }
            return $value;
        }
        if ($node instanceof Expr\ConstFetch) {
            return match (strtolower($node->name->toString())) {
                'null' => null, 'true' => true, 'false' => false,
                default => throw new RuntimeException('Non-literal constant'),
            };
        }
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            if (!array_key_exists($node->name, $variables)) {
                throw new RuntimeException('Dynamic variable: $' . $node->name);
            }
            return $variables[$node->name];
        }
        if ($node instanceof Expr\Array_) {
            $value = [];
            foreach ($node->items as $item) {
                if ($item === null || $item->unpack || $item->byRef) {
                    throw new RuntimeException('Unsupported array item');
                }
                $itemValue = $this->expression($item->value, $variables);
                if ($item->key === null) {
                    $value[] = $itemValue;
                } else {
                    $value[$this->expression($item->key, $variables)] = $itemValue;
                }
            }
            return $value;
        }
        if ($node instanceof Expr\ArrayDimFetch && $node->dim !== null) {
            return $this->expression($node->var, $variables)[$this->expression($node->dim, $variables)] ?? null;
        }
        if ($node instanceof Expr\Assign) {
            $value = $this->expression($node->expr, $variables);
            $this->assign($node->var, $value, $variables);
            return $value;
        }
        if ($node instanceof Expr\BooleanNot) {
            return !$this->expression($node->expr, $variables);
        }
        if ($node instanceof Expr\Isset_) {
            foreach ($node->vars as $var) {
                if ($this->expression($var, $variables) === null) {
                    return false;
                }
            }
            return true;
        }
        if ($node instanceof Expr\BinaryOp) {
            $left = $this->expression($node->left, $variables);
            if ($node instanceof Expr\BinaryOp\Coalesce) {
                return $left ?? $this->expression($node->right, $variables);
            }
            if ($node instanceof Expr\BinaryOp\BooleanAnd) {
                return $left && $this->expression($node->right, $variables);
            }
            if ($node instanceof Expr\BinaryOp\BooleanOr) {
                return $left || $this->expression($node->right, $variables);
            }
            $right = $this->expression($node->right, $variables);
            return match (true) {
                $node instanceof Expr\BinaryOp\Concat => $left . $right,
                $node instanceof Expr\BinaryOp\Plus => $left + $right,
                $node instanceof Expr\BinaryOp\Identical => $left === $right,
                $node instanceof Expr\BinaryOp\NotIdentical => $left !== $right,
                default => throw new RuntimeException('Unsupported operator'),
            };
        }
        if ($node instanceof Expr\Ternary) {
            $condition = $this->expression($node->cond, $variables);
            return $condition ? ($node->if ? $this->expression($node->if, $variables) : $condition)
                : $this->expression($node->else, $variables);
        }
        if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Node\Name
            && strtolower($node->class->toString()) === 'self' && $node->name instanceof Node\Identifier) {
            return $this->constant($node->name->toString());
        }
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $args = [];
            foreach ($node->getArgs() as $arg) {
                if ($arg->unpack || $arg->name !== null) {
                    throw new RuntimeException('Unsupported helper argument');
                }
                $args[] = $this->expression($arg->value, $variables);
            }
            // No arbitrary function invocation, callbacks, autoload or eval.
            return match (strtolower($node->name->toString())) {
                'str_starts_with' => str_starts_with(...$args),
                'strtolower' => strtolower(...$args),
                'strtoupper' => strtoupper(...$args),
                'trim' => trim(...$args),
                'explode' => explode(...$args),
                'is_string' => is_string(...$args),
                'preg_replace' => preg_replace(...$args),
                'ctype_digit' => ctype_digit(...$args),
                'defined' => str_starts_with($args[0], 'self::') && array_key_exists(substr($args[0], 6), $this->constants),
                'constant' => str_starts_with($args[0], 'self::') ? $this->constant(substr($args[0], 6)) : throw new RuntimeException('External constant'),
                'array_map' => count($args) === 2 && $args[0] === 'trim' ? array_map('trim', $args[1]) : throw new RuntimeException('Unsupported callback'),
                default => throw new RuntimeException('Unsupported helper operation'),
            };
        }
        throw new RuntimeException('Expression cannot be resolved statically');
    }

    public function constant(string $name): mixed
    {
        if (!array_key_exists($name, $this->constants)) {
            throw new RuntimeException('Unknown constant: ' . $name);
        }
        return $this->constants[$name];
    }

    private function assign(Node $target, mixed $value, array &$variables): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $variables[$target->name] = $value;
        } elseif ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            foreach ($target->items as $key => $item) {
                if ($item) {
                    $index = $item->key ? $this->expression($item->key, $variables) : $key;
                    $this->assign($item->value, $value[$index] ?? null, $variables);
                }
            }
        } else {
            throw new RuntimeException('Unsupported assignment');
        }
    }

    /** @return array{bool, mixed} Whether a return was reached, and its value. */
    public function statements(array $statements, array &$variables): array
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\Return_) {
                return [true, $statement->expr ? $this->expression($statement->expr, $variables) : null];
            }
            if ($statement instanceof Stmt\Static_) {
                foreach ($statement->vars as $var) {
                    $this->assign($var->var, $this->expression($var->default, $variables), $variables);
                }
            } elseif ($statement instanceof Stmt\Expression) {
                $this->expression($statement->expr, $variables);
            } elseif ($statement instanceof Stmt\If_) {
                $branch = $statement->else?->stmts ?? [];
                foreach (array_merge([$statement], $statement->elseifs) as $condition) {
                    if ($this->expression($condition->cond, $variables)) {
                        $branch = $condition->stmts;
                        break;
                    }
                }
                $result = $this->statements($branch, $variables);
                if ($result[0]) {
                    return $result;
                }
            } elseif (!$statement instanceof Stmt\Nop) {
                throw new RuntimeException('Unsupported helper statement');
            }
        }
        return [false, null];
    }
}
