# API da fila (server)

Backend PHP do ZeraFilas: a API que o cliente usa para entrar na fila virtual e que o dashboard do estabelecimento usa para ver e avançar a fila. Por enquanto atende **um estabelecimento fixo** (`veste-bem`) e **não tem login**: veja [Segurança e pendências](#segurança-e-pendências).

## Requisitos

- PHP **8.3 ou mais novo**, com as extensões `pdo_sqlite` (testes) e `pdo_mysql` (produção)
- [Composer](https://getcomposer.org/) 2

## Instalação

```bash
cd server
composer install
```

## Configuração

A configuração vem de **variáveis de ambiente** (o PHP não lê o `.env` sozinho: exporte as variáveis no ambiente do servidor ou configure-as no painel da hospedagem). O modelo está em [`.env.example`](.env.example).

| Variável | Para quê | Padrão |
|---|---|---|
| `DB_DRIVER` | `mysql` (produção) ou `sqlite` (testes e desenvolvimento) | `mysql` |
| `DB_HOST`, `DB_USER`, `DB_PASS` | Acesso ao MySQL | `localhost`, vazio, vazio |
| `DB_NAME` | Nome do banco MySQL, ou caminho do arquivo SQLite | `zerafilas` |
| `API_KEY` | Chave **provisória** das rotas do dashboard (cabeçalho `X-API-Key`). Vazia = essas rotas ficam fechadas (401) | vazia |
| `CORS_ORIGIN` | Única origem permitida para CORS, por exemplo `https://zerafilas.exemplo.com`. Vazia = sem cabeçalhos CORS | vazia |

Nunca commite valores reais de banco ou de chave.

## Banco e migrações

```bash
php bin/migrate        # ou: composer migrate
```

Cria as tabelas `estabelecimentos` e `entradas_fila` e insere o estabelecimento de partida (`Veste Bem`, slug `veste-bem`). É idempotente: rodar de novo responde "Nada a migrar". As migrações executadas ficam na tabela `migrations`.

> No MySQL, comandos de criação de tabela não são transacionais. Se uma migração falhar no meio, confira a tabela `migrations` e o que foi criado antes de rodar de novo.

## Rodar localmente

Com SQLite não é preciso instalar um servidor de banco:

```bash
export DB_DRIVER=sqlite DB_NAME=/tmp/zerafilas.sqlite API_KEY=troque-esta-chave
touch "$DB_NAME"
php bin/migrate
php -S localhost:8080 -t public
```

O servidor embutido do PHP serve só para desenvolvimento.

## Testes

```bash
composer test
```

Roda em SQLite em memória, sem servidor de banco. Para rodar também contra MySQL, use um banco **descartável cujo nome contenha `test`** (por exemplo `zerafilas_test`):

```bash
DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_NAME=zerafilas_test DB_USER=... DB_PASS=... composer test
```

Os testes apagam todas as tabelas do banco a cada teste. Por isso eles **recusam** rodar em um MySQL cujo nome não contenha `test`, para nunca apagar um banco real por engano.

## API

Todas as respostas são JSON. Erros têm o formato `{"erro": "mensagem"}`. Exemplos contra `http://localhost:8080`:

**Entrar na fila** (público): `201` com a nova entrada, ou `200` com a entrada existente se o telefone já estiver na fila.

```bash
curl -X POST http://localhost:8080/api/filas/veste-bem/entradas \
  -H 'Content-Type: application/json' \
  -d '{"telefone": "(11) 97177-8203"}'
# {"codigo":"9f3c...","posicao":1,"status":"em_atendimento"}
```

**Consultar a própria posição** (público), com o `codigo` recebido ao entrar. `posicao` é `null` depois de finalizado.

```bash
curl http://localhost:8080/api/filas/veste-bem/entradas/9f3c...
# {"codigo":"9f3c...","posicao":1,"status":"em_atendimento"}
```

**Listar a fila** (dashboard, exige `X-API-Key`). O telefone vem mascarado.

```bash
curl http://localhost:8080/api/filas/veste-bem/entradas -H "X-API-Key: $API_KEY"
# [{"codigo":"9f3c...","posicao":1,"status":"em_atendimento","telefone":"*****8203"}]
```

**Finalizar o atendimento** (dashboard, exige `X-API-Key`). Só finaliza a entrada que está em atendimento (senão `409`) e promove a próxima.

```bash
curl -X POST http://localhost:8080/api/filas/veste-bem/entradas/9f3c.../finalizar \
  -H "X-API-Key: $API_KEY"
# {"finalizada":{"codigo":"9f3c...","posicao":null,"status":"finalizado"},"atual":null}
```

| Status | Quando |
|---|---|
| `400` | Corpo que não é JSON válido |
| `401` | Chave ausente ou errada (ou `API_KEY` não configurada) |
| `404` | Rota, estabelecimento ou entrada inexistente |
| `405` | Método não permitido |
| `409` | Finalizar uma entrada que não está em atendimento |
| `422` | Telefone inválido (de 10 a 13 dígitos; o DDI `55` é acrescentado se faltar) |
| `500` | Erro inesperado (mensagem genérica; os detalhes vão para o `error_log`) |

O comportamento completo está especificado em [`openspec/specs/`](../openspec/specs) (`queue-intake`, `queue-management` e `api-platform`).

## Estrutura

```
server/
  public/            front controller (index.php) e .htaccess para Apache
  api/config.php     lê as variáveis de ambiente e devolve um array
  app/controllers/   ClienteController e DashboardController
  app/services/      FilaService (regras da fila) e exceções de domínio
  app/models/        Estabelecimento e EntradaFila
  app/middleware/    Cors e ChaveDeApi
  app/support/       Aplicacao (rotas e erros), Database, Migrator, Telefone, Json
  database/migrations/
  bin/migrate        runner de migrações
  tests/
```

## Hospedagem

A hospedagem ainda não foi definida. O código não depende de nenhum provedor: serve qualquer hospedagem com PHP 8.3+ e MySQL, apontando a raiz do site para `public/`, definindo as variáveis de ambiente e rodando `php bin/migrate` a cada deploy. Em Apache, o `public/.htaccess` já direciona tudo ao front controller.

## Segurança e pendências

- **A chave `X-API-Key` é provisória e não é autenticação de verdade.** Se ela for embutida no pacote do front, qualquer pessoa a vê nas ferramentas do navegador. Até existir login, o dashboard deve pedir a chave ao dono em tempo de execução, em vez de embuti-la no build.
- **Qualquer pessoa pode entrar na fila com qualquer telefone:** não há limite de requisições nem confirmação de que o número é de quem o digitou. Isso precisa ser resolvido antes de existirem notificações.
- **O telefone é dado pessoal (LGPD).** O banco guarda o número completo e a API nunca o devolve inteiro ao dashboard. A política de retenção do histórico ainda não foi definida.
