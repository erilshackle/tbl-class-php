<?php

namespace Tests\Support;

trait OutputProfiles
{
    /** Expected names are explicit so tests do not reproduce the naming algorithm. */
    public static function outputProfiles(): array
    {
        $overrides = ['authentication' => 'account', 'configuration' => 'setting'];
        $profiles = [
            'full' => ['full', [], 'authentication', 'authentication__id', 'configuration', 'configuration__id', 'fk__configuration__authentication', 'on__configuration__authentication', 'on__authentication__authentication'],
            'FULL' => ['FULL', [], 'AUTHENTICATION', 'AUTHENTICATION__ID', 'CONFIGURATION', 'CONFIGURATION__ID', 'FK__CONFIGURATION__AUTHENTICATION', 'ON__CONFIGURATION__AUTHENTICATION', 'ON__AUTHENTICATION__AUTHENTICATION'],
            'short' => ['short', [], 'authentication', 'auth__id', 'configuration', 'cfg__id', 'fk__cfg__auth', 'on__cfg__auth', 'on__auth__auth'],
            'SHORT' => ['SHORT', [], 'AUTHENTICATION', 'AUTH__ID', 'CONFIGURATION', 'CFG__ID', 'FK__CFG__AUTH', 'ON__CFG__AUTH', 'ON__AUTH__AUTH'],
            'full overrides' => ['full', $overrides, 'authentication', 'account__id', 'configuration', 'setting__id', 'fk__setting__account', 'on__setting__account', 'on__account__account'],
            'FULL overrides' => ['FULL', $overrides, 'AUTHENTICATION', 'ACCOUNT__ID', 'CONFIGURATION', 'SETTING__ID', 'FK__SETTING__ACCOUNT', 'ON__SETTING__ACCOUNT', 'ON__ACCOUNT__ACCOUNT'],
            'short overrides' => ['short', $overrides, 'authentication', 'account__id', 'configuration', 'setting__id', 'fk__setting__account', 'on__setting__account', 'on__account__account'],
            'SHORT overrides' => ['SHORT', $overrides, 'AUTHENTICATION', 'ACCOUNT__ID', 'CONFIGURATION', 'SETTING__ID', 'FK__SETTING__ACCOUNT', 'ON__SETTING__ACCOUNT', 'ON__ACCOUNT__ACCOUNT'],
        ];
        $fields = ['strategy', 'overrides', 'table', 'column', 'otherTable', 'otherColumn', 'foreignKey', 'join', 'selfJoin'];
        return array_map(static fn(array $values): array => [array_combine($fields, $values)], $profiles);
    }
}
