<?php

namespace Eril\TblClass;

class GeneratorResult
{
    public function __construct(
        private int $code,
        private string $message = '',
        private array $data = []
    ) {}

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isSuccess(): bool
    {
        return $this->code === 0;
    }

    public function isSchemaChanged(): bool
    {
        return $this->code === 1 && ($this->data['schemaChanged'] ?? false);
    }

    public function isInitialRequired(): bool
    {
        return $this->code === 2;
    }

    public static function success(string $message = '', array $data = []): self
    {
        return new self(0, $message, $data);
    }

    public static function schemaChanged(array $data = []): self
    {
        return new self(1, 'Schema or generation configuration changed', ['schemaChanged' => true] + $data);
    }

    public static function initialRequired(): self
    {
        return new self(2, 'Initial generation required');
    }

    public static function error(string $message): self
    {
        return new self(1, $message);
    }
}
