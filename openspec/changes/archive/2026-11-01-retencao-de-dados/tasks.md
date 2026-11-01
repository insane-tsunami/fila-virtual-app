# Tasks

## 1. Banco e configuração

- [x] 1.1 Criar a migração `server/database/migrations/0012_entradas_fila_telefone_anulavel.php` (`telefone` `->nullable()->change()` e índice em `finalizou_em`). Atualizar as listas de `server/tests/MigrationTest.php` e testar que a coluna aceita `null`, que o índice existe e que as entradas que já existiam mantêm o telefone. Em `Models\EntradaFila`, declarar `string|null $telefone` e tirar o "reservado" de `CANCELADO`. Verificar com `vendor/bin/phpunit --filter MigrationTest`.
- [x] 1.2 Acrescentar `retencao` (`RETENCAO_ENTRADA_ATIVA_HORAS` 24, `RETENCAO_TELEFONE_DIAS` 30) em `server/api/config.php` com o `$inteiro` e em `server/.env.example`, e testar os padrões e os valores inválidos em `server/tests/ConfigTest.php`. Criar `Support\Migrator::pendentes()` (só lê, não cria a tabela `migrations`), com testes: banco vazio, tudo aplicado, uma pendente. Verificar com `composer test`.

## 2. Serviço de retenção

- [x] 2.1 Criar `Services\RetencaoDeDados` com o encerramento das entradas esquecidas (loja a loja, em transação com `lockForUpdate` e repetição em deadlock, prazo por `COALESCE(iniciou_em, entrou_em)`, `cancelado` com `finalizou_em`, promoção da ativa mais antiga quando ninguém fica em atendimento). Testes em `server/tests/RetencaoDeDadosTest.php`: loja fechada sem finalizar (as duas canceladas e fila vazia), promove a próxima, dentro do prazo não muda, em atendimento há pouco depois de esperar muito não é encerrada, outras lojas não mudam, prazo configurável. Verificar com `composer test`.
- [x] 2.2 Acrescentar ao serviço o apagamento do telefone (`finalizado` e `cancelado` encerradas antes do corte; ativas nunca) e a remoção de sessões, tokens de redefinição e de confirmação e contadores vencidos. Testes: 31 e 29 dias, prazo de 7 dias, código, status e datas mantidos, ativas intactas, sessão válida continua a dar acesso, token de confirmação válido continua a funcionar, conta não confirmada vencida continua no banco, contagens devolvidas e segunda execução com tudo zero. Verificar com `composer test`.
- [x] 2.3 Cobrir na API, em `server/tests/` (testes de fila que já existem), que a consulta de uma entrada `cancelado` devolve `posicao` `null` e não traz telefone, que um telefone com entrada cancelada entra de novo com `201`, que a consulta de uma entrada com telefone apagado continua a responder e que o dashboard não lista canceladas. Verificar com `composer test`.

## 3. Comando

- [x] 3.1 Criar `server/bin/limpar` (lê a config, confere `Migrator::pendentes()`, roda o serviço, imprime as contagens, `exit(1)` com mensagem em STDERR em erro) e o script `limpar` em `composer.json`. Testes por processo, como em `SeedDemoCommandTest`: banco vazio (erro, pede `php bin/migrate`, não cria tabelas), migrações aplicadas sem dados (tudo zero), com dados vencidos (contagens) e segunda execução (zero). Verificar com `composer test`.

## 4. Front

- [x] 4.1 Em `src/Cliente/index.js`, tratar `cancelado` como encerrada: título "Entrada encerrada", linha "Sua entrada foi encerrada sem atendimento.", sem consultas e com "Entrar na fila de novo". Testes em `src/Cliente/index.test.js`: a consulta passa a `cancelado` (mensagem, para de consultar, entrar de novo apaga o código) e reabrir com o código de uma cancelada. Verificar com `CI=true yarn test --watchAll=false`, `yarn lint` e `CI=true yarn build`.

## 5. Documentação

- [x] 5.1 Atualizar `server/README.md`:
  - as variáveis `RETENCAO_*`;
  - a `0012` em "Banco e migrações";
  - uma seção sobre o comando `php bin/limpar`;
  - o cron de hora em hora em "Hospedagem";
  - `bin/limpar`, `RetencaoDeDados` e `Migrator` em "Estrutura";
  - a pendência da LGPD, que passa a dizer os prazos e o que depende da hospedagem (cron, backups e logs).

  Atualizar também `README.md` (retenção) e o contexto de `openspec/config.yaml` (comando, prazos e status `cancelado`). Verificar com `openspec validate --all --strict`.

## 6. Verificação integrada

- [x] 6.1 Rodar a suíte da API em MySQL 8.4 num banco descartável com `test` no nome (confere o `change()` da `0012` no MySQL), a suíte do front e `openspec validate --all --strict`; registrar os resultados.
- [x] 6.2 Testar à mão com SQLite local: `php bin/limpar` antes do `migrate` (erro), `php bin/migrate`, inserir entradas antigas e uma sessão vencida, rodar `php bin/limpar` duas vezes (contagens e depois zero) e consultar a entrada cancelada pela API local com `curl` (`status` `cancelado`, `posicao` `null`).

## Workflow follow-up

- Abrir o PR com "Refs #26" (a issue continua aberta: faltam sair da fila, o aviso de privacidade e a exclusão da conta) e, depois do merge, arquivar o change com `/opsx:archive`.
