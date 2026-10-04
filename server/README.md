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
| `TRUSTED_PROXIES` | IPs ou faixas CIDR de proxies confiáveis, separados por vírgula (ex.: `10.0.0.1,172.16.0.0/12`). Só atrás deles a API lê o `X-Forwarded-For` para descobrir o IP do cliente (ver [Limite de tentativas](#limite-de-tentativas)) | vazia |
| `RATE_LIMIT_LOGIN_EMAIL` | Erros de login por e-mail que bloqueiam o e-mail na janela | `5` |
| `RATE_LIMIT_LOGIN_IP` | Erros de login por IP que bloqueiam o IP na janela | `20` |
| `RATE_LIMIT_SENHA_CONTA` | Senhas atuais erradas na troca de senha que bloqueiam a conta na janela | `5` |
| `RATE_LIMIT_JANELA_MIN` | Janela, em minutos, dos três limites acima | `15` |
| `RATE_LIMIT_CADASTRO_IP` | Tentativas de cadastro por IP na janela do cadastro | `5` |
| `RATE_LIMIT_CADASTRO_JANELA_MIN` | Janela, em minutos, do limite de cadastro | `60` |

Os `RATE_LIMIT_*` aceitam só inteiros positivos; qualquer outro valor (vazio, `0`, negativo, texto) é ignorado e vale o padrão.

Nunca commite valores reais de banco. (A antiga variável `API_KEY` não existe mais: o dashboard passa a usar login por conta. Se ela ainda estiver no ambiente, é ignorada.)

## Banco e migrações

```bash
php bin/migrate        # ou: composer migrate
```

Cria as tabelas `estabelecimentos` (com o `endereco_publico` de cada loja, opcional), `entradas_fila`, `contas`, `sessoes` e `limites_tentativas` (contadores do limite de tentativas) e insere o estabelecimento de partida (`Veste Bem`, slug `veste-bem`). É idempotente: rodar de novo responde "Nada a migrar". As migrações executadas ficam na tabela `migrations`.

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

**Cadastrar a conta e a loja** (público): cria a conta, a loja dela (o `slug` sai do nome: `Moda & Cia São João` vira `moda-cia-sao-joao`, com `-2`, `-3`... se já existir) e já abre a sessão. O CNPJ pode ter máscara e ser numérico ou **alfanumérico** (12 posições com letras ou números e 2 dígitos verificadores, formato da Receita desde julho de 2026, como `12.ABC.345/01DE-35`); letras minúsculas viram maiúsculas e o **dígito verificador é conferido** (CNPJ com dígito errado ou com os 14 caracteres iguais é `422`). A senha tem de 8 a 72 caracteres.

```bash
curl -X POST http://localhost:8080/api/contas -H 'Content-Type: application/json' \
  -d '{"email": "contato@modaazul.com", "cnpj": "93.339.970/0001-05", "nome": "Moda Azul", "senha": "senha-segura-1"}'
# {"token":"3f9c...","expira_em":"2026-10-11 12:00:00","conta":{"email":"contato@modaazul.com","cnpj":"93339970000105"},"loja":{"nome":"Moda Azul","slug":"moda-azul","endereco_publico":null}}
```

**Entrar** (público): devolve um `token` novo (cada aparelho tem o seu). O token vale 7 dias, é mostrado só nesta resposta (o servidor guarda apenas o hash) e é enviado nas rotas protegidas no cabeçalho `Authorization: Bearer <token>`. Credencial errada é sempre `401` com a mesma mensagem; depois de muitos erros, `429` (ver [Limite de tentativas](#limite-de-tentativas)).

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

## Limite de tentativas

Três rotas públicas ou de conta têm limite, para dificultar a adivinhação de senha, a criação de contas em massa e a sobrecarga por logins repetidos. Os contadores ficam na tabela `limites_tentativas` (janela fixa; o e-mail e o IP só existem lá como hash) e os números são configuráveis (ver [Configuração](#configuração)):

| Rota | Quem é limitado | Padrão | O que conta |
|---|---|---|---|
| `POST /api/sessoes` (login) | cada e-mail | 5 em 15 min | só os erros (senha errada ou e-mail sem conta) |
| `POST /api/sessoes` (login) | cada IP | 20 em 15 min | só os erros |
| `POST /api/contas` (cadastro) | cada IP | 5 em 60 min | toda chamada, inclusive as recusadas (`409`, `422`) |
| `PUT /api/conta/senha` (trocar a senha) | cada conta | 5 em 15 min | só as senhas atuais erradas |

- Esgotado o limite, a rota responde **`429`** com `{"erro": "Muitas tentativas. Tente de novo em N minutos."}` e o cabeçalho `Retry-After` (segundos), **sem verificar a senha**, mesmo que ela esteja certa. O bloqueio vale até a janela vencer.
- Um login correto zera o contador do e-mail; uma troca de senha bem-sucedida zera o da conta. Dados incompletos (`422`) no login não contam.
- E-mail sem conta conta como erro, então o `429` não revela quais e-mails existem. Em compensação, quem erra 5 vezes o e-mail de outra pessoa o bloqueia por até 15 minutos.
- O **IP** é o da conexão. Só se ele estiver em `TRUSTED_PROXIES` a API usa o `X-Forwarded-For` (o IP mais à direita que não seja de proxy confiável); endereços IPv6 contam pelo bloco /64.
- Se o banco dos contadores falhar, a chamada **segue sem limite** e o erro vai para o log (o limite é uma defesa extra e não pode derrubar o login).

```bash
for i in 1 2 3 4 5 6; do
  curl -s -o /dev/null -w '%{http_code} ' -X POST http://localhost:8080/api/sessoes \
    -H 'Content-Type: application/json' -d '{"email": "contato@modaazul.com", "senha": "errada-errada"}'
done
# 401 401 401 401 401 429
```

## Estrutura

```
server/
  public/            front controller (index.php) e .htaccess para Apache
  api/config.php     lê as variáveis de ambiente e devolve um array
  app/controllers/   ClienteController, DashboardController, LojaController, ContaController e SessaoController
  app/services/      FilaService, LojaService, ContaService (cadastro, troca de senha), SessaoService (login, token), LimiteDeTentativas (contadores do limite) e exceções
  app/models/        Estabelecimento, EntradaFila, Conta e Sessao
  app/middleware/    Cors, Autenticacao (token) e LojaDaConta (posse da loja)
  app/support/       Aplicacao (rotas e erros), Database, Migrator, Telefone, EnderecoPublico, Email, Cnpj, Slug, Senha, IpDoCliente, Json
  database/migrations/
  bin/migrate        runner de migrações
  tests/
```

## Hospedagem

A hospedagem ainda não foi definida. O código não depende de nenhum provedor: serve qualquer hospedagem com PHP 8.3+ e MySQL, apontando a raiz do site para `public/`, definindo as variáveis de ambiente e rodando `php bin/migrate` a cada deploy. Em Apache, o `public/.htaccess` já direciona tudo ao front controller.

**Atrás de proxy ou CDN, configure `TRUSTED_PROXIES`.** Sem isso a API usa o endereço da conexão, que atrás de um proxy é o do próprio proxy: todos os usuários parecem o mesmo IP e o limite de tentativas por IP passa a valer para o site inteiro. Liste só os proxies que você controla: um endereço na lista é confiado para dizer quem é o cliente. Sem proxy, deixe vazia.

## Segurança e pendências

- **O limite de tentativas cobre só login, cadastro e troca de senha** (ver [Limite de tentativas](#limite-de-tentativas)): não há limite de requisições em geral nem nas rotas públicas da fila. Atrás de proxy, configure `TRUSTED_PROXIES`, senão o limite por IP vale para todos juntos.
- **Não há "esqueci a senha"** nem confirmação de e-mail: quem perde a senha perde o acesso, e qualquer pessoa pode cadastrar um e-mail ou CNPJ que não é dela.
- **O token é um segredo:** quem o tiver age como a dona da loja por até 7 dias (ou até sair ou trocar a senha). O front o guarda em `sessionStorage`, legível por qualquer script da página.
- **A loja `veste-bem` original não tem dono:** continua servindo a página pública da fila, mas ninguém a acessa pelo dashboard.
- **O dígito verificador só pega erro de digitação:** não prova que o CNPJ é de quem se cadastra (isso depende de consulta à Receita ou de confirmação de e-mail, que não existem).
- **Qualquer pessoa pode entrar na fila com qualquer telefone:** não há limite de requisições nem confirmação de que o número é de quem o digitou. Isso precisa ser resolvido antes de existirem notificações.
- **O telefone é dado pessoal (LGPD).** O banco guarda o número completo e a API nunca o devolve inteiro ao dashboard. A política de retenção do histórico ainda não foi definida.
