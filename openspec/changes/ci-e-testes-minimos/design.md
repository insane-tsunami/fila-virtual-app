# Design

## Context

Não existe `.github/` nem `.nvmrc`; `package.json` não tem `engines`; o README não cita versão de Node. O `react-scripts` 3.4.1 usa webpack 4, que quebra no Node 17+ por causa do OpenSSL 3 (`ERR_OSSL_EVP_UNSUPPORTED`). Foi verificado em um Node 16.20.2 limpo: instalação com lockfile congelado, `yarn lint`, 8 suítes e 27 testes e `yarn build` passam, sem nenhuma flag. A CLI do OpenSpec, por outro lado, declara `engines.node >= 20.19.0`, então **não roda no Node 16**. Ver proposal.md - Why.

## Goals / Non-Goals

**Goals:**
- Uma única versão de Node documentada e imposta para o app.
- CI que reprova PR com lint, teste, build ou spec quebrados.
- CI reproduzível: mesmas versões de Node e da CLI do OpenSpec sempre.

**Non-Goals:**
- Sair do Node 16 (exigiria migrar o CRA para outro bundler; é mudança própria).
- Proteger a branch ou exigir o status do CI para o merge.
- CI do backend PHP.

## Decisions

1. **Dois jobs com Node diferentes**, em vez de um só. O app roda no Node 16 e a CLI do OpenSpec no Node 22. Rodar tudo em um job obrigaria a trocar de Node no meio dele; separados, os dois executam em paralelo e a falha de um aponta claramente a causa. *Alternativa:* rodar o OpenSpec dentro do job do app com um segundo `setup-node`; descartada por misturar as duas coisas e deixar o diagnóstico menos claro.

2. **CLI do OpenSpec via `npx --yes @fission-ai/openspec@1.14.0`, com a versão fixada**, sem adicioná-la às dependências do projeto. Instalar a CLI no `package.json` quebraria o Node 16 (por causa do `engines` dela) e a versão fixada evita que uma atualização da ferramenta reprove o CI sem mudança nenhuma no repositório. Atualizar a versão vira um commit explícito. Define-se `OPENSPEC_TELEMETRY=0` para não enviar telemetria do CI.

3. **`.nvmrc` com `16` e `engines.node` em `>=16 <17`.** O `.nvmrc` serve ao `nvm use` e ao `actions/setup-node` (`node-version-file: .nvmrc`), que mantém uma única fonte da verdade para o job `app`. O `engines` faz o `yarn install` recusar versões incompatíveis em vez de falhar mais tarde com um erro de OpenSSL difícil de entender. Contorno consciente: `yarn --ignore-engines <comando>`; verificado que o `engines` bloqueia também `yarn lint`, `yarn test` e `yarn build`, não só o `install`, e que no Node 22 o build ainda exige `NODE_OPTIONS=--openssl-legacy-provider`.

4. **Cache de dependências** com `actions/setup-node` (`cache: yarn`), que usa o `yarn.lock`. Reduz o tempo do job sem lógica própria.

5. **Gatilhos `push` e `pull_request` só para `master`.** Evita rodar duas vezes em branches de trabalho com PR aberto, e cobre também o commit de merge na `master`. Adiciona-se `concurrency` por ref, com `cancel-in-progress` só em `pull_request`, para cancelar execuções obsoletas do mesmo PR sem nunca cancelar a execução de um push na `master`.

6. **Permissões mínimas** (`permissions: contents: read`) no workflow, já que nenhum job escreve no repositório.

7. **Versões das actions** fixadas em tags maiores (`actions/checkout@v4`, `actions/setup-node@v4`). O `setup-node@v4` roda em Node 20 no runner, mas instala o Node 16 pedido para os passos do projeto.

8. **`CI: 'true'` explícito no job `app`.** O GitHub Actions já define `CI=true`, mas declará-lo deixa explícito que o CRA trata warnings do build como erros e que o `yarn test` não entra em modo interativo. Já se verificou que o build atual compila sem warnings.

## Risks / Trade-offs

- [Node 16 está fora de suporte (EOL desde setembro de 2023)] → Aceito como dívida consciente: o CRA 3.4.1 não suporta Node mais novo sem flag. A saída definitiva é migrar do CRA, registrada como fora de escopo. O runner do GitHub ainda baixa o Node 16 via `setup-node`.
- [`engines` estrito bloqueia quem usa outro Node] → Mensagem de erro clara do yarn, README com a instrução (`nvm use`) e a opção `--ignore-engines`.
- [A versão fixada da CLI do OpenSpec envelhece] → Atualização manual e consciente; se a validação passar a falhar após atualizar, é uma mudança visível no PR.
- [O workflow só pode ser comprovado de verdade no GitHub] → Localmente se reproduz cada comando no Node 16 e no Node 22; o primeiro run real acontece no PR da própria mudança, que deve ser verificado antes do merge.
- [Sem branch protection o CI não bloqueia merge] → Fora de escopo; deve ser configurado nas settings do GitHub por quem administra o repositório.

## Migration Plan

Sem deploy. Quem desenvolve troca para o Node 16 (`nvm install 16 && nvm use`) e roda `yarn install` de novo. Rollback: reverter o commit; o projeto volta a compilar só com `--openssl-legacy-provider`.
