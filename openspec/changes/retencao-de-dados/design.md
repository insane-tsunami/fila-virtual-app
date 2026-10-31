# Design

## Context

Estado atual relevante (código conferido):

- **`entradas_fila`** (`0002`): `telefone` é `string(20)` NOT NULL. Há um índice em `(estabelecimento_id, status, id)`, mas nenhum em `finalizou_em`. `EntradaFila::CANCELADO` existe só como reservado, e nenhuma operação o produz. `finalizar` grava `finalizou_em`.
- **`FilaService`:**
  - serializa `entrar` e `finalizar` por loja com `lockForUpdate` na linha do estabelecimento;
  - garante a invariante: no máximo uma entrada `em_atendimento` por loja, e ela é a primeira ativa por `id`;
  - `posicao()` já devolve `null` para qualquer status fora de `ATIVOS`, e o filtro de duplicata só olha as ativas. Na API, `cancelado` já se comporta como a spec pede; faltam testes.
- **Limpeza oportunista que já existe:**
  - sessões vencidas: só no login (`SessaoService.php:68`);
  - tokens de redefinição e de confirmação vencidos: quando se cria outro token;
  - contadores: a cada escrita do limite.
  - Tudo filtra por `expira_em`, por isso nenhuma leitura depende de uma linha vencida.
- **`bin/`:** tem `migrate` e `seed-demo`. Os dois leem `api/config.php`, conectam com `Support\Database` e saem com `exit(1)` e mensagem em STDERR. `SeedDemoCommandTest` roda os comandos com `proc_open` e um SQLite temporário.
- **Configuração:** `api/config.php` tem o helper `$inteiro`, em que só inteiros positivos valem e o resto usa o padrão.
- **Página do cliente** (`src/Cliente/index.js`): `finalizada = status === 'finalizado'` decide parar a consulta e mostrar "Atendimento finalizado". Qualquer outro status cai no ramo "Aguardando" com `posicao`.
- **Testes já feitos:** no SQLite, `->nullable()->change()` em `telefone` recria a tabela mantendo os dados, a FK e os índices.
- **Hospedagem:** não definida (#27). Não há agendador no projeto.

## Goals / Non-Goals

**Goals:**
- Um comando idempotente que a hospedagem agenda (de hora em hora, por exemplo) e que aplica todas as regras de retenção.
- Manter a invariante da fila mesmo quando o comando encerra a entrada em atendimento.
- Nenhuma mudança de comportamento nas rotas além de `cancelado` passar a aparecer.

**Non-Goals:**
- Rodar a limpeza dentro das requisições (sem cron). Ver a decisão 6.
- Apagar contas, lojas ou entradas inteiras.
- Rota ou tela para a dona ver as entradas encerradas.

## Decisions

### 1. Serviço `Services\RetencaoDeDados` e comando `bin/limpar`

O serviço recebe os dois prazos no construtor. `limpar(): array` devolve as contagens: `entradas_encerradas`, `telefones_apagados`, `sessoes`, `tokens` (redefinição e confirmação somados) e `contadores`.

O `bin/limpar` segue o modelo do `seed-demo`:
- lê a config e conecta;
- confere as migrações pendentes (decisão 5);
- roda o serviço e imprime uma linha por contagem;
- sai com `0`, ou com `1` e a mensagem em STDERR.

Alternativa descartada: pôr as regras no `FilaService`. A limpeza também mexe em sessões, tokens e contadores, e um serviço próprio deixa o comando fino e testável sem processo.

### 2. Encerrar as esquecidas loja a loja, com a mesma trava da fila

- O prazo conta desde a última mudança de estado, `COALESCE(iniciou_em, entrou_em)`: `entrou_em` para quem aguarda e `iniciou_em` para quem está em atendimento. Assim, quem esperou 23 h e acabou de ser chamado não é encerrado no meio do atendimento.
- O serviço lista os `estabelecimento_id` que têm uma ativa vencida. Para cada um, abre uma transação (`transaction($fn, 3)`, que repete em deadlock), trava a loja com `lockForUpdate`, como `entrar` e `finalizar`, e repete a busca dentro da trava.
- Marca as vencidas como `cancelado` com `finalizou_em = agora`, que passa a ser a hora do encerramento e serve para a regra do telefone.
- Se, depois disso, a loja tem ativas e nenhuma `em_atendimento`, promove a de menor `id` (`em_atendimento`, `iniciou_em = agora`), como `finalizar` faz.

Alternativa descartada: um `UPDATE` só para todas as lojas. Seria mais rápido, mas correria contra `entrar` e `finalizar` e poderia deixar a fila sem ninguém em atendimento.

A promoção por cron chama um cliente que pode não estar mais na loja. Mas a entrada dele também vence 24 h depois, e é o mesmo que `finalizar` faria.

### 3. Telefone apagado com `UPDATE` único

`UPDATE entradas_fila SET telefone = NULL WHERE status IN ('finalizado','cancelado') AND telefone IS NOT NULL AND finalizou_em < corte`. Entradas encerradas não mudam mais, então não há corrida com a fila. A coluna fica `null` (e não vazia) para não colidir com nenhum telefone válido e para o `WHERE` ser simples.

### 4. Vencidos removidos com `DELETE` por `expira_em`

- `sessoes` usa `<` (como o login hoje).
- `redefinicoes_senha`, `confirmacoes_email` e `limites_tentativas` usam `<=` (como o código que já os limpa).

Os mesmos critérios das leituras garantem que nada válido some. Contas não são tocadas, nem as não confirmadas com prazo vencido: a #32 resolve essas no cadastro e a D8 decidiu não apagar contas inativas.

### 5. Migrações pendentes bloqueiam o comando

`Support\Migrator` ganha `pendentes(): list<string>`, que só lê (sem tabela `migrations`, todos os arquivos estão pendentes) e não cria nada. Com pendentes, o `bin/limpar` sai com `1` e "Banco com migrações pendentes: rode `php bin/migrate` antes.". Isso cobre o deploy em que o código novo chega antes da `0012` e o `UPDATE ... NULL` falharia no NOT NULL.

### 6. Só por cron, documentado

O comando não roda sozinho. O `server/README.md` (Hospedagem) passa a pedir um agendamento de hora em hora, por exemplo `0 * * * * cd /caminho/server && php bin/limpar`. Sem cron, nada é limpo; o README diz isso na pendência da LGPD.

Alternativa descartada: limpeza oportunista em cada requisição. Põe trabalho e trava no caminho das rotas públicas, e o prazo deixaria de valer em lojas sem tráfego.

### 7. Migração `0012_entradas_fila_telefone_anulavel`

`telefone` passa a `->nullable()->change()` e ganha o índice em `finalizou_em`. O `Models\EntradaFila` declara `string|null $telefone`, e o comentário de `CANCELADO` deixa de dizer "reservado". `Telefone::mascarar` só recebe telefones de entradas ativas, que nunca são apagados.

### 8. Configuração

`api/config.php` ganha `'retencao' => ['entrada_ativa_horas' => $inteiro('RETENCAO_ENTRADA_ATIVA_HORAS', 24), 'telefone_dias' => $inteiro('RETENCAO_TELEFONE_DIAS', 30)]`. Também entram no `.env.example` e na tabela de variáveis do README.

### 9. Página do cliente

- `finalizada` passa a `encerrada`, que vale para `finalizado` ou `cancelado`.
- O título é "Atendimento finalizado" ou "Entrada encerrada". Para `cancelado` entra a linha "Sua entrada foi encerrada sem atendimento.".
- O botão "Entrar na fila de novo" é o mesmo nos dois casos.
- Ao reabrir com um código de entrada cancelada, a consulta inicial já devolve `cancelado` e o acompanhamento não começa, como hoje com `finalizado`.

O texto é genérico de propósito: o change `sair-da-fila` também vai produzir `cancelado`.

## Risks / Trade-offs

- **[Cron não configurado]** → Nada é limpo. É o risco principal enquanto a #27 não define a hospedagem; fica no README, na pendência da LGPD e nas instruções de deploy.
- **[`change()` no MySQL]** → `ALTER TABLE ... MODIFY` numa tabela grande trava a escrita por alguns instantes. Conferir na suíte MySQL 8.4; a tabela ainda é pequena.
- **[Backups e logs]** → Guardam o telefone além dos 30 dias. Isso depende da hospedagem (#27) e fica fora do código.
- **[Entrada promovida por cron]** → Um cliente pode ser chamado com a loja fechada. É raro (exige uma entrada em atendimento parada há 24 h e outra ativa recente) e é o mesmo efeito de `finalizar`.
- **[`finalizou_em` para cancelada]** → O nome sugere atendimento finalizado. Fica documentado no modelo como "hora em que a entrada saiu da fila", sem renomear a coluna.

## Migration Plan

1. Deploy: `php bin/migrate` roda a `0012`. As linhas existentes não mudam.
2. Agendar `php bin/limpar` de hora em hora. A primeira execução encerra as ativas esquecidas e apaga os telefones das entradas com mais de 30 dias.
3. Rollback: voltar o código. A coluna anulável e o índice são compatíveis com o código anterior, a não ser pelas linhas com telefone `null`, que o código anterior só lê em entradas encerradas, sem mascarar. Status `cancelado` não aparece no dashboard antigo, porque ele só lista as ativas. Os telefones apagados não voltam.
