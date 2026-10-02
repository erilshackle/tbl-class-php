# Documentação Completa de Configuração

## Verificação e geração segura

```bash
php vendor/bin/tbl-class generate
php vendor/bin/tbl-class check
php vendor/bin/tbl-class check --diff
```

O `check` compara o estado atual com um snapshot incluído no comentário do PHP
gerado. O snapshot contém nomes de tabelas e colunas, relações, valores de enum
fornecidos pelo leitor do banco, driver, namespace efetivo e opções de nomenclatura.
Não inclui credenciais de ligação. A ordem de leitura de tabelas, colunas e FKs
não causa diferenças; a ordem dos valores de enum é preservada.

Exemplo de saída do `check --diff`:

```text
+ users.phone
- users.username
~ enum users.status: ["active","pending"] -> ["active","pending","blocked"]
~ output.naming.strategy: "full" -> "upper"
```

Ambos os comandos de verificação são apenas de leitura. `--diff` também funciona
com `--check`, mas é rejeitado com `generate`, `init` ou sem comando.

| Código de saída | Significado |
| --- | --- |
| `0` | Schema e configuração de geração atualizados |
| `1` | Diferenças encontradas ou erro operacional; a mensagem distingue os casos |
| `2` | Geração inicial necessária ou argumentos inválidos |

Ficheiros antigos sem snapshot devem ser regenerados uma vez com `generate`.
Até lá, `check` devolve `1` e `check --diff` explica que não há histórico detalhado.
Se o ficheiro não existir, devolve `2`. O comando lê o destino da configuração
atual; ao mudar `output.path`, gera primeiro no novo destino.

A comparação cobre os metadados expostos pelos leitores atuais. Não é uma
comparação completa de DDL: tipos, defaults, índices e nulabilidade não são
verificados. No SQLite, a extração geral de enums a partir de constraints `CHECK`
ainda não é suportada. O `check` não verifica edições manuais no corpo da classe.

Na geração, caracteres inválidos nos identificadores PHP são substituídos por `_`.
Nomes de constantes que começam por números recebem `_` como prefixo e o nome
reservado `class` torna-se `_class`. Os valores conservam os nomes originais do banco.
Constantes anteriormente válidas mantêm a nomenclatura existente.

Os valores são serializados como literais PHP e os comentários são escapados.
Namespaces inválidos e colisões de constantes interrompem a geração com erro,
preservando o ficheiro anterior. Isto inclui duas FKs que produzam o mesmo nome;
a convenção de nomenclatura das relações não foi alterada.

A saída é escrita num ficheiro temporário no mesmo diretório, validada com
`PHP_BINARY -n -l` e só então substitui o destino. É necessário ter `proc_open`
disponível. Esta validação garante sintaxe PHP; identificadores SQL especiais
continuam a exigir quoting adequado ao banco quando usados em consultas.

Para executar os testes locais, usa `composer test` (requer `pdo_sqlite`).

## `tblclass.yaml`

O ficheiro **`tblclass.yaml`** é o **coração do TBL-CLASS**.
É nele que defines:

* Como a ferramenta se liga à base de dados
* Onde escreve os ficheiros gerados
* Como os nomes das constantes são construídos

Sem este ficheiro, o TBL-CLASS **não executa**.

---
## 📌 Criação do ficheiro de configuração

Cria o ficheiro explicitamente com:

```bash
php vendor/bin/tbl-class init
```

O comando cria um template YAML se `tblclass.yaml` ainda não existir. A inicialização não é interativa: edita o ficheiro para configurar a ligação e as opções de saída. Se o ficheiro já existir, `init` não o sobrescreve.

Sem argumentos, `tbl-class` apenas mostra a ajuda. Usa `generate` para gerar a classe e `check` para verificar alterações sem gerar ficheiros.

```bash
php vendor/bin/tbl-class generate
php vendor/bin/tbl-class check
```

---

## Estrutura Geral do Ficheiro

```yaml
include: null

database:
  ...

output:
  ...
```

Cada secção é independente, mas todas são processadas na execução.

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
> Verifica as referências existentes antes de executar `tbl-class generate`.
