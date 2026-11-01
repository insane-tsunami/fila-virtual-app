# Design

## Context

- `FilaService` serializa `entrar` e `finalizar` por loja com `lockForUpdate` na linha do estabelecimento e mantém a invariante de no máximo uma entrada `em_atendimento`, sempre a primeira ativa por `id`.
- `finalizar` grava `finalizou_em` e promove a próxima. A retenção (`RetencaoDeDados`) também produz `cancelado` e apaga o telefone depois de 30 dias.
- A consulta pública usa só o `codigo` como segredo (192 bits aleatórios): quem o tem é o dono daquela entrada. O telefone nunca volta na resposta.
- A página do cliente já trata `cancelado` como encerrada, mostra "Entrada encerrada" e para de consultar.

## Goals / Non-Goals

**Goals:**
- Sair na hora, com o telefone apagado imediatamente (a finalidade acabou).
- Nenhuma entrada ativa sem ninguém em atendimento.
- Mesmo contrato público e mesmo segredo (o código) da consulta.

**Non-Goals:**
- Apagar a linha da entrada (o histórico anonimizado fica para as métricas).
- Autenticar o cliente por telefone, criar limite de requisições novo ou devolver o telefone.

## Decisions

### 1. Apagar o telefone na saída, não só encerrar

`UPDATE` da entrada para `cancelado`, `finalizou_em = agora` e `telefone = null`, na mesma transação. A retenção já apaga depois de 30 dias; aqui o titular pediu para sair, então a finalidade termina agora e não há motivo para guardar o número. Código, status e datas continuam (métricas e a própria consulta).

### 2. Reutilizar a trava e a promoção de `finalizar`

A operação roda em `Capsule::transaction(..., 3)` e trava a loja com `lockForUpdate`, como `entrar` e `finalizar`. Depois de encerrar, se não há ninguém em atendimento e sobrou ativa, promove a de menor `id` com `iniciou_em = agora`. Isso mantém a invariante mesmo se a saída for da entrada em atendimento.

### 3. Idempotente para entrada já encerrada

Se a entrada encontrada já está `finalizado` ou `cancelado`, responde `200` com o resumo atual e não altera nada. Evita erro no duplo clique e no navegador que reenvia o `DELETE`, e não vaza se a entrada foi finalizada pela loja ou cancelada pela retenção. O telefone de uma entrada já encerrada não é tocado: a retenção cuida do prazo.

### 4. Rota pública, sem sessão

`DELETE /api/filas/{slug}/entradas/{codigo}` fica ao lado da consulta pública. O código é o segredo; exigir sessão não protegeria melhor (o cliente não tem conta) e quebraria a página. Quem souber o código pode encerrar a entrada, o que é o mesmo risco de quem entra na fila com qualquer telefone (documentado em `server/README.md`).

### 5. A página volta ao formulário

Depois de sair, o código guardado deixa de ser útil (a entrada está encerrada), então a página apaga o código e mostra o formulário. Se a chamada falhar por rede ou `404`, mantém o estado atual e avisa; com `404` o código já foi descartado pelo fluxo existente. O botão só aparece com entrada ativa (não em "Entrada encerrada").

## Risks / Trade-offs

- **[Qualquer um com o código encerra a entrada]** → É o mesmo segredo que já permite consultar; não há dado novo exposto e a entrada pode ser recriada pelo mesmo telefone. Fica documentado.
- **[Telefone apagado antes da métrica]** → As métricas não usam o telefone; só posição/status/datas.
- **[Saída durante o atendimento]** → A loja vê a entrada sumir e a próxima ser chamada; é o mesmo efeito de `finalizar`, sem a confirmação da loja.
