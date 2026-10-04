# API da fila (server)

Backend PHP do ZeraFilas: a API que o cliente usa para entrar na fila virtual e que o dashboard do estabelecimento usa para ver e avançar a fila. Cada estabelecimento tem a sua **conta** (e-mail e senha) e só enxerga e mexe na própria loja; o cliente da fila não precisa de conta. Veja [Segurança e pendências](#segurança-e-pendências).

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
| `CORS_ORIGIN` | Única origem permitida para CORS, por exemplo `https://zerafilas.exemplo.com`. Vazia = sem cabeçalhos CORS | vazia |

Nunca commite valores reais de banco. (A antiga variável `API_KEY` não existe mais: o dashboard passa a usar login por conta. Se ela ainda estiver no ambiente, é ignorada.)

## Banco e migrações

```bash
php bin/migrate        # ou: composer migrate
```

Cria as tabelas `estabelecimentos` (com o `endereco_publico` de cada loja, opcional) e `entradas_fila` e insere o estabelecimento de partida (`Veste Bem`, slug `veste-bem`). É idempotente: rodar de novo responde "Nada a migrar". As migrações executadas ficam na tabela `migrations`.

> No MySQL, comandos de criação de tabela não são transacionais. Se uma migração falhar no meio, confira a tabela `migrations` e o que foi criado antes de rodar de novo.

## Rodar localmente

Com SQLite não é preciso instalar um servidor de banco:

```bash
export DB_DRIVER=sqlite DB_NAME=/tmp/zerafilas.sqlite
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

**Cadastrar a conta e a loja** (público): cria a conta, a loja dela (o `slug` sai do nome: `Moda & Cia São João` vira `moda-cia-sao-joao`, com `-2`, `-3`... se já existir) e já abre a sessão. O CNPJ pode ter máscara; o dígito verificador não é conferido. A senha tem de 8 a 72 caracteres.

```bash
curl -X POST http://localhost:8080/api/contas -H 'Content-Type: application/json' \
  -d '{"email": "contato@modaazul.com", "cnpj": "93.339.970/0001-05", "nome": "Moda Azul", "senha": "senha-segura-1"}'
# {"token":"3f9c...","expira_em":"2026-10-11 12:00:00","conta":{"email":"contato@modaazul.com","cnpj":"93339970000105"},"loja":{"nome":"Moda Azul","slug":"moda-azul","endereco_publico":null}}
```

**Entrar** (público): devolve um `token` novo (cada aparelho tem o seu). O token vale 7 dias, é mostrado só nesta resposta (o servidor guarda apenas o hash) e é enviado nas rotas protegidas no cabeçalho `Authorization: Bearer <token>`. Credencial errada é sempre `401` com a mesma mensagem.

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/sessoes -H 'Content-Type: application/json' \
  -d '{"email": "contato@modaazul.com", "senha": "senha-segura-1"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
```

**Dados da conta, trocar a senha e sair** (exigem `Authorization`). Trocar a senha encerra as outras sessões da conta; senha atual errada é `422` (e não `401`).

```bash
curl http://localhost:8080/api/conta -H "Authorization: Bearer $TOKEN"
curl -X PUT http://localhost:8080/api/conta/senha -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"senha_atual": "senha-segura-1", "nova_senha": "outra-senha-22"}'
curl -X DELETE http://localhost:8080/api/sessao -H "Authorization: Bearer $TOKEN"   # 204
```

**Dados da loja** (público): nome, slug e o endereço público configurado (`null` se ainda não houver). O front usa isso para montar o link do QRCode.

```bash
curl http://localhost:8080/api/filas/veste-bem
# {"nome":"Veste Bem","slug":"veste-bem","endereco_publico":null}
```

**Definir o endereço público da loja** (dashboard, exige `Authorization` e que a loja seja da sua conta). Aceita só a **origem** do site (`http`/`https`, host, porta opcional; sem usuário, caminho, query ou fragmento). É normalizado para minúsculas e sem barra final. `null` ou `""` apaga o endereço; o campo ausente é `422`.

```bash
curl -X PUT http://localhost:8080/api/filas/moda-azul/endereco \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"endereco_publico": "https://Loja.Exemplo.com/"}'
# {"nome":"Moda Azul","slug":"moda-azul","endereco_publico":"https://loja.exemplo.com"}
```

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

**Listar a fila** (dashboard, exige `Authorization` e a loja da sua conta). O telefone vem mascarado.

```bash
curl http://localhost:8080/api/filas/moda-azul/entradas -H "Authorization: Bearer $TOKEN"
# [{"codigo":"9f3c...","posicao":1,"status":"em_atendimento","telefone":"*****8203"}]
```

**Finalizar o atendimento** (dashboard, exige `Authorization` e a loja da sua conta). Só finaliza a entrada que está em atendimento (senão `409`) e promove a próxima.

```bash
curl -X POST http://localhost:8080/api/filas/moda-azul/entradas/9f3c.../finalizar \
  -H "Authorization: Bearer $TOKEN"
# {"finalizada":{"codigo":"9f3c...","posicao":null,"status":"finalizado"},"atual":null}
```

| Status | Quando |
|---|---|
| `400` | Corpo que não é JSON válido |
| `401` | Sem `Authorization` válido (ausente, desconhecido, vencido ou encerrado) ou e-mail/senha incorretos no login |
| `403` | Loja de outra conta, ou loja sem dono |
| `404` | Rota, estabelecimento ou entrada inexistente |
| `405` | Método não permitido |
| `409` | Finalizar uma entrada que não está em atendimento, ou e-mail/CNPJ já cadastrado |
| `422` | Telefone inválido (de 10 a 13 dígitos; o DDI `55` é acrescentado se faltar) endereço público inválido/ausente, dado inválido no cadastro ou senha atual incorreta |
| `500` | Erro inesperado (mensagem genérica; os detalhes vão para o `error_log`) |

O comportamento completo está especificado em [`openspec/specs/`](../openspec/specs) (`queue-intake`, `queue-management`, `store-address`, `establishment-account`, `account-session` e `api-platform`).

## Estrutura

```
server/
  public/            front controller (index.php) e .htaccess para Apache
  api/config.php     lê as variáveis de ambiente e devolve um array
  app/controllers/   ClienteController, DashboardController, LojaController, ContaController e SessaoController
  app/services/      FilaService, LojaService, ContaService (cadastro, troca de senha), SessaoService (login, token) e exceções
  app/models/        Estabelecimento, EntradaFila, Conta e Sessao
  app/middleware/    Cors, Autenticacao (token) e LojaDaConta (posse da loja)
  app/support/       Aplicacao (rotas e erros), Database, Migrator, Telefone, EnderecoPublico, Email, Cnpj, Slug, Senha, Json
  database/migrations/
  bin/migrate        runner de migrações
  tests/
```

## Hospedagem

A hospedagem ainda não foi definida. O código não depende de nenhum provedor: serve qualquer hospedagem com PHP 8.3+ e MySQL, apontando a raiz do site para `public/`, definindo as variáveis de ambiente e rodando `php bin/migrate` a cada deploy. Em Apache, o `public/.htaccess` já direciona tudo ao front controller.

## Segurança e pendências

- **Não há limite de tentativas** de login nem de cadastro (nem limite de requisições em geral): a senha pode sofrer força bruta, e o cadastro revela se um e-mail ou CNPJ já existe (`409`). Resolver antes de abrir ao público.
- **Não há "esqueci a senha"** nem confirmação de e-mail: quem perde a senha perde o acesso, e qualquer pessoa pode cadastrar um e-mail ou CNPJ que não é dela.
- **O token é um segredo:** quem o tiver age como a dona da loja por até 7 dias (ou até sair ou trocar a senha). O front o guarda em `sessionStorage`, legível por qualquer script da página.
- **A loja `veste-bem` original não tem dono:** continua servindo a página pública da fila, mas ninguém a acessa pelo dashboard.
- **O CNPJ não é validado** além de ter 14 dígitos (sem dígito verificador, e ainda sem o formato alfanumérico).
- **Qualquer pessoa pode entrar na fila com qualquer telefone:** não há limite de requisições nem confirmação de que o número é de quem o digitou. Isso precisa ser resolvido antes de existirem notificações.
- **O telefone é dado pessoal (LGPD).** O banco guarda o número completo e a API nunca o devolve inteiro ao dashboard. A política de retenção do histórico ainda não foi definida.
