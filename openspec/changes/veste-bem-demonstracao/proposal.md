# Proposal

## Why

A loja `veste-bem` vem da migração `0003`, escrita para a primeira versão do projeto ("estabelecimento fixo, sem multi-loja nem login"), e foi decidido que ela é uma **demonstração**. Hoje todo banco novo, inclusive o de produção, nasce com ela: uma fila pública sem dona, que ninguém atende pelo dashboard, onde qualquer cliente pode entrar (e o telefone fica guardado), e que ocupa o slug `veste-bem` de uma loja de verdade. Em desenvolvimento e nos testes ela continua útil.

## What Changes

- Uma migração nova (`0009`) **remove** a `veste-bem` quando ela não tem dona e não tem nenhuma entrada na fila. Com dona, ou com entradas, a loja é preservada (e, com entradas, a migração avisa no log). Bancos novos ficam sem loja nenhuma; bancos já migrados perdem só a demonstração vazia. A `0003` não é alterada (o histórico fica intacto).
- Novo comando `php bin/seed-demo` (e `composer seed-demo`) que cria a loja `Veste Bem` (`veste-bem`, sem dona e sem endereço público) para desenvolvimento. É idempotente: se o slug já existe, não altera nada e avisa. Com `DB_DRIVER=mysql` ele **recusa** rodar, a menos que se passe `--forcar`, para ninguém semear produção por engano.
- Os testes passam a semear a demonstração no `setUp` (com o mesmo código do comando), em vez de dependerem da migração. Os testes que esperavam `veste-bem-2`, `-3` e `-4` passam a esperar `veste-bem` para a primeira loja com esse nome.
- O cenário "Loja sem dono" de `account-session` troca o exemplo "(como a `veste-bem` original)" por "(como a loja de demonstração)".
- Documentação: `README.md` e `server/README.md` deixam de dizer que a migração insere a `veste-bem` e mandam rodar `php bin/seed-demo` em desenvolvimento.

Fora do escopo: dar uma conta dona à demonstração, qualquer outra remoção de dados, o avatar e a LGPD (outras issues).

## Capabilities

### New Capabilities
- `demo-store`: a loja de demonstração `veste-bem` existe só por comando explícito (nunca por migração), com proteção contra produção, e a migração que a remove dos bancos sem uso.

### Modified Capabilities
- `account-session`: o exemplo do cenário "Loja sem dono" deixa de citar a "`veste-bem` original".

## Impact

- `server/`: nova migração `0009`, comando `bin/seed-demo` (e script do Composer), uma classe de semente compartilhada com os testes, `DatabaseTestCase`/`ApiTestCase` e os testes que dependem da loja (`MigrationTest`, `ContaServiceTest`, `AccountApiTest` e os de fila), `server/README.md`.
- Documentação: `README.md` e, se citar a semente, `openspec/config.yaml`.
- Sem mudança de API nem de front (os testes do front usam só mocks). Sem dependências novas.
- Quem já tem um banco de desenvolvimento migrado e usa a `veste-bem` passa a precisar rodar `php bin/seed-demo` depois de migrar (a `0009` apaga a loja se ela não tiver entradas).
