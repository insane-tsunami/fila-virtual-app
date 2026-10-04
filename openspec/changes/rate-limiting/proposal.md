# Proposal

## Why

Hoje nada limita as tentativas em `POST /api/sessoes`, `POST /api/contas` e `PUT /api/conta/senha`: alguém pode adivinhar senhas, criar contas em massa e descobrir quais e-mails e CNPJs já existem pelo `409`. Cada tentativa também gasta CPU com bcrypt, então um volume alto de logins errados derruba a API. O login e o cadastro já estão no ar, e o limite de tentativas é a pendência de segurança que sobrou.

## What Changes

- Login (`POST /api/sessoes`): bloqueia por 15 minutos depois de 5 tentativas erradas para o mesmo e-mail ou de 20 tentativas erradas vindas do mesmo IP. Um login correto zera o contador do e-mail. E-mail desconhecido conta como erro, para o bloqueio não revelar quais e-mails existem.
- Cadastro (`POST /api/contas`): no máximo 5 tentativas por hora por IP, contando todas (inclusive as recusadas por `409` ou `422`).
- Troca de senha (`PUT /api/conta/senha`): bloqueia por 15 minutos depois de 5 senhas atuais erradas na mesma conta; trocar com sucesso zera o contador.
- Chamada bloqueada responde `429` com `Retry-After` e uma mensagem em português com o tempo de espera, sem verificar a senha (não gasta bcrypt).
- A API descobre o IP do cliente pelo `REMOTE_ADDR`; só lê `X-Forwarded-For` quando o `REMOTE_ADDR` estiver em `TRUSTED_PROXIES` (nova variável de ambiente).
- Os limites e a janela ficam configuráveis por variáveis `RATE_LIMIT_*`, com os números acima como padrão.
- Se o armazenamento dos contadores falhar, a chamada segue sem limite e o erro vai para o log (fail-open).
- Nova tabela `limites_tentativas` (migração `0006`) guarda os contadores; o e-mail é guardado só como hash.
- O front já mostra a mensagem de erro da API nas telas de login, cadastro e Perfil: o `429` aparece no mesmo lugar, sem mudança de interface (só testes que travam esse comportamento).

Fora do escopo: CAPTCHA, bloqueio permanente, aviso por e-mail ao dono, "esqueci a senha", limite na rota pública de entrada na fila.

## Capabilities

### New Capabilities
- `rate-limiting`: limite de tentativas do login, do cadastro e da troca de senha, resposta `429`, identificação do IP do cliente e configuração dos limites.

### Modified Capabilities
- `account-session`: o requisito de Login passa a prever o bloqueio por excesso de tentativas (`429`).

## Impact

- `server/`: nova migração `0006`, modelo e serviço de limite de tentativas, resolução do IP do cliente, `SessaoService`, `ContaService`, controllers, `Aplicacao` (tratamento do `429`), `Cors` (expõe `Retry-After`), `api/config.php`, `.env.example`, `server/README.md` e testes.
- `src/`: só testes de Login, Cadastro e Perfil para o `429`.
- Documentação: `README.md` e `openspec/config.yaml` (tirar "limite de tentativas" das pendências).
- Sem novas dependências.
