<?php

namespace Tests\Unit;

use Eril\TblClass\Generators\PhpOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpOutputTest extends TestCase
{
    public static function identifiers(): array
    {
        return ['reserved' => ['class', '_class'], 'numeric' => ['123', '_123'],
            'punctuation' => ['a-b', 'a_b'], 'empty' => ['', '_'], 'valid' => ['users__id', 'users__id']];
    }

    #[DataProvider('identifiers')]
    public function testNormalizesPhpIdentifiers(string $input, string $expected): void
    {
        self::assertSame($expected, PhpOutput::identifier($input));
    }

    public function testCommentCannotCloseTheDocblock(): void
    {
        self::assertSame('* / one two ', PhpOutput::comment("*/ one\ntwo\r\0"));
    }

    public function testDuplicateConstantNamesAreRejected(): void
    {
        $output = new PhpOutput();
        $output->constant('users', 'users', 'first');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Constant collision');
        $output->constant('users', 'other', 'second');
    }
}
