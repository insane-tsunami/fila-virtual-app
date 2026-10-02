# Tasks

## 1. Landing

- [ ] 1.1 Criar `src/Home/index.test.js` com os cenários de `landing-page` (marca e slogan, texto da plataforma, "Cadastre-se aqui" com `href` `/cadastro`, "Entrar" com `href` `/login`); verificar com `CI=true yarn test --watchAll=false src/Home` (passa)

## 2. Cadastro e login

- [ ] 2.1 Criar `src/Register/index.test.js` com os cenários de `establishment-signup` (quatro campos, senhas mascaradas, botão "Cadastrar", "Faça o Login aqui" com `href` `/login`, e "Cadastrar" sem requisição nem mudança de rota); verificar com `CI=true yarn test --watchAll=false src/Register` (passa)
- [ ] 2.2 Criar `src/Login/index.test.js` com os cenários de `establishment-login` (campos e botão, senha mascarada, "Cadastre-se aqui" com `href` `/cadastro`, "Entrar" sem requisição nem mudança de rota); verificar com `CI=true yarn test --watchAll=false src/Login` (passa)
- [ ] 2.3 Cobrir o cenário "Acessar o dashboard sem login" de `establishment-login` com um teste em `src/App.test.js` que renderiza `App` em `/dashboard` e encontra o painel "Fila"; verificar com `CI=true yarn test --watchAll=false src/App` (passa)

## 3. Páginas do dashboard

- [ ] 3.1 Criar `src/Dashboard/Nav.test.js` com os cenários de `dashboard-navigation` (quatro itens do menu com os destinos esperados e "Sair" levando a `/` sem apagar dados); verificar com `CI=true yarn test --watchAll=false src/Dashboard/Nav` (passa)
- [ ] 3.2 Cobrir em `src/App.test.js` o cenário "Acessar cada rota" (`/dashboard`, `/dashboard/qrcode` e `/dashboard/perfil` renderizam suas páginas); verificar com `CI=true yarn test --watchAll=false src/App` (passa)
- [ ] 3.3 Criar `src/Dashboard/Qrcode.test.js` com os cenários de `qrcode-generation` (título e botão; acionar o botão não exibe imagem nem altera a página); verificar com `CI=true yarn test --watchAll=false src/Dashboard/Qrcode` (passa)
- [ ] 3.4 Criar `src/Dashboard/Perfil.test.js` com os cenários de `establishment-profile` (e-mail e CNPJ somente leitura com as mensagens, campos de nova senha mascarados, seletor que aceita só imagens, "Atualizar" sem requisição); verificar com `CI=true yarn test --watchAll=false src/Dashboard/Perfil` (passa)

## 4. Integração

- [ ] 4.1 Rodar `CI=true yarn test --watchAll=false` e `yarn lint`; verificar que todos os testes passam, incluindo os 5 de `queue-dashboard`, e que o lint não aponta erros nos arquivos novos
- [ ] 4.2 Rodar `openspec validate specs-baseline --strict`; verificar que a validação passa
