# Tasks

## 1. Node 16 fixado

- [ ] 1.1 Criar `.nvmrc` com `16` e adicionar `"engines": { "node": ">=16 <17" }` ao `package.json`; verificar que, no Node 16, `yarn install --frozen-lockfile` passa e que, no Node 22, `yarn install` recusa com o erro `The engine "node" is incompatible`
- [ ] 1.2 Confirmar no Node 16, sem `NODE_OPTIONS`, `yarn lint`, `CI=true yarn test --watchAll=false` (27 testes) e `CI=true yarn build`; verificar que os três terminam com sucesso e que o build diz `Compiled successfully`
- [ ] 1.3 Documentar no README uma seção sobre a versão do Node (`.nvmrc`, `nvm install 16 && nvm use`, aviso do `engines` e `--ignore-engines`) e os comandos de verificação (`yarn lint`, `yarn test`, `yarn build`, `openspec validate --all --strict`); verificar executando cada comando documentado no Node 16 (o `openspec validate` no Node 22) e vendo que passam como escritos

## 2. Workflow de CI

- [ ] 2.1 Criar `.github/workflows/ci.yml` com os gatilhos `push` e `pull_request` para `master`, `permissions: contents: read`, `concurrency` por ref com `cancel-in-progress` e o job `app` (`actions/checkout@v4`, `actions/setup-node@v4` com `node-version-file: .nvmrc` e `cache: yarn`, `yarn install --frozen-lockfile`, `yarn lint`, `CI=true yarn test --watchAll=false`, `yarn build`); verificar que o YAML é válido (parse com Python `yaml`) e reproduzir localmente cada passo do job no Node 16, todos passando
- [ ] 2.2 Adicionar ao workflow o job `specs` (Node 22 via `actions/setup-node@v4`, `OPENSPEC_TELEMETRY=0`, `npx --yes @fission-ai/openspec@1.14.0 validate --all --strict`); verificar que o YAML segue válido e que o comando passa localmente no Node 22 com as 7 specs

## 3. Integração

- [ ] 3.1 Rodar `openspec validate ci-e-testes-minimos --strict`; verificar que a validação passa
- [ ] 3.2 Publicar a branch e abrir o PR (quando o usuário pedir) e verificar nas check runs do GitHub que os jobs `app` e `specs` terminam em sucesso no primeiro run real; se falharem, corrigir o workflow e repetir até ficarem verdes
