# Tbl::class

### Type-safe database schema constants para PHP

[![Latest Version](https://img.shields.io/packagist/v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class) [![PHP Version](https://img.shields.io/packagist/php-v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class) [![License](https://img.shields.io/packagist/l/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class) [![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class) [![Stars](https://img.shields.io/github/stars/erilshackle/php-tbl-class?style=social)](https://github.com/erilshackle/php-tbl-class)

---

## O que é o tbl-class?

**TBL-CLASS** é uma ferramenta **CLI para PHP** que gera **classes com constantes type-safe** directamente a partir do **esquema do seu banco de dados**.

Permite referenciar **tabelas, colunas, foreign keys e valores enum** sem recorrer a strings mágicas, tornando o código:

* mais seguro
* mais legível
* mais fácil de manter
* resistente a alterações de schema

> Ideal para projectos PHP modernos, APIs, frameworks customizados e ambientes CI/CD.

---

## ✨ Funcionalidades Principais

* Geração automática de constantes PHP a partir do schema
* Constantes **type-safe e centralizadas**
* Detecção de alterações no esquema via **hash**
* Compatível com **MySQL, PostgreSQL e SQLite**
* Classes organizadas: `Tbl`, `TblFk`, `TblEnum`
* Interface CLI simples e previsível
* Integração nativa com Composer

---

## 📌 Convenção Importante

> **Todas as constantes geradas são em lowercase**, por definição.

Exemplo:

```php
Tbl::users
Tbl::users_id
TblFk::posts_users
TblEnum::users_status_active
```

Isto garante:

* consistência entre bases de dados
* compatibilidade cross-platform
* previsibilidade em SQL dinâmico

---

## 📦 Instalação

```bash
composer require erilshackle/php-tbl-class --dev
```

Recomendado como dependência de desenvolvimento.

---

## 🚀 Utilização Rápida

### 1. Criar configuração inicial

```bash
php vendor/bin/tbl-class
```

Gera o ficheiro `tblclass.yaml`.

---

### 2. Configurar ligação à base de dados

```yaml
database:
  driver: mysql
  host: env(DB_HOST)
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)
```

---

### 3. Gerar classes PHP

```bash
php vendor/bin/tbl-class
```

É gerado o ficheiro `Tbl.php` contendo:

* `Tbl` → tabelas, colunas e aliases
* `TblFk` → foreign keys
* `TblEnum` → valores enum

---

### 4. Verificar alterações no esquema

```bash
php vendor/bin/tbl-class --check
```

---

## 📁 Exemplo de Código Gerado

```php
<?php

final class Tbl
{
    /** table: users (alias: u) */
    public const users = 'users';

    /** `users`.`id` */
    public const users_id = 'id';

    /** `users`.`email` */
    public const users_email = 'email';

    // ==================== table aliases ====================
    /** alias: `u` */
    public const as_users = 'users u';
}

final class TblFk
{
    /** posts.user_id → users.id */
    public const posts_users = 'user_id';
}

final class TblEnum
{
    public const users_status_active   = 'active';
    public const users_status_pending  = 'pending';
    public const users_status_inactive = 'inactive';
}
```

---

## 🔧 Configuração Completa (`tblclass.yaml`)

```yaml
include: null

database:
  connection: null
  driver: mysql # mysql | pgsql | sqlite

  host: env(DB_HOST)
  port: env(DB_PORT)
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)

  # sqlite
  # path: env(DB_PATH)

output:
  path: "./"
  namespace: ""

  naming:
    strategy: full # full | short | alias

    abbreviation:
      max_length: 15
      dictionary_lang: en # en | pt | es | all
      dictionary_path: null
```

---

## 🧠 Estratégias de Nomenclatura

Naming strategy is global and applied consistently to tables, columns, foreign keys and enums.
Changing the strategy is a breaking change and should be treated as a refactor.

### `full` (default)

```php
Tbl::users
Tbl::users_id
TblFk::users_posts
```

### `short` 

```php
Tbl::usr        // users
Tbl::usr_id     // users_id
TblFk::usr_pst  // users_posts
```
>

### `alias`

```php
Tbl::u          // users
Tbl::u_id       // users_id
TblFk::u_p      // users_posts
```

---

## 🔍 Detecção de Alterações de Schema

Cada geração inclui metadados:

```php
/**
 * @schema-hash md5:abc123...
 * @generated 2026-01-08 18:42:00
 */
```

Se o hash mudar, o schema foi alterado.

---

## 🏗️ Integração com Composer

### Sem namespace

```json
{
  "autoload": {
    "files": ["Tbl.php"]
  }
}
```

### Com namespace

```json
{
  "autoload": {
    "psr-4": {
      "App\\Database\\": "src/Database/"
    }
  }
}
```

```bash
composer dump-autoload
```

---

## 📝 Exemplo de Utilização

```php
$sql = "
    SELECT *
    FROM " . Tbl::users . "
    WHERE " . Tbl::users_id . " = ?
";

$status = TblEnum::users_status_active;
$fk     = TblFk::posts_users;
$alias  = Tbl::as_users;
```

---

## 🐛 Resolução de Problemas

**Nenhuma tabela encontrada**

* Verifique a base de dados configurada
* Confirme que existem tabelas

**Erro de ligação**

* Credenciais incorrectas no `tblclass.yaml`
* Serviço da base de dados inactivo

**Schema alterado**

* Reexecutar `tbl-class`

---

## 📄 Licença

MIT License — Eril TS Carvalho

---

## 🤝 Contribuições

Issues e pull requests são bem-vindos.
Sugestões técnicas são apreciadas.

---

<div align="center">
<strong>tbl::class — constantes type-safe para esquemas de base de dados em PHP.</strong>
</div>
