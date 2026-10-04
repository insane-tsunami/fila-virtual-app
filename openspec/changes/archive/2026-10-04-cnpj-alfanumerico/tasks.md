# Tasks

## 1. Validador do CNPJ (backend)

- [x] 1.1 Reescrever `server/app/support/Cnpj.php` (aceita `[0-9A-Z]{12}` + 2 dígitos, com ou sem máscara e em qualquer caixa; guarda em maiúsculas; confere o DV por módulo 11 com `ord - 48`; recusa os 14 caracteres iguais; método público `digitosVerificadores`) e conferir a regra no documento oficial da Receita; verificar em `tests/CnpjTest.php` com os vetores de `design.md` (numéricos e alfanuméricos válidos, o exemplo oficial `12.ABC.345/01DE-35`, caixa baixa, DV errado, 14 iguais, letra no DV, 13 e 15 caracteres, valor que não é texto) e com mutações capturadas (trocar `ord - 48`, um peso, o `r < 2` e remover a regra dos iguais fazem testes falharem)
- [x] 1.2 Trocar a mensagem do `422` em `ContaService` (continua começando com "CNPJ"), atualizar o `server/README.md` (a regra do CNPJ nos exemplos e a pendência "o CNPJ não é validado"), e acrescentar em `ContaServiceTest` e `AccountApiTest` os cenários de `establishment-account` (alfanumérico aceito e guardado em maiúsculas, caixa baixa, DV errado, 14 iguais, letra no DV, unicidade com outra caixa e com e sem máscara); verificar com `composer test` verde (SQLite; o MySQL roda no CI) e rodando os comandos de cadastro do README contra um SQLite novo

## 2. Front

- [x] 2.1 Fazer o campo "CNPJ" de `src/Register/index.js` converter para maiúsculas ao digitar e ao colar; verificar em `src/Register/index.test.js` com os cenários de "CNPJ em maiúsculas ao digitar" (digitar, colar, e o `422` de DV errado mostrando a mensagem e mantendo o CNPJ) e com a suíte inteira verde
- [x] 2.2 Fazer `mascararCnpj` em `src/Dashboard/Perfil.js` reconhecer letras (`XX.XXX.XXX/XXXX-DD`, e mostrar o valor como está se não casar); verificar em `src/Dashboard/Perfil.test.js` com o cenário "CNPJ alfanumérico" e o numérico; e atualizar o `README.md` e o contexto de `openspec/config.yaml` (CNPJ alfanumérico aceito e com DV conferido; sai a pendência) validando com `openspec validate --all --strict`

## 3. Integração

- [x] 3.1 Rodar `composer test`, `yarn lint`, `yarn test` e `yarn build` no Node 16 e `openspec validate cnpj-alfanumerico --strict`; verificar que tudo passa, que `yarn install --frozen-lockfile` continua válido, e que nenhuma migração nova foi criada (`git diff --stat origin/master -- server/database`)
- [x] 3.2 Verificar de ponta a ponta no Chromium (API em SQLite, front construído com `REACT_APP_API_URL`, servido com fallback de página única): cadastrar pela interface com o CNPJ oficial digitado em minúsculas e ver o campo em maiúsculas; ver o Perfil com `12.ABC.345/01DE-35`; tentar um CNPJ de DV errado e ver a mensagem na tela sem criar conta; tentar o mesmo CNPJ em outra caixa e ver o `409`; `00000000000000` recusado; e um CNPJ numérico válido continuando a funcionar
- [x] 3.3 Publicar a branch e abrir o PR (quando o usuário pedir) e verificar nas check runs que `App (Node 16)`, `Specs (OpenSpec)`, `API (PHP 8.3, SQLite)` e `API (PHP 8.3, MySQL)` terminam em sucesso no primeiro run real
