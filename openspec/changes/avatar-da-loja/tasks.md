# Tasks

## 1. Banco e formato da loja

- [x] 1.1 Criar a migração `server/database/migrations/0011_add_avatar_to_estabelecimentos.php` (`avatar_cor` e `avatar_icone`, `string(20)`, nulas). Atualizar as listas de migrações em `server/tests/MigrationTest.php` e testar que as colunas existem e que as lojas que já existiam ficam com as duas nulas. Verificar com `vendor/bin/phpunit --filter MigrationTest`.
- [x] 1.2 Criar `Support\Avatar` com `CORES`, `ICONES`, a mensagem fixa e a validação do corpo (as duas strings das listas, ou as duas `null`; comparação exata), com testes em `server/tests/AvatarTest.php`. Em `Models\Estabelecimento`, declarar as propriedades e criar `paraResposta()` (`avatar` `null` quando falta uma das colunas). Passar `LojaService::resumo`, `ContaService::dados` e `SessaoService::abrir` a usá-lo e ajustar os tipos `@return`. Atualizar os testes que comparam a loja inteira (`StoreAddressApiTest`, `AccountApiTest`, `ContaServiceTest`, `SessionApiTest`) e cobrir `avatar: null` no cadastro, no login, em `GET /api/conta` e em `GET /api/filas/{slug}`. Verificar com `composer test` (SQLite).

## 2. Rota do avatar

- [x] 2.1 Criar `LojaService::definirAvatar`, `LojaController::definirAvatar` e a rota `PUT /api/filas/{slug}/avatar` em `Aplicacao.php` com `LojaDaConta` e `Autenticacao`. Testes em `server/tests/StoreAvatarApiTest.php`: escolher (`200` e o avatar no login, em `GET /api/conta` e em `GET /api/filas/{slug}`), voltar à inicial com as duas `null`, `401` sem sessão, `403` na loja de outra conta, `404` com slug inexistente, `422` com a mensagem fixa para cada corpo recusado da spec (fora da lista, maiúsculas, só um campo, um `null` e o outro não, não-texto, corpo sem campos), sem mudar o avatar anterior; e o `PUT .../endereco` devolvendo o avatar. Verificar com `composer test`.
- [x] 2.2 Documentar em `server/README.md`: a rota com exemplo de `curl`, as listas, o `422`, o campo `avatar` nas respostas da loja e a migração `0011`. Verificar relendo a seção da API.

## 3. Front

- [x] 3.1 Criar `src/Dashboard/avatar.js` (nomes, rótulos, cores e ícones do design) e o componente `AvatarDaLoja` (ícone sobre a cor, ou a inicial com avatar `null` ou desconhecido), com testes que fixam os 6+6 nomes e cobrem avatar definido, `null` e desconhecido. Usar `AvatarDaLoja` em `BarraLateral.js` e acrescentar casos em `BarraLateral.test.js` (com avatar, sem avatar). Acrescentar `avatar: null` em `LOJA_DE_TESTE` (`src/sessao/SessaoDeTeste.js`). Verificar com `CI=true yarn test --watchAll=false`.
- [x] 3.2 Acrescentar `definirAvatar` em `src/api.js` e a seção "Avatar da loja" em `Perfil.js` (pré-visualização, cores, ícones, "Salvar avatar", "Usar a inicial", mensagens). Inverter o teste de `Perfil.test.js` que confere a falta do seletor (continua sem campo de arquivo) e cobrir os cenários de `establishment-profile`: sem avatar, escolha atual marcada, salvar (envio, mensagem e a barra atualizada), escolha incompleta, voltar à inicial, `422`, `401` e falha de rede. Verificar com `CI=true yarn test --watchAll=false`, `yarn lint` e `CI=true yarn build`.
- [x] 3.3 Atualizar `README.md` (avatar no Perfil e na barra lateral) e o contexto de `openspec/config.yaml` (tirar "avatar" das pendências e citar o avatar da loja e a rota). Verificar com `openspec validate --all --strict`.

## 4. Verificação integrada

- [x] 4.1 Rodar a suíte da API em MySQL 8.4 num banco descartável com `test` no nome, a suíte do front e `openspec validate --all --strict`; registrar os resultados.
- [x] 4.2 Testar à mão com a API local (`php -S 127.0.0.1:8080 -t public`) e `curl`: definir o avatar (`200`), conferir no `GET /api/conta`, enviar um valor fora da lista (`422`) e voltar à inicial (`200` com `avatar` `null`).

## Workflow follow-up

- Abrir o PR (com captura de ecrã do Perfil e da barra lateral) e, depois do merge, arquivar o change com `/opsx:archive`.
- Fechar a issue #24 com a referência ao PR.
