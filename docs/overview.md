# TBL-CLASS

**TBL-CLASS** é uma ferramenta CLI que gera classes PHP com constantes para seu esquema de banco de dados, fornecendo referências type-safe para tabelas, colunas, foreign keys e valores enum.

## ✨ Características

- **Geração automática** - Converte seu esquema de banco de dados em classes PHP
- **Type-safe** - Constantes PHP para todas as tabelas, colunas e relações
- **Detecção de mudanças** - Sistema de hash para detectar alterações no esquema
- **Multi-banco** - Suporte para MySQL, PostgreSQL e SQLite
- **Abstração simples** - Classes `Tbl`, `TblFk`, `TblEnum` organizadas
- **CLI amigável** - Interface de linha de comando intuitiva

## 📦 Instalação

```bash
composer require eril/tbl-class --dev
```

## 🚀 Uso Rápido

### 1. Gerar configuração inicial
```bash
php vendor/bin/tbl-class
```
Isso cria um arquivo `tblclass.yaml` com configuração padrão.

### 2. Configurar conexão com banco de dados
Edite o arquivo `tblclass.yaml`:

```yaml
database:
  driver: mysql
  host: env(DB_HOST)       # ou 'localhost'
  name: env(DB_NAME)       # nome do banco
  user: env(DB_USER)       # usuário
  password: env(DB_PASS)   # senha
```

### 3. Gerar classes
```bash
php vendor/bin/tbl-class
```

Isso cria `Tbl.php` com três classes:
- `Tbl` - Tabelas e colunas
- `TblFk` - Foreign keys
- `TblEnum` - Valores enum

### 4. Verificar mudanças no esquema
```bash
php vendor/bin/tbl-class --check
```

## 📁 Arquivo Gerado

```php
<?php

// Tbl.php - Exemplo de saída

final class Tbl
{
    /** table: users (alias: u) */
    public const users = 'users';
    /** `users`.`id` */ public const users_id = 'id';
    /** `users`.`name` */ public const users_name = 'name';
    
    // ==================== Table Aliases ====================
    /** alias: `u` */ public const as)users = 'users u';
}

final class TblFk
{
    // ==================== Foreign Keys ====================
    /** user_id: posts → users.id */
    public const posts_users = 'user_id';
}

final class TblEnum
{
    public const users_status_active = 'active';
    public const users_status_pending = 'pending';
    public const users_status_inactive = 'inactive';
}
```

## 🔧 Configuração

### Arquivo `tblclass.yaml`

```yaml
# Inclusão opcional de arquivo PHP (ex: autoloader do framework)
include: null

# Configuração do banco de dados
database:
  # Conexão customizada opcional
  connection: null
  
  driver: mysql            # mysql | pgsql | sqlite
  
  # MySQL/PostgreSQL
  host: env(DB_HOST)       # ou 'localhost'
  port: env(DB_PORT)       # 3306 (mysql) ou 5432 (pgsql)
  name: env(DB_NAME)       # obrigatório
  user: env(DB_USER)       # ou 'root'
  password: env(DB_PASS)   # ou ''
  
  # SQLite apenas
  # path: env(DB_PATH)     # ex: database.sqlite

# Configuração de saída
output:
  # Diretório de saída
  path: "./"
  
  # Namespace PHP (opcional)
  namespace: ""
  
  # Regras de nomenclatura
  naming:
    # Estratégia de nomenclatura:
    # - full  → nomes completos (users, users_id)
    # - short → nomes abreviados (usr, usr_id)
    # - alias → alias de tabela (u, u_id)
    strategy: full
    
    # Configuração de abreviação
    abbreviation:
      max_length: 15        # comprimento máximo dos nomes
      dictionary_lang: en   # en | pt | es | all
      dictionary_path: null # dicionário customizado (opcional)
```

## 💻 Comandos CLI

### Gerar classes
```bash
php vendor/bin/tbl-class
php vendor/bin/tbl-class ./src/Database/  # Diretório customizado
```

### Verificar mudanças
```bash
php vendor/bin/tbl-class --check
```

### Ajuda
```bash
php vendor/bin/tbl-class --help
```

### Versão
```bash
php vendor/bin/tbl-class --version
```


## 🎯 Estratégias de Nomenclatura

### `full` (padrão)
```php
Tbl::users
Tbl::users_id
TblFk::users_posts
TblEnum::users_status_active
```

### `short` (abreviado)
```php
Tbl::usr        // users → usr
Tbl::usr_id     // users_id → usr_id
TblFk::usr_pst  // users_posts → usr_pst
```

### `alias` (alias de tabela)
```php
Tbl::u          // users → u
Tbl::u_id       // users_id → u_id
TblFk::u_p      // users_posts → u_p
```

## 🔍 Sistema de Hash

`tbl-class` usa hashing MD5 para detectar mudanças no esquema:

```php
/**
 * @schema-hash md5:abc123def456...
 * @generated   2022-02-22 10:30:00
 */
```

## 🏗️ Integração com Composer

### Sem namespace:
```json
{
  "autoload": {
    "files": ["Tbl.php"]
  }
}
```

### Com namespace:
```json
{
  "autoload": {
    "psr-4": {
      "App\\Database\\": "./src/Database/"
    }
  }
}
```

Depois execute:
```bash
composer dump-autoload
```

## 📝 Uso no Código

```php
// Tabelas e colunas
$table = Tbl::users;
$column = Tbl::users_id;

// Query type-safe
$query = "SELECT * FROM " . Tbl::users . " WHERE " . Tbl::users_id . " = ?";

// Foreign keys
$fkColumn = TblFk::posts_users; // 'user_id'

// Valores enum
$status = TblEnum::users_status_active;

// Aliases para JOINs
$alias = Tbl::as_users; // 'users u'
```

## 🐛 Solução de Problemas

### "No tables found"
- Verifique se está conectando ao banco correto
- O banco precisa ter tabelas

### "Database connection failed"
- Verifique credenciais no `tblclass.yaml`
- Garanta que o servidor está rodando
- Verifique firewall/rede

### "Schema changed"
- Execute `php vendor/bin/tbl-class` para regenerar

## 📄 Licença

MIT License

## 🤝 Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues e pull requests.


---

<div align="center">

![Star](https://img.shields.io/github/stars/erilshackle/php-tbl-class?style=social) ![Fork](https://img.shields.io/github/forks/erilshackle/php-tbl-class?style=social) ![Watch](https://img.shields.io/github/watchers/erilshackle/php-tbl-class?style=social)

<strong>Tbl:: Type-safe database constants for PHP projects 🚀 </strong>
</div>
