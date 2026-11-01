# Tasks

## 1. API

- [x] 1.1 Em `server/app/services/FilaService.php`, criar `sair(string $slug, string $codigo): array`: transação com `lockForUpdate` na loja, encerra a entrada ativa como `cancelado` com `finalizou_em` e `telefone = null`, promove a ativa mais antiga quando ninguém fica em atendimento e devolve o resumo. Entrada já encerrada devolve o resumo sem alterar. Testes em `server/tests/FilaServiceTest.php`: sai da fila aguardando, sai em atendimento e promove a próxima, telefone apagado, código inexistente 404 e segunda chamada sem mudança. Verificar com `vendor/bin/phpunit --filter FilaServiceTest`.
- [x] 1.2 Expor `DELETE /api/filas/{slug}/entradas/{codigo}` em `server/app/support/Aplicacao.php` e `ClienteController::sair`. Testes em `server/tests/QueueIntakeApiTest.php`: `200` com `status` `cancelado` e `posicao` `null`, telefone apagado no banco, mesma chamada de novo `200`, código inexistente `404` e o mesmo telefone entra de novo com `201`. Verificar com `composer test`.

## 2. Front

- [x] 2.1 Em `src/api.js`, criar `sairDaFila(slug, codigo)` (`DELETE`). Em `src/Cliente/index.js`, mostrar "Sair da fila" enquanto a entrada está ativa; ao sair, apagar o código guardado e voltar ao formulário; falha de rede mantém a entrada e mostra a mensagem. Testes em `src/Cliente/index.test.js`. Verificar com `CI=true yarn test --watchAll=false src/Cliente/index.test.js`, `yarn lint` e `CI=true yarn build`.

## 3. Documentação

- [x] 3.1 Documentar o endpoint em `server/README.md` (seção da API) e a saída na página do cliente no `README.md`. Verificar com `openspec validate --all --strict`.
