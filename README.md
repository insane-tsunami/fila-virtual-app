## MegaHack 2.0

Este projeto foi desenvolvido durante o MegaHack 2.0

Vejá uma prévia aqui: https://zerafilas.now.sh/
Dashboard: https://zerafilas.now.sh/dashboard


## Ambiente de desenvolvimento

O front usa Create React App 3.4.1 (webpack 4), que **só funciona no Node 16**. A versão está fixada no `.nvmrc` e no campo `engines` do `package.json`; em outras versões o `yarn install` recusa com `The engine "node" is incompatible`.

```bash
nvm install 16 && nvm use   # lê o .nvmrc
yarn install --frozen-lockfile
```

Se você realmente precisa usar outro Node, o `engines` também bloqueia `yarn lint`, `yarn test` e `yarn build`; contorne com `yarn --ignore-engines <comando>`. No Node 17+ o build ainda falha com `ERR_OSSL_EVP_UNSUPPORTED`, e aí é preciso `NODE_OPTIONS=--openssl-legacy-provider yarn --ignore-engines build`. O caminho suportado continua sendo o Node 16.

### Falar com a API

O front chama a API do `server/` (página pública do cliente em `/fila/<slug>` e geração do QRCode). O endereço vem de `REACT_APP_API_URL`, **gravada no pacote na hora do build** (mudou o valor, rode `yarn build` de novo). O modelo está em [`.env.example`](.env.example).

- **Desenvolvimento local:** copie `.env.example` para `.env.local`, rode a API na porta 8080 com `CORS_ORIGIN=http://localhost:3000` (veja o [`server/README.md`](server/README.md)) e `yarn start`.
- **Mesma origem:** deixe `REACT_APP_API_URL` vazia se um proxy encaminha `/api` para o PHP; assim não há CORS.
- **Produção:** use HTTPS na API; um site em HTTPS não consegue chamar uma API em HTTP.
- **Rotas do front:** a hospedagem precisa devolver o `index.html` para qualquer caminho (por exemplo `/fila/veste-bem`), senão o link do QRCode dá 404 ao abrir direto.
- **Nunca** coloque a `X-API-Key` em variável `REACT_APP_*`: ela iria para o navegador de todos.

### Verificações

O CI (`.github/workflows/ci.yml`) roda estes comandos a cada push e pull request para `master`:

| Comando | Node | O que verifica |
|---|---|---|
| `yarn lint` | 16 | ESLint (airbnb + Prettier) em `src/` |
| `CI=true yarn test --watchAll=false` | 16 | Testes das telas (Jest + Testing Library) |
| `yarn build` | 16 | Build de produção (no CI, warnings viram erro) |
| `openspec validate --all --strict` | 20.19+ | Specs do OpenSpec |
| `composer test` (em `server/`) | PHP 8.3 | Testes da API (PHPUnit), em SQLite e em MySQL |

O OpenSpec exige Node 20.19 ou mais novo, por isso roda separado do app. Para rodá-lo localmente, use outro terminal com Node 20.19+ e a CLI instalada (veja a seção abaixo).

## Desenvolvimento com OpenSpec

O projeto usa o [OpenSpec](https://github.com/Fission-AI/OpenSpec) para desenvolvimento orientado a especificações (spec-driven). As specs e propostas ficam em `openspec/`, e as skills e comandos do Claude Code já estão commitados em `.claude/`.

### Instalação da CLI (opcional)

As skills e os comandos `/opsx:*` funcionam sem a CLI. Para rodar `openspec list`, `openspec validate` etc. na sua máquina:

```bash
npm install -g @fission-ai/openspec
```

### Como usar no Claude Code

| Comando | O que faz |
|---|---|
| `/opsx:propose "ideia"` | Cria uma proposta de mudança (proposta, design, tarefas e specs) |
| `/opsx:explore` | Explora e discute ideias antes de propor |
| `/opsx:apply` | Implementa as tarefas de uma mudança |
| `/opsx:update` | Atualiza os artefatos de uma mudança existente |
| `/opsx:sync` | Sincroniza as specs da mudança com `openspec/specs/` |
| `/opsx:archive` | Arquiva a mudança concluída |

O contexto do projeto (stack, rotas, regras) está em `openspec/config.yaml`. Se os comandos não aparecerem, reinicie a sessão do Claude Code.

### Configuração do servidor

O backend (PHP 8.3 ou mais novo) está em `server/`. Como instalar, configurar, migrar o banco, rodar e testar está no [`server/README.md`](server/README.md).

A configuração vem de variáveis de ambiente (`DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `API_KEY` e `CORS_ORIGIN`); use `server/.env.example` como modelo e nunca commite segredos.
