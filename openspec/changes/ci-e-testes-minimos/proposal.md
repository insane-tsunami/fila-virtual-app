# Proposal

## Why

Hoje nada garante que `master` continue saudável: não há CI, e o projeto só compila com o truque `NODE_OPTIONS=--openssl-legacy-provider`, porque o `react-scripts` 3.4.1 (webpack 4) não funciona no Node 17+. Testes, lint e validação das specs já existem e passam, mas só rodam se alguém lembrar. Fixar uma versão de Node que funciona e rodar essas verificações a cada push e PR fecha esse buraco com pouco custo, antes de qualquer funcionalidade nova.

## What Changes

- Fixar o Node 16 no projeto: `.nvmrc` com `16` e `engines.node` em `package.json` (`>=16 <17`). Verificado: no Node 16.20.2, `yarn install --frozen-lockfile`, `yarn lint`, os 27 testes e `yarn build` passam **sem** `--openssl-legacy-provider`.
- Criar o workflow `.github/workflows/ci.yml`, disparado em `push` e `pull_request` para `master`, com dois jobs:
  - `app` (Node 16): `yarn install --frozen-lockfile`, `yarn lint`, `CI=true yarn test --watchAll=false` e `yarn build`.
  - `specs` (Node 22): `openspec validate --all --strict`, com a versão da CLI fixada. Este job precisa de outro Node porque a CLI do OpenSpec exige Node >= 20.19, incompatível com o Node 16 do app.
- Documentar no README a versão do Node, como instalá-la e os comandos de verificação.
- Nenhuma mudança de código-fonte do app, de comportamento ou de specs.

## Capabilities

### New Capabilities

<!-- Nenhuma: é mudança de ferramental e processo, sem comportamento novo do produto. A mudança declara skip_specs. -->

### Modified Capabilities

<!-- Nenhuma. -->

## Impact

- **Arquivos novos:** `.nvmrc`, `.github/workflows/ci.yml`.
- **Arquivos alterados:** `package.json` (campo `engines`) e `README.md`.
- **Efeito para quem desenvolve:** `yarn install` passa a recusar versões de Node fora de 16 (pode ser contornado com `--ignore-engines`). Quem usa outro Node precisa trocar para o 16 (por exemplo, `nvm use`).
- **Dependências do projeto:** nenhuma nova. A CLI do OpenSpec roda só no CI, via `npx` com versão fixada.
- **Fora do escopo:** migrar do Create React App (e com isso sair do Node 16), CI do backend PHP, testes novos de tela, deploy, cobertura de código e branch protection (exigir o CI para mergear é configuração do GitHub, não do repositório).
