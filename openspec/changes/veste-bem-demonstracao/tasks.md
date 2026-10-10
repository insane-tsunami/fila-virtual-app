# Tasks

## 1. Migração que remove a demonstração

- [ ] 1.1 Criar a migração `0009` (apaga a `veste-bem` só com `conta_id` nulo e zero entradas; com entradas preserva e avisa por `error_log`; com dona preserva em silêncio; não toca em outras lojas) e atualizar o `MigrationTest`: a lista de migrações ganha a `0009`, os testes que esperavam a loja após migrar tudo passam a esperar banco novo sem loja, e entram testes da `0009` (vazia sem dona removida; com entradas preservada com o aviso capturado via `ini_set('error_log')`; com dona preservada; outras lojas intactas; um cadastro "Veste Bem" em banco recém-migrado recebe o slug `veste-bem`); verificar com `vendor/bin/phpunit tests/MigrationTest.php` e, no CI, no MySQL

## 2. Comando para semear a demonstração

- [ ] 2.1 Criar `Support\LojaDeDemonstracao` (`semear`: cria `Veste Bem`/`veste-bem` sem dona e sem endereço, idempotente, devolve se criou) e o comando `server/bin/seed-demo` (confere o driver antes de conectar: `mysql` sem `--forcar` recusa com código 1; confere que `estabelecimentos` existe, senão orienta `php bin/migrate`; imprime criada ou já existe) com o script `composer seed-demo`; verificar com testes da classe e do comando (`proc_open` com `DB_DRIVER=sqlite` em arquivo temporário: criar, rodar de novo, banco sem migrar, e `DB_DRIVER=mysql` sem `--forcar` recusa antes de conectar, sem criar nada), e documentar o comando no `server/README.md` (seção Banco e migrações) conferindo que os comandos documentados rodam como escritos

## 3. Testes passam a semear a demonstração

- [ ] 3.1 Acrescentar `semearDemonstracao()` ao `DatabaseTestCase` (chama `LojaDeDemonstracao::semear`), chamá-lo no `setUp` do `ApiTestCase` e nos testes de serviço que dependem do slug (`FilaServiceTest` e os demais encontrados por busca por `veste-bem` em `server/tests`), e ajustar os testes de slug (`ContaServiceTest`, `AccountApiTest`) para esperar `veste-bem` na primeira loja com esse nome, semeando a demonstração de forma explícita onde o teste quer a colisão (`veste-bem-2`, `-3`, `-4`); verificar com a suíte completa da API passando no SQLite e conferir com uma mutação (remover o `semearDemonstracao()` do `ApiTestCase`) que a suíte falha

## 4. Specs, documentação e verificação final

- [ ] 4.1 Atualizar `README.md` e `server/README.md` (a migração não insere mais a `veste-bem`; rodar `php bin/seed-demo` em desenvolvimento antes dos exemplos de `curl`; `--forcar` para MySQL; a seção de segurança deixa de listar a loja sem dono como pendência e passa a descrever a demonstração opcional) e `openspec/config.yaml` se citar a semente; verificar que os comandos documentados rodam como escritos
- [ ] 4.2 Integração: com SQLite, migrar um banco novo e conferir que não há loja; rodar `php bin/seed-demo` duas vezes (cria e depois avisa que já existe); subir a API e conferir `GET /api/filas/veste-bem` (200) e o `403` do dashboard para a loja sem dono; simular um banco antigo (migrar até a `0008` com a loja e migrar de novo) e conferir que a `0009` a remove; conferir que `DB_DRIVER=mysql` sem `--forcar` recusa; rodar `openspec validate veste-bem-demonstracao --strict` e as suítes da API e do front
