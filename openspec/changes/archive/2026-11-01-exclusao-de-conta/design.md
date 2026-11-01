# Design

## Context

- `Services\ExclusaoDeConta::excluir(int $contaId)` já apaga a conta com a loja, as entradas da fila, as sessões, os tokens de confirmação/redefinição e os contadores presos à conta. Roda dentro da transação do chamador.
- `ContaService::trocarSenha` já confere a senha atual com `Senha::confere`, usa o limite `SENHA_CONTA` e trata senha errada como `422` (para não confundir com `401`, que é sessão inválida).
- O Perfil usa `useSessao()` (`token`, `conta`, `expirar`) e já tem formulários com resultado de sucesso/erro.
- `DELETE /api/sessao` encerra a sessão atual; o front guarda o token em `sessionStorage`.

## Goals / Non-Goals

**Goals:**
- Exclusão definitiva, imediata e verificável, com a senha atual como prova de posse.
- Reaproveitar a exclusão já testada (a mesma da substituição de conta abandonada) para não divergir.
- Deixar claro no Perfil o que some e o que não dá para desfazer.

**Non-Goals:**
- Exclusão por e-mail sem senha, período de carência, lixeira ou restauração.
- Reter dados fiscais/contábeis (decisão jurídica da #26; hoje a conta é só da plataforma).
- Excluir os contadores por e-mail e por IP (são hashes que vencem sozinhos; apagá-los zeraria o limite de login de um e-mail).

## Decisions

### 1. `DELETE /api/conta` com a senha no corpo

A rota exige sessão válida (`Autenticacao`) e o corpo `{ "senha": "..." }`. `ContaService::excluir` confere a senha, registra o erro no limite `SENHA_CONTA` e, se estiver certa, roda `ExclusaoDeConta` dentro de uma transação. A resposta é `200` com `{"mensagem":"Conta excluída."}` (um `204` também serviria, mas a página mostra a mensagem e o padrão do projeto é JSON com mensagem).

### 2. Senha errada é `422`, como na troca de senha

Mantém o mesmo contrato de `PUT /api/conta/senha`: `401` continua sendo "sem sessão", e a mensagem "Senha atual incorreta." aparece no formulário. Como a sessão ainda vale, a pessoa pode tentar de novo.

### 3. Sessão encerrada no fim, não antes

A exclusão apaga todas as sessões da conta, inclusive a atual (a tabela `sessoes` some com a conta). O front, ao receber `200`, limpa o `sessionStorage` e navega para `/`. Se o front falhar depois do `200`, o token já não vale nada.

### 4. Perfil com confirmação explícita

Seção separada, com o texto do que será apagado, campo "Senha atual" e botão "Excluir minha conta" (vermelho). Não usa `window.confirm`: o teste do front controla o formulário e o `confirm` do jsdom atrapalha. O botão fica desabilitado sem senha. Erro de rede mantém o formulário; `401` chama `expirar()`.

## Risks / Trade-offs

- **[Exclusão sem carência]** → Um engano apaga tudo na hora. O texto do Perfil avisa que não dá para desfazer; a senha atual é a barreira. Uma carência exigiria estado novo e não foi pedida.
- **[Contadores por e-mail/IP ficam]** → Vencem em minutos/horas e não contêm o e-mail em claro (hash); retirá-los abriria o limite de login.
- **[Exclusão e substituição usam o mesmo serviço]** → Mudanças futuras numa afetam a outra; é intencional (o que sai é o mesmo).
