# Documentação Completa de Configuração

## `tblclass.yaml`

O ficheiro **`tblclass.yaml`** é o **coração do TBL-CLASS**.
É nele que defines:

* Como a ferramenta se liga à base de dados
* Onde escreve os ficheiros gerados
* Como os nomes das constantes são construídos
* Se a geração de constantes está **ativa ou desativada**

Sem este ficheiro, o TBL-CLASS **não executa**.

---

## 📌 Criação do ficheiro de configuração

Na **primeira execução** do comando:

```bash
php vendor/bin/tbl-class
```

se o ficheiro `tblclass.yaml` **não existir**, o TBL-CLASS irá:

1. Criar automaticamente um template limpo
2. Informar que a configuração foi criada
3. **Interromper a execução**

Isto é intencional, para garantir que **o utilizador configura conscientemente o projeto** antes de gerar código.

> Se apagares o ficheiro, ele será recriado do zero na próxima execução.

---

## Estrutura Geral do Ficheiro

```yaml
enabled: true

include: null

database:
  ...

output:
  ...
```

Cada secção é independente, mas todas são processadas na execução.
O novo parâmetro **`enabled`** define se a geração de constantes está ativa ou desativada.

---

# 🔹 Propriedade `enabled`

```yaml
enabled: false
```

### O que faz?

* Controla se a geração de constantes deve ser executada
* Valor padrão: `true` (ativado) - futuramente será `false`, de modo ao desenvolvedor explicitamente configurar permitir a geração, e principalmente a configuração do naming antes do uso, 

### Comportamento:

* Se `enabled: false`, o comando **não gera `Tbl.php`**
* Se `enabled: true`, a geração funciona normalmente
* Pode ser **forçado** usando a flag `--generate`

### Flag `--generate`

```bash
php vendor/bin/tbl-class --generate
```

* Ignora `enabled: false` e força a geração de constantes
* Útil para ambientes CI/CD ou testes temporários

> Aviso: alterar `enabled` ou usar `--generate` pode sobrescrever arquivos existentes.

---

# 🔹 Secção `include`

```yaml
include: null
```

### O que faz?

Permite **incluir manualmente um ficheiro PHP** antes de qualquer outra operação do TBL-CLASS.

Este ficheiro é incluído com `include_once`.

### Quando usar?

* Projeto usa um **framework**
* Precisas carregar um **autoload personalizado**
* A ligação à base de dados depende de:

  * containers
  * variáveis definidas em runtime
  * helpers globais

### Exemplo

```yaml
include: bootstrap/app.php
```

```php
// bootstrap/app.php
require __DIR__ . '/../vendor/autoload.php';
Dotenv::load(...);
```

> O ficheiro só é incluído se **existir fisicamente**.

---

# 🔹 Secção `database`

Define **como o TBL-CLASS acede à base de dados**.

```yaml
database:
  driver: mysql
  connection: null
  host: localhost
  port: 3306
  name: my_database
  user: root
  password: ""
```

### `database.driver`

Determina o motor de base de dados e o tipo de `SchemaReader`.

| Valor  | Motor           |
| ------ | --------------- |
| mysql  | MySQL / MariaDB |
| pgsql  | PostgreSQL      |
| sqlite | SQLite          |

---

### `database.connection` (avançado)

```yaml
connection: Classe::metodo
```

* Permite definir **resolver totalmente personalizado**
* Deve retornar **uma instância de PDO**
* Ignora as demais opções (`host`, `port`, etc.)

---

# 🔹 Secção `output`

Define **como e onde o código PHP será gerado**.

```yaml
output:
  path: "./"
  namespace: ""
  naming: full
```

### `output.path`

Directório onde o ficheiro `Tbl.php` será escrito.

### `output.namespace`

Namespace PHP das classes geradas.

Exemplo sem namespace:

```php
final class Tbl {}
```

Exemplo com namespace:

```yaml
namespace: App\Database
```

```php
namespace App\Database;
final class Tbl {}
```

---


## 🔹 Secção `naming`

Define **como TODOS os nomes de constantes são gerados**
(tabelas, colunas, foreign keys e enums).

### Estratégias disponíveis (`naming.strategy`)

| Strategy | Descrição                                                 | Exemplos gerados                                |
| -------- | --------------------------------------------------------- | ----------------------------------------------- |
| **`full`**   | Usa **nomes completos** de tabelas e colunas              | `users`<br>`users__email`<br>`fk__posts__users` <br> `enum__users__active` |
| `short`  |Tabelas, fk e enums completas + **tabela nas colunas abreviadas via dicionário** | `users`<br>`usr__email`<br>`fk__posts__users`<br> `enum__users__inactive`  |
| `abbr`   | Tabelas completas + **colunas abreviadas via dicionário** | `users`<br>`usr__email`<br>`fk__pst__usr` <br> `enum__usr__active`   |
| `alias`  | **Alias curtos** para tabelas e colunas                   | `users`<br>`u__email`<br>`fk__p__u`<br>`enum__u__active`      |
| `upper`  | Igual a `full`, porém **em uppercase**                    | `USERS`<br>`USERS__EMAIL`<br>`FK__POSTS__USERS`<br>`ENUM__USERS__ADMIN` |


> ⚠ **Aviso crítico**
> Alterar `naming.strategy` **renomeia todas as constantes geradas** e **pode quebrar código existente**.
> Defina a estratégia no início do projeto e evite mudá-la depois.

---

# ⚠ Recomendações sobre `enabled`

* `enabled: false` para desativar geração temporária
* `enabled: true` para habilitar geração automática
* `--generate` para ignorar `enabled` e gerar manualmente
* Sempre verificar antes de rodar em produção
