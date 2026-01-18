
# Autoload e Integração no Projeto

Esta secção explica **como configurar correctamente o autoload** após a geração das classes/constantes, garantindo que o ficheiro gerado possa ser utilizado em qualquer ponto da aplicação sem `require` manuais.

---

## Conceito Geral

O comando `tbl-class` gera um ficheiro PHP que contém:

- Constantes para:
  - Tabelas
  - Colunas
  - Chaves estrangeiras
  - Enums
- Uma classe principal (`Tbl`)
- **Todas as constantes são lowercase**, por convenção da biblioteca

Dependendo da configuração, o ficheiro pode:

- Estar dentro de um **namespace**
- Ou ser um ficheiro global (sem namespace)

A forma de **autoload varia conforme esta escolha**.

---

## Estratégias de Autoload Suportadas

A biblioteca suporta oficialmente **duas abordagens**:

1. Autoload via **PSR-4** (recomendado)
2. Autoload via **files** (modo simples)

---

## 1. Autoload via PSR-4 (Recomendado)

### Quando usar

Utilize esta abordagem quando:

- Define um namespace no output
- Usa Composer como autoloader principal
- Pretende integração limpa e escalável
- O projecto é médio ou grande

---

### Exemplo de Configuração

No ficheiro de configuração do `tbl-class`:

```yaml
output:
  namespace: App\Database
  path: src/Database
```

Isto irá gerar, por exemplo:

```
src/Database/Tbl.php
```

Com o conteúdo:

```php
namespace App\Database;

final class Tbl
{
    public const users = 'users';
    public const users_id = 'id';
}
```

---

### Configuração do composer.json

Adicione ao `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\Database\\": "src/Database"
    }
  }
}
```

Depois execute:

```bash
composer dump-autoload
```

---

### Utilização no Código

```php
use App\Database\Tbl;

echo Tbl::users;
echo Tbl::users_id;
```

Nenhum `require` manual é necessário.

---

### Vantagens

* Compatível com standards PHP (PSR)
* Melhor organização do código
* Carregamento preguiçoso (lazy loading)
* Ideal para aplicações profissionais

---

## 2. Autoload via Files (Modo Simples)

### Quando usar

Recomendado quando:

* Não pretende usar namespaces
* O projecto é pequeno ou legacy
* Quer uma configuração mínima

---

### Configuração de Output

```yaml
output:
  namespace: null
  path: database
```

Exemplo de ficheiro gerado:

```
database/tbl.php
```

Conteúdo:

```php
final class Tbl
{
    public const users = 'users';
}
```

---

### Configuração do composer.json

```json
{
  "autoload": {
    "files": [
      "database/tbl.php"
    ]
  }
}
```

Depois execute:

```bash
composer dump-autoload
```

---

### Utilização no Código

```php
echo Tbl::users;
```

---

### Nota Importante

* O ficheiro é carregado sempre que o Composer inicializa
* Não recomendado para projectos grandes
* Deve existir apenas **um ficheiro gerado**

---

## Autoload Durante a Execução do CLI

O comando `tbl-class` permite incluir ficheiros adicionais **apenas durante a execução do CLI**.

Exemplo de configuração:

```yaml
autoload:
  include: vendor/autoload.php
```

Durante a execução, o CLI faz:

```php
include_once vendor/autoload.php;
```

Isto é útil quando:

* O projecto já possui classes próprias
* Existem dependências externas
* O schema depende de tipos personalizados

> Este include **não substitui** o autoload final do projecto.

---

## Verificação Automática Pós-Geração

Após gerar o ficheiro, o CLI verifica se a classe `Tbl` está acessível:

* Se não estiver, imprime instruções exactas para configurar o autoload
* Evita erros silenciosos
* Facilita a primeira integração

---

## Boas Práticas

* Preferir sempre **PSR-4**
* Não editar manualmente o ficheiro gerado
* Regenerar após alterações no schema
* Usar `--check` em CI/CD
* Manter o ficheiro fora da pasta `vendor`
