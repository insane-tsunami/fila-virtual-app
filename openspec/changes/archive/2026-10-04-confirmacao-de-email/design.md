# Design

## Context

O e-mail da conta hoje é só texto: o cadastro o aceita sem prova e o "esqueci a senha" já manda o link de redefinição para ele (`RecuperacaoSenhaService`). Já existem o envio de e-mail plugável (`Mailer`, com `APP_URL` para montar links), o desenho de token opaco com só o hash no banco (tabela `redefinicoes_senha`, um por conta, de uso único), as regras de limite de tentativas (`LimiteDeTentativas`) e as respostas `{conta, loja}` de cadastro, login e `GET /api/conta`. No front, `SessaoProvider` guarda a conta da sessão (e já a restaura com `obterConta`), `RotaAnonima` redireciona quem está logada e o Perfil lida com a troca de senha. Motivação e escopo: ver proposal.md.

## Goals / Non-Goals

**Goals:**
- Provar a posse do e-mail por um link, sem impedir o uso do dashboard.
- Impedir que um e-mail não confirmado receba link de redefinição de senha.
- Dar uma saída para o e-mail digitado errado.
- Reaproveitar o `Mailer`, o desenho de token e o limitador existentes.

**Non-Goals:**
- Trocar o e-mail de conta já confirmada; expirar ou recadastrar sobre contas não confirmadas; provar posse do CNPJ.
- E-mail de aviso de troca de e-mail; polling automático do estado de confirmação no front.

## Decisions

**Dados (migração `0008`).** Coluna `contas.email_confirmado_em` (datetime, nula = não confirmado; as contas existentes ficam nulas) e tabela `confirmacoes_email` com `id`, `conta_id` (chave estrangeira, única), `email` (o endereço para o qual o token foi enviado), `token_hash` (char 64, único), `criado_em` e `expira_em`. A coluna de data, e não um booleano, guarda *quando* foi confirmado sem custo extra. A tabela espelha `redefinicoes_senha`: um token por conta (o novo apaga o anterior na mesma transação, junto com os vencidos), uso único (confirmar apaga a linha) e só o hash do token. O `email` gravado impede que o token do endereço antigo confirme o novo: ao confirmar, o serviço confere `linha.email == conta.email`.

**`ConfirmacaoEmailService`.** Quatro operações:
- `enviar(Conta)`: cria o token, monta `<APP_URL>/confirmar-email#token=...` e manda o e-mail pelo `Mailer`; falha de envio e `APP_URL` ausente vão para o log (mesmas regras do "esqueci a senha"), nunca para a resposta. É usada pelo cadastro, pelo reenvio e pela troca de e-mail.
- `confirmar(token, ip)`: verifica o limite do IP, procura o token válido e preso ao e-mail atual; se não achar, registra erro no limite e lança `422` ("Link inválido ou expirado. Peça um novo e-mail de confirmação."); se achar, em transação marca `email_confirmado_em` e apaga o token.
- `reenviar(contaId)`: `409` se já confirmado; consome o limite da conta e chama `enviar`.
- `trocarEmail(contaId, email, senha)`: `409` se já confirmado; verifica o limite de senha da conta e confere a senha (`422` e registra erro se errada); valida o formato e a unicidade (`422`/`409`); consome o limite de envios; em transação troca o e-mail (a unicidade é conferida pela coluna `unique` e a violação vira `409`, como no cadastro) e chama `enviar`.

**Cadastro e respostas.** `ContaService::cadastrar` passa a chamar `enviar` depois do commit (uma falha ali não desfaz o cadastro). `email_confirmado` (booleano, `email_confirmado_em !== null`) entra no objeto `conta` de `SessaoService::abrir` (cadastro e login) e de `ContaService::dados`. Para evitar que cada ponto monte o objeto, um único helper do modelo `Conta` devolve `{email, cnpj, email_confirmado}`.

**"Esqueci a senha" só para e-mail confirmado.** `RecuperacaoSenhaService::pedir` passa a tratar conta não confirmada como "sem conta": conta o limite, devolve a mesma mensagem e não cria token nem envia. Redefinir não muda (só existe token para quem pediu como confirmado). Como o limite por e-mail e por IP já conta todo pedido, o `429` continua sem revelar nada.

**Rotas.** Públicas: `POST /api/email/confirmar` (`200` `{"mensagem": "E-mail confirmado."}`). Com sessão (`Autenticacao`): `POST /api/conta/email/reenviar` (`202`) e `PUT /api/conta/email` (`200` com a conta). Um `ConfirmacaoEmailController` fino, como os demais.

**Limites.** Novos escopos em `LimiteDeTentativas`: `confirmacao_conta` (3 em 60 min, chave = id da conta, consome toda chamada que chega a enviar) e `confirmar_ip` (20 em 15 min, só tokens inválidos, `verificar` + `registrar`). A senha errada na troca de e-mail usa o escopo `senha_conta` já existente (a mesma janela da troca de senha), então quem erra a senha numa rota não ganha tentativas novas na outra. `api/config.php` ganha `confirmacao_conta`, `confirmacao_janela_minutos` e `confirmar_ip` (variáveis `RATE_LIMIT_CONFIRMACAO_CONTA`, `RATE_LIMIT_CONFIRMACAO_JANELA_MIN` e `RATE_LIMIT_CONFIRMAR_IP`; valor inválido cai no padrão).

**Front: estado da sessão.** O objeto `conta` do `SessaoProvider` passa a trazer `email_confirmado`. O provider ganha `atualizarConta()` (chama `obterConta` e troca a conta no estado; falha de rede não derruba a sessão, `401` expira como já faz) e `definirConta(conta)` para a troca de e-mail aplicar a resposta sem nova consulta. A faixa e o Perfil leem desse estado.

**Front: telas.**
- `AvisoEmail` (faixa "Confirme seu e-mail"): componente que só aparece com `email_confirmado === false`; botões "Reenviar e-mail" (chama a API e mostra a mensagem), "Já confirmei" (`atualizarConta`) e o link "Trocar e-mail" para `/dashboard/perfil`. É renderizado no topo das três páginas do dashboard (Dashboard, Qrcode, Perfil).
- `ConfirmarEmail`: rota `/confirmar-email` **fora** de `RotaAnonima`, porque o link pode ser aberto no mesmo navegador de quem está logada; lê o token do fragmento, tira-o do endereço (`history.replace`) e chama `confirmarEmail(token)` uma vez ao montar. Como o link costuma abrir em outra aba, onde o `sessionStorage` não tem a sessão, a tela oferece "Entrar" (que, estando logada, o login já leva ao dashboard).
- `Perfil`: mostra o estado do e-mail; se não confirmado, o formulário "Novo e-mail" + "Senha atual" + "Trocar e-mail" (`definirConta` com a resposta); se confirmado, o e-mail fica somente leitura como hoje.

**Contas existentes.** A migração só acrescenta a coluna nula: nenhuma conta antiga é marcada como confirmada. Efeito colateral aceito: até confirmarem, elas não recebem link de "esqueci a senha". Como o envio de e-mail está desligado por padrão e não há conta real em produção, o custo é só para ambientes de desenvolvimento.

**Alternativas descartadas.** Bloqueio total do login até confirmar (trava todo mundo enquanto o envio estiver desligado e piora o primeiro uso); variável para alternar entre os dois modos (dobra o comportamento a especificar e testar); marcar as contas existentes como confirmadas (legitimaria justamente os e-mails que podem estar errados); permitir trocar o e-mail de conta confirmada (exigiria reconfirmar e avisar o endereço antigo, que é outro desenho).

## Risks / Trade-offs

- [Quem cadastra o e-mail de outra pessoa a bloqueia: o e-mail é único e a conta não confirmada ocupa o endereço, então o dono de verdade leva `409` ao se cadastrar] → fica fora do escopo; resolver pede expirar contas não confirmadas ou permitir recadastrar sobre elas. Registrado como risco conhecido.
- [Conta não confirmada com e-mail errado e senha esquecida fica sem recuperação por e-mail] → é o custo de não mandar link para endereço não provado; enquanto a sessão (7 dias) valer, a pessoa troca o e-mail no Perfil com a senha atual.
- [Link aberto em outra aba não atualiza a faixa da aba original] → o botão "Já confirmei" reconsulta a conta; sem polling para não gerar tráfego.
- [Falha do envio no cadastro deixa a conta sem e-mail de confirmação] → vai para o log e a pessoa usa "Reenviar e-mail"; o cadastro não pode falhar por isso.
- [Dois clientes concorrentes trocando para o mesmo e-mail] → a restrição `unique` da coluna decide; o perdedor recebe `409`.
- [Enumeração de contas pela troca de e-mail (`409` revela que o e-mail existe)] → exige sessão e senha atual, e a mesma informação já vaza pelo cadastro; o limite por conta reduz o uso em massa.

## Migration Plan

Rodar `php bin/migrate` no deploy (adiciona `contas.email_confirmado_em` e cria `confirmacoes_email`). Sem configuração nova nada quebra: as contas ficam não confirmadas, a faixa aparece e, com o envio desligado, o reenvio responde `202` sem mandar e-mail. Para confirmar de verdade é preciso o envio configurado (`MAIL_DRIVER=smtp`, `MAIL_DSN`, `MAIL_FROM`, `APP_URL`). Rollback: reverter o código; coluna e tabela novas podem ficar.
