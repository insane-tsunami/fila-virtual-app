# Proposal

## Why

Quem esquece a senha perde o acesso à conta e à loja: hoje só dá para trocar a senha estando logada. Com o cadastro e o login no ar, "esqueci a senha" é a pendência que mais trava um dono de loja de verdade. O projeto não tem envio de e-mail e a hospedagem ainda não foi escolhida, então o envio precisa ser trocável por configuração.

## What Changes

- Novo fluxo de redefinição por e-mail: `POST /api/senha/esqueci` (sempre `202`, com a mesma mensagem para e-mail com e sem conta) e `POST /api/senha/redefinir` (`{token, nova_senha}`).
- Token de redefinição de 256 bits, guardado só como hash, válido por 1 hora e de uso único; um pedido novo invalida o anterior da mesma conta.
- Redefinir a senha troca a senha, queima os tokens da conta e **encerra todas as sessões**; não abre sessão nova (a pessoa entra de novo pelo login).
- Telas `/esqueci-senha` e `/redefinir-senha`; o login ganha o link "Esqueci a senha" e mostra o aviso "Senha alterada. Entre com a nova senha." depois da redefinição. O link do e-mail leva o token no fragmento (`/redefinir-senha#token=...`), que não vai para logs nem para o `Referer`; a tela lê o token e limpa o endereço.
- Envio de e-mail plugável por `MAIL_DRIVER`: `desligado` (padrão, só registra um aviso no log), `log` (grava a mensagem no log, só para desenvolvimento e testes) e `smtp` (envio real, `MAIL_DSN` e `MAIL_FROM`). Nova dependência `symfony/mailer` para o `smtp`.
- Nova variável `APP_URL` (base do link do e-mail).
- Limites de tentativas para os dois endpoints novos: 3 pedidos por hora por e-mail e 10 por hora por IP (contando todos), e 20 tokens inválidos por 15 minutos por IP.
- Falha no envio não aparece na resposta (senão revelaria quais e-mails têm conta): vai para o log.

Fora do escopo: confirmação de e-mail no cadastro, e-mail de "sua senha foi alterada", redefinição de senha por outros meios (sem e-mail) e login automático depois da redefinição.

## Capabilities

### New Capabilities
- `password-recovery`: pedir e concluir a redefinição de senha por link enviado ao e-mail, as telas do fluxo e a regra de não revelar quais e-mails têm conta.
- `email-delivery`: envio de e-mail da API por drivers configuráveis (`desligado`, `log`, `smtp`) e o conteúdo básico das mensagens.

### Modified Capabilities
- `rate-limiting`: novos limites para o pedido e para a conclusão da redefinição de senha.
- `establishment-login`: o login ganha o link "Esqueci a senha" e o aviso exibido depois da redefinição.

## Impact

- `server/`: nova migração `0007`, serviços de recuperação e de envio, controller e rotas novas, `SessaoService` (encerrar todas as sessões), `LimiteDeTentativas` e `api/config.php` (novos escopos e variáveis), `composer.json` e `composer.lock` (`symfony/mailer`), `.env.example`, `server/README.md` e testes.
- `src/`: `api.js`, duas telas novas, rotas em `App.js`, link e aviso no `Login`, e testes.
- Documentação: `README.md` e `openspec/config.yaml` (tirar "esqueci a senha" das pendências).
- Nova dependência de produção (`symfony/mailer` e suas dependências indiretas); sem outros sistemas novos.
