# Proposal

## Why

O e-mail e o CNPJ são únicos, e uma conta com o e-mail **não confirmado** ocupa os dois para sempre: quem cadastra o e-mail de outra pessoa a bloqueia, e o dono de verdade leva `409` sem nenhuma saída (issue #32). Além disso, dois cadastros simultâneos com o mesmo e-mail ou CNPJ hoje terminam em `500`, porque só a colisão de slug é tratada.

## What Changes

- Uma conta passa a poder ser **substituída** por um cadastro novo com o mesmo e-mail ou CNPJ quando, ao mesmo tempo: o e-mail dela não está confirmado, o prazo para confirmar (14 dias) já passou e a loja dela não recebeu nenhuma entrada na fila nos últimos 14 dias. A verificação acontece no próprio cadastro, só para as contas que o bloqueiam; não há limpeza periódica.
- Na substituição, a conta antiga é apagada com tudo o que é dela: loja (slug, QR code e fila), entradas da fila, sessões e links de confirmação e de redefinição de senha.
- Uma conta **confirmada nunca** é substituída. Fora da regra, o `409` e as mensagens continuam os mesmos.
- A conta ganha o prazo `confirmar_ate` (14 dias depois do cadastro), devolvido nas respostas de cadastro, login e `GET /api/conta` enquanto o e-mail não está confirmado. As contas não confirmadas que já existem ganham 14 dias contados a partir do deploy, para ninguém perder a loja no dia em que a mudança entrar.
- A faixa "Confirme seu e-mail" do dashboard passa a mostrar o prazo e o que acontece depois dele.
- Dois cadastros simultâneos com o mesmo e-mail ou CNPJ passam a dar `409` em vez de `500`.

Fora do escopo (documentado no `server/README.md`):
- Provar que o CNPJ é de quem se cadastra: quem confirma um e-mail próprio continua podendo ocupar um CNPJ alheio.
- Recuperar por e-mail a senha de uma conta não confirmada (continua bloqueado, para que o dono de um e-mail digitado errado não tome a conta).
- Comando ou tarefa agendada de limpeza.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `establishment-account`: a unicidade de e-mail e CNPJ ganha a exceção da conta não confirmada abandonada, o que é apagado com ela e o `409` (em vez de `500`) nos cadastros simultâneos.
- `email-confirmation`: prazo `confirmar_ate` nas respostas, prazo das contas que já existiam, a confirmação passa a proteger a conta da substituição e a faixa do dashboard mostra o prazo.

## Impact

- **API:** `server/app/services/ContaService.php` (conflito, substituição e corrida), um serviço novo de exclusão de conta em cascata (que a exclusão de conta da #26 vai reaproveitar), `server/app/models/Conta.php` (`confirmar_ate` na resposta) e a migração `0010` (coluna `contas.confirmar_ate`, preenchida para as contas não confirmadas que já existem).
- **Front:** `src/Dashboard/AvisoEmail.js` (prazo na faixa) e `src/sessao/SessaoDeTeste.js`.
- **Respostas:** `conta.confirmar_ate` é um campo novo; nenhum campo existente muda.
- **Docs:** `server/README.md` (regra, risco do CNPJ e tabela de migrações), `README.md` e o contexto em `openspec/config.yaml`.
- **Sem mudança:** o limite de cadastros por IP (a substituição também gasta uma tentativa), o "esqueci a senha" e a loja de demonstração.
