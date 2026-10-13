# Proposal

## Why

Hoje o login é bloqueado depois de 5 senhas erradas para o mesmo e-mail, venham de onde vierem. Então qualquer pessoa que saiba o e-mail da dona consegue trancá-la fora do painel. Bastam 5 tentativas a cada 15 minutos, de um único IP, para mantê-la bloqueada indefinidamente, inclusive no próprio celular. O e-mail da loja costuma ser público, e um atacante não precisa de nenhum recurso para isso.

## What Changes

- **Erros de login contados por e-mail+IP.** O limite pequeno de erros de login (5 em 15 minutos) passa a valer para o par e-mail+IP, e não mais só para o e-mail. Quem erra a partir de um IP bloqueia só aquele IP para aquele e-mail. A dona continua entrando de qualquer outro IP.
- **Teto alto por e-mail, de qualquer IP.** O e-mail continua com um limite, agora alto (50 erros em 15 minutos), somando todos os IPs. Ele limita a velocidade de um ataque distribuído contra uma conta.
- **Limite por IP mantido.** O limite de 20 erros por IP em 15 minutos continua igual.
- **O que um login certo zera.** Um login com a senha certa zera o contador do par e-mail+IP daquele login. Ele MUST NOT zerar o teto do e-mail, para que o acesso legítimo da dona não devolva tentativas a um ataque distribuído.
- **E-mail sem conta segue contando igual.** Ele conta nos dois limites, e o `429` continua sem revelar quais e-mails têm conta.
- **Nova variável e mudança de padrão:**
  - `RATE_LIMIT_LOGIN_EMAIL_IP`, com padrão 5, é nova.
  - **BREAKING (configuração):** `RATE_LIMIT_LOGIN_EMAIL` passa a ser o teto por e-mail de qualquer IP, e o padrão dela sobe de 5 para 50. Uma instalação que fixou essa variável em 5 mantém o bloqueio antigo até ajustá-la.
- **Fora do escopo:**
  - os demais limites (cadastro, troca de senha, esqueci a senha, confirmação);
  - o mecanismo de contadores (tabela `limites_tentativas`, janela fixa);
  - o front, porque a mensagem do `429` não muda.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `rate-limiting`: o requisito "Limite de erros no login" passa do bloqueio por e-mail para os limites por e-mail+IP, por e-mail (teto global) e por IP. O requisito "Limites configuráveis" ganha a variável nova e o novo padrão do teto por e-mail. O requisito "Resposta de limite excedido" passa a exigir a equivalência entre e-mails com e sem conta também no limite por e-mail+IP.

## Impact

- **API:** `server/app/services/SessaoService.php` (contadores do login) e `server/app/services/LimiteDeTentativas.php` (novo escopo `login_email_ip`).
- **Configuração:**
  - `server/api/config.php`, com a nova chave `login_email_ip` e o novo padrão de `login_email`;
  - `server/.env.example`, `server/README.md` e o `README.md` da raiz;
  - o contexto em `openspec/config.yaml`.
- **Testes:** `server/tests/RateLimitApiTest.php`, `server/tests/ConfigTest.php` e `server/tests/LimiteDeTentativasTest.php`. Os cenários de dona bloqueada e de dona entrando de outro IP aproveitam os testes da branch local `limite-de-tentativas` (commit `47c5cce`).
- **Banco:** nenhuma migração. O escopo novo cabe em `limites_tentativas.escopo` (32 caracteres). Os contadores `login_email` existentes passam a ser lidos com o limite novo.
- **Segurança:**
  - **Contra um único IP:** a resistência a adivinhar a senha continua igual, com 5 tentativas por e-mail a cada 15 minutos.
  - **Contra um ataque distribuído em muitos IPs:** a resistência cai de 5 para 50 tentativas por e-mail a cada 15 minutos. Esse é o custo aceito para tirar o bloqueio barato da dona.
