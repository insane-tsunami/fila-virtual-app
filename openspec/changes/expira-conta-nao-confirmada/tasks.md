# Tasks

## 1. Prazo de confirmação (`confirmar_ate`)

- [x] 1.1 Criar a migração `server/database/migrations/0010_add_confirmar_ate_em_contas.php`: coluna `contas.confirmar_ate` (`dateTime`, nula) e, para as contas com `email_confirmado_em` nulo, `confirmar_ate` = momento da migração + 14 dias; as confirmadas ficam com nulo. Atualizar as listas de migrações em `server/tests/MigrationTest.php` e acrescentar testes da coluna, do prazo das contas antigas não confirmadas e do nulo nas confirmadas; verificar com `vendor/bin/phpunit --filter MigrationTest`.
- [x] 1.2 Em `ContaService`, criar a constante `PRAZO_CONFIRMACAO_DIAS = 14` e gravar `confirmar_ate = criado_em + 14 dias` no cadastro; em `Models\Conta`, declarar a propriedade e devolver `confirmar_ate` em `paraResposta()` (nulo quando confirmado). Ajustar os tipos `@return` que descrevem a conta em `ContaService`, `SessaoService` e `ConfirmacaoEmailService`. Testes em `ContaServiceTest`, `EmailConfirmationApiTest` e `SessionApiTest`: cadastro, login e `GET /api/conta` trazem o prazo; confirmado traz `null`; trocar o e-mail e reenviar não mudam o prazo. Verificar com `composer test` (SQLite).

## 2. Exclusão de conta em cascata

- [x] 2.1 Criar `server/app/services/ExclusaoDeConta.php` com `excluir(int $contaId)`, sem transação própria, apagando na ordem das FKs: entradas da loja, loja, confirmações, redefinições, sessões, contadores `senha_conta` e `confirmacao_conta` da conta, e a conta. Testes em `server/tests/ExclusaoDeContaTest.php`: tudo da conta some; outras contas, lojas, entradas e a loja de demonstração ficam; contadores por e-mail e por IP ficam. Verificar com `vendor/bin/phpunit --filter ExclusaoDeContaTest`.

## 3. Cadastro sobre conta abandonada e corrida

- [x] 3.1 Em `ContaService::cadastrar`, trocar os dois `exists()` pela busca das contas bloqueadoras (`email` ou `cnpj`) com `lockForUpdate()`, aplicar a regra de substituição do design (não confirmada, prazo vencido e sem entradas nos últimos 14 dias), lançar o 409 com a mensagem de hoje (e-mail antes de CNPJ) quando alguma não é substituível e apagar as substituíveis com `ExclusaoDeConta`. Injetar o serviço onde o `ContaService` é montado (`server/app/support/Aplicacao.php` e testes). Testes em `ContaServiceTest` para cada cenário de "Cadastro sobre conta não confirmada abandonada" e "Conta confirmada nunca é substituída", inclusive duas bloqueadoras e a mensagem do 409.
- [x] 3.2 Rodar a transação do cadastro com 3 tentativas; capturar a `QueryException` que escapar dela, reconsultar e-mail e CNPJ e lançar o 409 correspondente, ou relançar se nada existe; em `criarLoja`, engolir só a violação de unicidade (SQLSTATE `23000`). Teste em `ContaServiceTest` que simula a conta criada entre a checagem e o INSERT e espera `409` (não `500`), e outro que garante que um erro de banco sem conflito continua subindo.
- [x] 3.3 Testes de ponta a ponta em `server/tests/AccountApiTest.php`: `409` dentro do prazo e `201` depois dele; o token de sessão antigo recebe `401`; `GET /api/filas/{slug}` antigo responde `404`; o link de confirmação antigo recebe `422` (em `EmailConfirmationApiTest`, se ficar mais natural lá); a substituição também gasta o limite de cadastro por IP (`RateLimitApiTest`). Verificar com `composer test` (SQLite).

## 4. Front: prazo na faixa

- [x] 4.1 Em `src/Dashboard/AvisoEmail.js`, mostrar "Confirme até DD/MM/AAAA" e o aviso de substituição quando `conta.confirmar_ate` existe e é válido; sem ele, a faixa fica como hoje. Acrescentar `confirmar_ate: null` em `src/sessao/SessaoDeTeste.js`. Testes em `src/Dashboard/AvisoEmail.test.js` (com prazo, sem prazo, valor inválido). Verificar com `CI=true yarn test --watchAll=false`, `yarn lint` e `CI=true yarn build`.

## 5. Documentação

- [x] 5.1 Atualizar `server/README.md` (regra da substituição na seção da confirmação de e-mail, o bullet de "Segurança e pendências" sobre o bloqueio por cadastro alheio passa a dizer o que está resolvido e o que continua fora: CNPJ com e-mail próprio confirmado e recuperação de conta não confirmada; tabela de migrações com a `0010`; `confirmar_ate` nas respostas), `README.md` (faixa com o prazo) e a linha da confirmação de e-mail no contexto de `openspec/config.yaml`. Verificar relendo as seções e com `openspec validate --all --strict`.

## 6. Verificação integrada

- [x] 6.1 Rodar a suíte da API em MySQL 8.4 num banco descartável com `test` no nome (`vendor/bin/phpunit` em segundo plano), a suíte do front e `openspec validate --all --strict`; registrar os resultados.
- [x] 6.2 Testar à mão com o servidor local (`php -S 127.0.0.1:8080 -t public`) e `curl`: cadastro sobre uma conta com prazo vencido e sem entradas responde `201`; sobre uma conta confirmada, `409`.

## Workflow follow-up

- Abrir o PR e, depois do merge, arquivar o change com `/opsx:archive`.
- Fechar a issue #32 com a referência ao PR.
