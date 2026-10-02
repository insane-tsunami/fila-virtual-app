# Tasks

## 1. Renomear a pasta

- [x] 1.1 Executar `git mv src/Dashbboard src/Dashboard` e ajustar os três imports de `./Dashbboard` em `src/App.js`; verificar com `grep -rn "Dashbboard" src` (sem resultados) e `yarn build` (compila sem erro)

## 2. Estabelecimento e avatar compartilhados

- [ ] 2.1 Criar `src/Dashboard/estabelecimento.js` exportando `nome` ("Veste Bem") e `inicial` ("V"); verificar que o arquivo existe e que `grep -rn "Veste Bem" src` aponta só para ele
- [ ] 2.2 Criar o estilo de avatar da barra lateral em `src/Dashboard/styles.js` (tamanho `theme.spacing(7)`, antes `classes.large`) e usá-lo em `index.js`, `Qrcode.js` e `Perfil.js` junto com o módulo de 2.1; verificar com `grep -rn "classes.large" src` (sem resultados) e abrindo as três páginas, que mostram o mesmo nome, inicial e tamanho de avatar (spec "Identificação do estabelecimento")

## 3. Dashboard da fila

- [ ] 3.1 Reescrever a lógica de `src/Dashboard/index.js`: estado inicial a partir da lista de exemplo sem o campo `status`, `status` derivado do índice (0 atual, 1 próximo, demais aguardando), `setClients((atual) => atual.slice(1))` em "Finalizar Atendimento", remover `useEffect`, estado `update` e a mutação de `data`; verificar que `grep -n "data.shift\|setUpdate" src/Dashboard/index.js` não retorna nada
- [ ] 3.2 Trocar as condicionais por comparações booleanas (`clients.length > 0 &&`, `clients.length === 0 &&`), mantendo a mensagem "Fila vazia" quando só há o cliente atual; verificar no navegador finalizando todos os atendimentos que nenhum `0` aparece na tela
- [ ] 3.3 Adicionar `src/Dashboard/index.test.js` (Jest + Testing Library) cobrindo os cenários do spec `queue-dashboard`: fila com vários clientes, finalizar com clientes aguardando, finalizar o último, ausência de `0` com fila vazia e mensagem "Fila vazia" com só o cliente atual; verificar com `CI=true yarn test --watchAll=false` (todos passam)

## 4. HTML e lint

- [ ] 4.1 Trocar `<html lang="en">` por `<html lang="pt-BR">` em `public/index.html`; verificar com `grep -n "<html" public/index.html`
- [ ] 4.2 Adicionar `"lint": "eslint src --ext .js"` aos scripts do `package.json`, rodar `yarn lint` e corrigir os apontamentos dos arquivos tocados por esta mudança (pasta `src/Dashboard/`, `src/App.js`); verificar que `yarn lint` não aponta erros nesses arquivos e anotar, no resumo do commit, o que restar em `Home`, `Login` e `Register`

## 5. Integração

- [ ] 5.1 Rodar `yarn build`, `yarn lint` e `CI=true yarn test --watchAll=false`, e percorrer manualmente `/dashboard`, `/dashboard/qrcode` e `/dashboard/perfil`; verificar build verde, testes passando e as três páginas renderizando sem erro no console
- [ ] 5.2 Rodar `openspec validate higiene-do-front --strict`; verificar que a validação passa
