# Design

## Context

Não existe envio de e-mail no projeto (nenhuma biblioteca, nenhuma variável) e a hospedagem não foi definida, então o meio de envio tem de ser configuração. O restante do desenho reaproveita o que existe: tokens opacos com só o hash no banco (`SessaoService`), as regras de senha (`Senha`), o encerramento de sessões (`SessaoService::encerrarOutras`) e o `LimiteDeTentativas` (janela fixa no banco, falha aberta). No front, o login já mostra `aviso` da sessão, `RotaAnonima` já esconde telas de quem está logada, e a base da API vem de `REACT_APP_API_URL`. Motivação e escopo: ver proposal.md.

## Goals / Non-Goals

**Goals:**
- Redefinição por link de uso único, sem revelar quais e-mails têm conta.
- Envio de e-mail trocável por configuração, testável sem servidor SMTP.
- Reaproveitar o limitador de tentativas e as regras de senha existentes.

**Non-Goals:**
- Confirmação de e-mail, e-mail de aviso de senha alterada, login automático depois da redefinição.
- Fila ou reenvio de e-mails (o envio é síncrono e uma falha só vai para o log).
- Igualar o tempo de resposta entre e-mail com e sem conta (ver Riscos).

## Decisions

**Tabela `redefinicoes_senha` (migração `0007`).** Colunas `id`, `conta_id` (chave estrangeira para `contas`), `token_hash` (char 64, único), `criado_em`, `expira_em`; é o mesmo desenho de `sessoes`. Uma conta tem no máximo uma linha: o pedido novo apaga as anteriores e insere a nova, na mesma transação. Tokens vencidos de qualquer conta são apagados no próprio pedido. O token usa `bin2hex(random_bytes(32))`; o uso único vem de apagar as linhas da conta ao concluir (e não de uma coluna de "usado"), então não há estado de "queimado" para esquecer.

**`RecuperacaoSenhaService`.** Dois métodos:
- `pedir(mixed $email, string $ip)`: valida o formato (`Email::normalizar`, senão `422`), consome o limite (`esqueci_ip`, `esqueci_email`), procura a conta e, se existe, cria o token e manda o e-mail. Para conta inexistente não faz nada além de contar o limite. Qualquer falha do envio é capturada e vai para o log, e o método sempre devolve a mesma mensagem.
- `redefinir(mixed $token, mixed $novaSenha, string $ip)`: verifica o limite do IP (`redefinir_ip`) antes de tudo; token ausente ou desconhecido ou vencido registra um erro no limite e lança `DadosInvalidosException('Link inválido ou expirado. Peça um novo.')`; senha inválida lança a mensagem de senha sem queimar o token nem contar no limite; senão, em uma transação, troca o hash da senha, apaga os tokens da conta e encerra **todas** as sessões (novo `SessaoService::encerrarTodas`).

**Limites.** Três escopos novos em `LimiteDeTentativas`: `esqueci_email` (3 em 60 min, consome toda chamada, chave = hash do e-mail normalizado), `esqueci_ip` (10 em 60 min, consome toda chamada) e `redefinir_ip` (20 em 15 min, só tokens inválidos, usa `verificar` + `registrar`). `daConfig` e `api/config.php` ganham `esqueci_email`, `esqueci_ip`, `esqueci_janela_minutos` e `redefinir_ip` (valor inválido cai no padrão, como os demais). O limite por e-mail conta também o e-mail sem conta, para o `429` ser igual nos dois casos.

**Envio de e-mail: interface `Mailer` com três drivers.** `Mailer::enviar(string $para, string $assunto, string $texto): void`, com `MailerDesligado` (padrão; loga só um aviso, sem corpo), `MailerLog` (loga a mensagem inteira, para desenvolvimento e testes) e `MailerSmtp` (usa `symfony/mailer` com `Transport::fromDsn($dsn)`, remetente `MAIL_FROM`). Uma fábrica `Mailer::daConfig` escolhe o driver por `MAIL_DRIVER` (valor desconhecido vira `desligado` e é logado). O `MailerSmtp` captura as exceções da biblioteca e relança uma exceção própria, sem a mensagem original, que pode conter o DSN com senha. Alternativas descartadas: escrever um cliente SMTP à mão (risco de injeção de cabeçalhos e TLS mal feito), `mail()` do PHP (depende do `sendmail` do servidor, não é configurável por DSN e é frágil na entrega) e um provedor por HTTP (prende o projeto a um fornecedor antes da hospedagem).

**E-mail como texto simples.** Assunto "ZeraFilas: redefinir sua senha"; o corpo traz o link e o prazo de 1 hora e diz que, se a pessoa não pediu, basta ignorar. Como o endereço vem de `Email::normalizar` (que usa `FILTER_VALIDATE_EMAIL`), um destinatário com quebra de linha nunca chega ao `Mailer`; o `Mailer` também recusa quebras de linha no destinatário e no assunto, como segunda barreira.

**Link com token no fragmento.** `<APP_URL>/redefinir-senha#token=<token>`: o fragmento não é enviado ao servidor nem entra no `Referer`. A tela lê `location.hash`, guarda o token no estado e troca o endereço por `/redefinir-senha` (`history.replaceState`). Sem `APP_URL` o link não pode ser montado: o e-mail não sai e o problema vai para o log (a resposta continua `202`). `APP_URL` é lida em `api/config.php` e não tem padrão.

**Rotas e controller.** `POST /api/senha/esqueci` e `POST /api/senha/redefinir`, públicas, em um `RecuperacaoSenhaController` fino (corpo, IP do cliente via `IpDoCliente`, resposta). O pedido responde `202` com `{"mensagem": "..."}`; a redefinição, `200` com `{"mensagem": "Senha alterada."}`. Os erros seguem o formato existente (`422`, `429`).

**Front.** `api.js` ganha `pedirRedefinicao(email)` e `redefinirSenha(token, novaSenha)`. Duas telas novas, `EsqueciSenha` e `RedefinirSenha`, dentro de `RotaAnonima` e com o mesmo visual do login (reaproveitando os estilos de `Login/styles`). Depois da redefinição, `RedefinirSenha` navega para `/login` com `state: { aviso: 'Senha alterada. Entre com a nova senha.' }`; o `Login` mostra esse aviso e o limpa do `state` do histórico (`history.replace` com `state` vazio), para não reaparecer ao recarregar. O link "Esqueci a senha" fica abaixo do botão "Entrar".

**Migração do Composer.** `composer require symfony/mailer` altera `composer.json` e `composer.lock`; o CI já roda `composer validate --strict` e `composer install`, então basta o lock estar em dia.

## Risks / Trade-offs

- [Diferença de tempo entre e-mail com e sem conta: o envio SMTP síncrono demora mais] → aceito e documentado; a resposta e o `429` são idênticos nos dois casos, e o limite por IP e por e-mail reduz a enumeração em massa. Mitigação futura: enviar depois de responder.
- [Falha de envio some do ponto de vista da pessoa: não recebe o e-mail e não é avisada] → é o custo de não revelar contas; o log registra a falha, a mensagem do front orienta a pedir de novo e a conferir o spam.
- [E-mail do cadastro não é confirmado: quem errou o e-mail ao cadastrar não recupera a conta, e quem cadastrou o e-mail de outra pessoa a deixa receber links] → conhecido, é a pendência "confirmação de e-mail" (fora deste change); o link só dá acesso à conta ligada àquele e-mail.
- [O driver `log` grava o link, que é um segredo] → o padrão é `desligado`, o README e o `.env.example` avisam que `log` é só para desenvolvimento e testes.
- [A senha do `MAIL_DSN` pode vazar em mensagens de erro da biblioteca] → o `MailerSmtp` não repassa a mensagem da exceção original; o teste confere que o log não contém a senha do DSN.
- [Quem tem acesso ao e-mail da conta toma a conta] → inerente a esse fluxo; mitigado por token de 1 hora, uso único, encerramento de todas as sessões e os limites de tentativas.
- [Nova dependência de produção] → `symfony/mailer` é mantida ativamente e o uso é isolado atrás da interface `Mailer`, trocável sem mexer no resto.

## Migration Plan

Rodar `composer install` e `php bin/migrate` no deploy (cria `redefinicoes_senha`). Sem configuração nova nada muda para os usuários: `MAIL_DRIVER` é `desligado` e o fluxo responde `202` sem enviar e-mail. Para ativar, definir `APP_URL`, `MAIL_DRIVER=smtp`, `MAIL_DSN` e `MAIL_FROM`. Rollback: reverter o código; a tabela nova pode ficar.
