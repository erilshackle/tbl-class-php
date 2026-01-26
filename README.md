# Tbl::class

### Type-safe database schema constants para PHP

[![Latest Version](https://img.shields.io/packagist/v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![License](https://img.shields.io/packagist/l/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class)](https://packagist.org/packages/eril/tbl-class)
[![Stars](https://img.shields.io/github/stars/erilshackle/tbl-class-php?style=social)](https://github.com/erilshackle/tbl-class-php)

---

## O que é o tbl-class?

**TBL-CLASS** é uma ferramenta **CLI para PHP** que gera **classes com constantes type-safe** diretamente a partir do **esquema do seu banco de dados**.

Permite referenciar **tabelas, colunas, foreign keys, joins e valores enum** sem recorrer a strings mágicas, tornando o código:

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
* Helpers de **JOIN baseados em foreign keys**
* Interface CLI simples e previsível
* Integração nativa com Composer

---

## 📌 Convenção Importante

> **Todas as constantes geradas são em lowercase**, por definição.

Exemplo:

```php
Tbl::users
Tbl::users__id
Tbl::on__posts__users
```

Isto garante:

* consistência entre bases de dados
* compatibilidade cross-platform
* previsibilidade em SQL dinâmico

---

## 📦 Instalação

```bash
composer require erilshackle/tbl-class-php --dev
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
ou usando callback
```yaml
database:
  connection: "class::method"
```

---

### 3. Gerar classes PHP

```bash
php vendor/bin/tbl-class
```

É gerado o ficheiro `Tbl.php` contendo:

* `Tbl` → tabelas, colunas e JOIN helpers
* enums e metadata de schema

---

### 4. Verificar alterações no esquema

```bash
php vendor/bin/tbl-class --check
```

---

## 🔗 JOIN Helpers (v1.1.0)

A partir da **v1.1.0**, o `tbl-class` gera automaticamente **helpers de JOIN** com base nas **foreign keys reais do schema**.

### Constantes geradas

Para uma foreign key:

```
posts.user_id → users.id
```

é gerada a constante:

```php
public const on__posts__users = 'posts.user_id = users.id';
```

---

### Uso direto

```php
Tbl::on__posts__users();
// posts.user_id = users.id
```

---

### Uso com aliases (via __callStatic)

```php
Tbl::on__posts__users('p', 'u');
// p.user_id = u.id
```

---

### Helper de alias de tabela

```php
Tbl::users('u');
// users AS u
```

---

### Exemplo completo de SQL

```php
$sql = "
    SELECT *
    FROM " . Tbl::users('u') . "
    JOIN " . Tbl::posts('p') . "
      ON " . Tbl::on__posts__users('p', 'u') . "
    WHERE u.status = ?
";
```

---

## 📁 Exemplo de Código Gerado

```php
final class Tbl
{
    /** TABLE: users */
    public const users = 'users';

    /** COLUMN: users.id */
    public const users__id = 'id';

    /** JOIN: posts.user_id = users.id */
    public const on__posts__users = 'posts.user_id = users.id';

}
```

---

## 🧠 Estratégias de Nomenclatura

Naming strategy é global e aplicada consistentemente a tabelas, colunas, joins e enums.
Alterar a estratégia é um **breaking change** e deve ser tratado como refactor.

### `full` (default)

```php
Tbl::users
Tbl::users__id
Tbl::on__posts__users
```

### `short`

```php
Tbl::usr
Tbl::usr__id
Tbl::on__pst__usr
```

### `abbr`

```php
Tbl::u
Tbl::u__id
Tbl::on__p__u
```

---

## 🔍 Detecção de Alterações de Schema

Cada geração inclui metadados:

```php
/**
 * @schema-hash md5:abc123...
 * @generated 2026-01-25 21:10:00
 */
```

Se o hash mudar, o schema foi alterado.

---

## 🏗️ Integração com Composer

### Sem namespace

```json
{
  "autoload": {
    "files": ["path/to/Tbl.php"]
  }
}
```

### Com namespace

```json
{
  "autoload": {
    "psr-4": {
      "Tbl\\": "path/to/Tbl/"
    }
  }
}
```

```bash
composer dump-autoload
```

---

## 🐛 Resolução de Problemas

**JOIN não gerado**

* Verifique se existe foreign key real no schema
* Reexecute o gerador após alterações

**Nenhuma tabela encontrada**

* Confirme a base de dados configurada
* Verifique permissões

---

## 📄 Licença

MIT License — Eril TS Carvalho

---

## 🤝 Contribuições

Issues e pull requests são bem-vindos.
Discussões técnicas são incentivadas.

---

<div align="center">
<strong>tbl::class — constantes type-safe para esquemas de base de dados em PHP.</strong>
</div>
