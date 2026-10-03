# Proposal

## Why

O backend não funciona: `server/composer.lock` fixa o `illuminate/database` v7.9.2, que exige PHP ^7.2.5 e **não instala em nenhum PHP moderno** (verificado: `composer install` aborta em PHP 8.3.6 com "lock file does not contain a compatible set of packages"). Além disso, só existem uma tabela (`zf_line`, de um dump de 2020), um arquivo de configuração e nenhum endpoint. Sem API, o front continua uma maquete com dados fixos e o laço central do produto (o cliente entra na fila e o estabelecimento a vê e a avança) não existe. Esta mudança constrói a base e a API para **um estabelecimento fixo**, sem login (fatia A), de forma que as próximas mudanças (página do cliente e dashboard ligado à API) tenham um backend real.

## What Changes

- **Dependências atualizadas e instaláveis:** PHP `^8.3`, `illuminate/database` `^13` (a versão atual exige PHP ^8.3), `guzzlehttp/guzzle` `^8`, `slim/slim` `^4` com `slim/psr7`, e `phpunit/phpunit` `^12` em desenvolvimento. O `composer.lock` é regenerado. **BREAKING:** o lock antigo (Laravel 7, PHP 7) deixa de existir.
- **Estrutura do backend:** front controller em `server/public/index.php`, roteamento e middlewares com o Slim, autoload PSR-4 (`Controllers\`, `Models\` já declarados, mais `Services\` e `Middleware\`).
- **Banco com migrações versionadas** (um runner próprio e pequeno, `bin/migrate`) no lugar do dump Navicat `server/_install/zf_line.sql`, **que é removido**. Duas tabelas: `estabelecimentos` (com uma linha de partida "Veste Bem", slug `veste-bem`) e `entradas_fila`. A tabela `zf_line` é abandonada: o banco que a continha já não existe e as duas linhas eram de exemplo.
- **Quatro chamadas de API** sob `/api/filas/{slug}`:
  - o cliente entra na fila e recebe um código e a posição;
  - o cliente consulta a própria posição pelo código;
  - o dashboard lista a fila com o telefone **mascarado pela API**;
  - o dashboard finaliza o atendimento atual, promovendo o próximo.
- **Chave compartilhada provisória** (`X-API-Key`, vinda de `API_KEY`) nas duas chamadas do dashboard, até existir login. Sem a variável configurada, essas chamadas ficam fechadas.
- **CORS configurável** (`CORS_ORIGIN`), porque o front e a API ficarão em origens diferentes quando houver hospedagem.
- **Testes automatizados** em SQLite (PHPUnit), sem servidor de banco, e um novo job de CI `API (PHP)` no workflow existente.
- **`server/README.md`** com como rodar localmente, migrar e testar, e `.env.example` com as novas variáveis.
- **Fora desta mudança:** página do cliente e QR code, dashboard consumindo a API, login e cadastro reais, multi-loja, notificações (WhatsApp/SMS), hospedagem e deploy.

## Capabilities

### New Capabilities
- `queue-intake`: o cliente entra na fila de um estabelecimento por telefone e consulta a própria posição, sem identificar o número de outras pessoas.
- `queue-management`: o dashboard do estabelecimento lista a fila com telefones mascarados e finaliza o atendimento atual, protegido por uma chave provisória.
- `api-platform`: convenções comuns da API (respostas e erros em JSON) e acesso entre origens configurável, de que as próximas mudanças do front dependem.

### Modified Capabilities

<!-- Nenhuma. `queue-dashboard` descreve a tela, que só passa a consumir esta API na mudança 3. -->

## Impact

- **Código:** `server/` inteiro (`composer.json` e `composer.lock`, `public/`, `app/`, `database/`, `bin/`, `tests/`, `README.md`, `.env.example`); remoção de `server/_install/zf_line.sql`.
- **CI:** novo job `api` em `.github/workflows/ci.yml` (PHP 8.3). O job só vira exigência de merge quando for adicionado às regras de branch protection nas settings do GitHub.
- **Front:** nenhum arquivo muda nesta mudança.
- **Dependências do app React:** nenhuma.
- **Dados pessoais (LGPD):** o telefone passa a ser gravado completo. O banco guarda o número inteiro e a API nunca o devolve inteiro ao dashboard. A **política de retenção do histórico fica como pendência explícita**, não resolvida aqui.
- **Pendências registradas:** hospedagem/deploy, login real (a chave compartilhada é provisória) e retenção de dados.
