# Design

## Context

A `0003` insere `Veste Bem` (`veste-bem`) com `conta_id` nulo e é a única fonte da loja. Os testes de API dependem dela por três caminhos: `ApiTestCase` usa `SLUG = 'veste-bem'` e `tokenDaDona()` liga uma conta à loja semeada; vários testes usam o slug sem criá-lo; e os testes de slug (`ContaServiceTest`, `AccountApiTest`) esperam `veste-bem-2`, `-3` e `-4` porque a semente ocupa o primeiro. O `MigrationTest` checa a loja após migrar e usa subconjuntos de migrações para simular bancos antigos. O migrador (`Support\Migrator`) roda por ordem de nome o que não consta em `migrations`, sem `down` e sem canal de saída (só o `bin/migrate` imprime a lista). Não há ambiente "produção" no código: o que distingue produção é o driver (`mysql`) e a configuração por variáveis. Motivação e escopo: ver proposal.md.

## Goals / Non-Goals

**Goals:**
- Banco novo sem a demonstração; bancos já migrados perdem só a demonstração vazia e sem dona.
- A demonstração continua fácil de criar em desenvolvimento e nos testes, com o mesmo código.
- Nunca semear produção por engano.

**Non-Goals:**
- Conta dona para a demonstração; remover ou alterar qualquer outra loja; `down` de migrações.
- Detectar "produção" por variável de ambiente nova (`APP_ENV`): a proteção usa o driver.

## Decisions

**Migração `0009` em vez de reescrever a `0003`.** A `0003` fica como está (histórico intacto, o `MigrationTest` das migrações parciais continua válido). A `0009` apaga a loja `veste-bem` quando `conta_id` é nulo **e** não há linha em `entradas_fila` para ela; caso contrário não faz nada. Banco novo: a `0003` insere e a `0009` apaga, resultado sem loja; banco já migrado: só a `0009` roda. Alternativa descartada: tornar a `0003` um no-op, que não limparia bancos já migrados e reescreveria uma migração antiga.

**Aviso quando a loja é preservada por ter entradas.** O migrador não tem canal de saída, então a `0009` usa `error_log()` só nesse caso raro (no CLI vai para o stderr, aparecendo no `bin/migrate`). O teste redireciona `error_log` para um arquivo temporário (`ini_set('error_log', ...)`) e confere a mensagem. Loja com dona é preservada em silêncio: não é mais "demonstração".

**Classe `Support\LojaDeDemonstracao` com `semear(Capsule): bool`.** Insere `Veste Bem`/`veste-bem` se o slug não existe e devolve `true` (criada) ou `false` (já existia). Fica em `app/support/` para ser chamada pelo comando e pelos testes (sem duplicar a semente). Constantes `SLUG` e `NOME` na própria classe.

**Comando `bin/seed-demo`.** Segue o `bin/migrate`: carrega a configuração, e **antes de conectar** confere o driver: `mysql` sem `--forcar` imprime o erro no stderr e sai com código 1. Depois conecta, confere que a tabela `estabelecimentos` existe (senão: "rode `php bin/migrate` antes", código 1) e chama a classe, imprimindo "Loja de demonstração criada: veste-bem" ou "A loja veste-bem já existe: nada a fazer.". Script `composer seed-demo` em `composer.json`. A proteção por driver (e não por variável) vale porque produção é MySQL e desenvolvimento e testes são SQLite; quem usa MySQL em desenvolvimento passa `--forcar` conscientemente.

**Testes semeiam no `setUp`.** `DatabaseTestCase` ganha `semearDemonstracao()` (chama `LojaDeDemonstracao::semear`), chamado pelo `ApiTestCase::setUp` depois de `migrate()` e pelos testes de serviço que dependem do slug (`FilaServiceTest` e os demais encontrados por busca por `veste-bem`). Os testes de slug passam a esperar `veste-bem` para a primeira loja cadastrada com esse nome (e `-2`, `-3` para as seguintes **quando** a demonstração está semeada, ou ajustados caso a caso): onde o teste quer testar a colisão de slug, ele semeia a demonstração explicitamente.

**`MigrationTest`.** A lista de migrações ganha a `0009`. Os testes que checavam a loja depois de migrar tudo passam a checar banco novo sem loja; os de bancos antigos simulados (migrações parciais) continuam, e a `0009` ganha testes próprios (vazia sem dona: removida; com entradas: preservada com aviso; com dona: preservada; outras lojas intactas; o slug fica livre para o cadastro).

**Docs e specs.** O cenário de `account-session` troca o exemplo; as docs trocam "a migração insere a `veste-bem`" por "rode `php bin/seed-demo` em desenvolvimento" e a pendência "loja sem dono" sai da seção de segurança (vira uma nota de que a demonstração é opcional e sem dona).

## Risks / Trade-offs

- [A `0009` apaga uma loja] → só apaga com `conta_id` nulo e zero entradas; qualquer outro caso é preservado. Dados de uma loja sem entradas e sem dona se resumem a nome e slug, recriáveis pelo comando.
- [Bancos de desenvolvimento perdem a `veste-bem` ao migrar] → o `bin/migrate` roda a `0009` e a pessoa roda `php bin/seed-demo`; documentado no README e na saída de erro de quem acessa `/fila/veste-bem` sem a loja (404 normal).
- [Um MySQL de desenvolvimento exige `--forcar`] → custo aceito: a proteção vale mais que a conveniência.
- [A semente chamada pelos testes e pelo comando é a mesma classe] → se a demonstração mudar, os dois mudam juntos (é o objetivo).
- [Testes de slug mais frágeis por dependerem de a demonstração estar semeada ou não] → cada teste de colisão semeia explicitamente; o `setUp` do `ApiTestCase` semeia por padrão e os testes de cadastro que querem slug livre removem a loja no próprio teste.

## Migration Plan

Rodar `php bin/migrate` no deploy executa a `0009` (sem efeito em banco que já não tem a demonstração vazia). Em desenvolvimento, depois de migrar, rodar `php bin/seed-demo` se a demonstração for necessária. Rollback: reverter o código; a loja apagada pode ser recriada com `php bin/seed-demo --forcar` (MySQL) ou `php bin/seed-demo` (SQLite).
