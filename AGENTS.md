# Instruções para agentes — fila-virtual-app

Este arquivo cobre só este repositório. O workspace do host está em `/home/tsunami/AGENTS.md`.

## Visão geral

- Front: Create React App 3.4.1, React 16, Material-UI v4, react-router v5. Rotas em `src/App.js`, HTTP em `src/api.js`, sessão/auth em `src/sessao/`.
- API: PHP 8.3 + Slim 4 em `server/`. As rotas são montadas à mão em `server/app/support/Aplicacao.php` (não há arquivo de rotas); os namespaces PSR-4 `Controllers\`, `Services\`, `Models\`, `Support\` e `Middleware\` mapeiam para `server/app/*`.
- Comportamento: `openspec/specs/` é a fonte de verdade. Mudanças propostas ficam em `openspec/changes/<change>/` (proposal, design, tasks e deltas de spec) e vão para `openspec/changes/archive/` quando concluídas. Contexto em `openspec/config.yaml`; comandos/skills `/opsx:*` em `.claude/`.
- Documentação longa: `README.md` (integração e fluxo OpenSpec) e `server/README.md` (variáveis, endpoints e limites). Prefira esses arquivos a adivinhar.

## Comandos

Node 16 é obrigatório no front (`.nvmrc` + `engines`). No host, o Node padrão é 24 e o yarn só existe no 16; rode `nvm use 16` antes. O OpenSpec exige Node 20.19+, então rode-o em outro shell.

Na raiz do repositório:

- `nvm use 16 && yarn install --frozen-lockfile`
- `HOST=127.0.0.1 yarn start`
- `yarn lint` — ESLint (Airbnb) + Prettier só em `src/`; é a única checagem estática (não há typecheck nem script `format`).
- `CI=true yarn test --watchAll=false` — todos os testes Jest uma vez (sem `CI=true`, `yarn test` fica em watch).
- Um arquivo/pattern de teste: `CI=true yarn test --watchAll=false src/Dashboard/index.test.js`
- `CI=true yarn build` — build de produção; no CI warnings viram erro.
- Fora do Node 16 só se inevitável: `yarn --ignore-engines <cmd>`; Node 17+ também exige `NODE_OPTIONS=--openssl-legacy-provider`.

Em `server/`:

- `composer install && composer migrate && composer test`
- Um teste: `composer test -- --filter SlugTest` (ou `vendor/bin/phpunit tests/SlugTest.php`).
- API local: `export DB_DRIVER=sqlite DB_NAME=/tmp/zerafilas.sqlite`, `touch "$DB_NAME"`, `php bin/migrate`, opcional `php bin/seed-demo`, depois `php -S 127.0.0.1:8080 -t public`. O PHP não lê `.env`; exporte as variáveis (modelo em `server/.env.example`). Para o front em dev, use `CORS_ORIGIN=http://localhost:3000`.
- Specs: `npx --yes @fission-ai/openspec@1.14.1 validate --all --strict` (mesma versão do CI; o host também tem a CLI 1.14.1).

## Testes

- API: o PHPUnit roda o app Slim em processo via `ApiTestCase::chamar()`, sem servidor HTTP. `DatabaseTestCase` apaga todas as tabelas a cada teste e recusa MySQL cujo nome não contenha `test`. O `phpunit.xml` usa SQLite em memória, mas variáveis `DB_*` exportadas têm prioridade.
- Front: testes `*.test.js` ao lado do código (Jest + Testing Library v9); a API é mockada com `jest.mock('../api')`. Testes da fila usam timers falsos e `act`; `SessaoDeTeste` fornece a sessão falsa.
- CI (`.github/workflows/ci.yml`): `yarn lint` → `yarn test` → `yarn build` (Node 16), OpenSpec (Node 22, 1.14.1) e PHPUnit em SQLite + MySQL 8.4. Rode o que for afetado antes de encerrar.

## Configuração

- `REACT_APP_API_URL` é gravada no build; vazio significa mesma origem. Rebuild após mudar. Nunca coloque segredos em `REACT_APP_*`.
- A API só lê variáveis de ambiente. Sem `APP_URL` e `MAIL_DRIVER=smtp` (ou `log` em dev), nenhum e-mail sai; `MAIL_DRIVER=log` grava os links de redefinição/confirmação no log.
- O token de sessão fica em `sessionStorage` e vai no header `Authorization: Bearer`, não em cookie.
- Migrações são idempotentes e ficam registradas na tabela `migrations`; DDL no MySQL não é transacional — confira as tabelas após uma falha no meio.
- `veste-bem` é loja de demonstração: `php bin/seed-demo` (em MySQL exige `--forcar`).

## Estilo e idioma

Textos de UI, docs, specs e commits em pt-BR; o OpenSpec mantém títulos estruturais e SHALL/MUST em inglês (`openspec/config.yaml`). Preserve nomes de domínio em português (`estabelecimento`, `fila`, `entrada`). JavaScript: 2 espaços, aspas simples, vírgula final ES5. PHP: 4 espaços, `declare(strict_types=1)`, PSR-4. UTF-8, LF, newline final. Servidores locais em loopback.

## Git

- O alvo é `master`; trabalhe em branch e faça PR (o histórico segue isso).
- Prefixos usados: `feat(front):`, `feat(api):`, `feat(server):`, `fix(front):`, `chore(openspec):`, `chore(ci):`, além de `Propõe o change ...` / `Arquiva o change ...`.
- Mantenha `openspec/specs/` em sincronia com o comportamento e valide antes de terminar.
- Nunca commite `.env`, `server/.env`, `node_modules`, `server/vendor`, `build` ou caches. `.claude/settings.local.json` é excluído do Git e pode conter um token real do GitHub: não imprima nem commite.
