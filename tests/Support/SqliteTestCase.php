<?php

namespace Tests\Support;

use Eril\TblClass\Config;
use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Schema\SqliteSchemaReader;
use PDO;

abstract class SqliteTestCase extends WorkspaceTestCase
{
    protected PDO $pdo;
    protected SqliteSchemaReader $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->schema = new SqliteSchemaReader($this->pdo, 'test');
    }

    protected function createBlog(): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id));');
    }

    protected function generate(?Config $config = null): Config
    {
        $config ??= $this->config();
        $result = (new FileClassGenerator($this->schema, $config))->run();
        self::assertTrue($result->isSuccess(), $result->getMessage());
        return $config;
    }

    protected function loadOutput(?Config $config = null): string
    {
        $config = $this->generate($config);
        require $config->getTblFile();
        return $config->getOutputNamespace() . '\\Tbl';
    }
}
