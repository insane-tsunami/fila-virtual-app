# Proposal

## Why

O cadastro aceita qualquer e-mail sem prova de que ele é de quem se cadastrou. Como o "esqueci a senha" já manda o link de redefinição para o e-mail da conta, quem erra o e-mail ao cadastrar (ou digita o de outra pessoa) entrega a conta e a loja ao dono do endereço digitado. E quem erra o e-mail não tem como corrigir: não existe troca de e-mail e o CNPJ é único, então não consegue nem se recadastrar.

## What Changes

- Cada conta passa a ter o estado "e-mail confirmado" (`email_confirmado`). A conta nasce não confirmada e o cadastro envia sozinho o e-mail de confirmação (falha do envio vai para o log e não derruba o cadastro).
- Bloqueio leve: a conta não confirmada entra e usa o dashboard normalmente. A trava está no "esqueci a senha": o link de redefinição só é enviado para e-mail **confirmado** (para os demais, a resposta continua `202` igual e nenhum e-mail sai).
- Novo token de confirmação (256 bits, só o hash no banco, 24 horas, uso único, um por conta) com o link `<APP_URL>/confirmar-email#token=...` (no fragmento, como na redefinição).
- `POST /api/email/confirmar` (público): confirma o e-mail da conta do token.
- `POST /api/conta/email/reenviar` (com sessão): reenvia a confirmação enquanto não confirmado.
- `PUT /api/conta/email` (com sessão): troca o e-mail enquanto não confirmado, exigindo a senha atual; `409` se o e-mail já é de outra conta; envia nova confirmação e invalida a anterior. É a saída para o erro de digitação.
- `email_confirmado` passa a aparecer nas respostas de cadastro, login e `GET /api/conta`.
- Front: faixa "Confirme seu e-mail" no dashboard (com "Reenviar", "Já confirmei" e "Trocar e-mail"), página pública `/confirmar-email`, e o Perfil mostra o estado do e-mail e, enquanto não confirmado, o formulário de troca.
- Limites de tentativas: 3 por hora por conta para reenviar e trocar o e-mail, a senha errada na troca usa o limite de senha da conta, e 20 tokens inválidos por 15 min por IP na confirmação.
- As contas que já existem passam a não confirmadas (sem anistia).

Fora do escopo: trocar o e-mail de uma conta já confirmada, expirar contas não confirmadas ou deixar recadastrar sobre elas (quem cadastra o e-mail de outra pessoa continua a bloqueá-la com `409`), provar que o CNPJ é de quem se cadastra e aviso por e-mail da troca de e-mail.

## Capabilities

### New Capabilities
- `email-confirmation`: estado de confirmação do e-mail da conta, token e link de confirmação, confirmar, reenviar, trocar o e-mail enquanto não confirmado, a página de confirmação e a faixa de aviso no dashboard.

### Modified Capabilities
- `establishment-account`: o cadastro cria a conta não confirmada e envia a confirmação; os dados da conta trazem `email_confirmado`.
- `account-session`: a conta devolvida no login traz `email_confirmado`.
- `password-recovery`: o pedido de redefinição só envia o link para e-mail confirmado.
- `establishment-profile`: o Perfil mostra o estado do e-mail e, enquanto não confirmado, deixa trocá-lo.
- `rate-limiting`: limites do reenvio, da troca de e-mail e dos tokens inválidos na confirmação.

## Impact

- `server/`: nova migração `0008` (coluna `contas.email_confirmado_em` e tabela `confirmacoes_email`), serviço e controller de confirmação, rotas novas, `ContaService`/`SessaoService`/`RecuperacaoSenhaService`, `LimiteDeTentativas` e `api/config.php`, `.env.example`, `server/README.md` e testes.
- `src/`: `api.js`, estado da sessão (`email_confirmado` e atualização da conta), faixa de aviso, página `/confirmar-email`, Perfil, rotas em `App.js` e testes.
- Documentação: `README.md` e `openspec/config.yaml` (tirar "confirmação de e-mail" das pendências).
- Sem novas dependências: usa o envio de e-mail já existente.
