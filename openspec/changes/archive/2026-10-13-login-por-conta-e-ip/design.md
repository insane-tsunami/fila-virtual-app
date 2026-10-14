# Design

## Context

A motivação está em `proposal.md`, e o comportamento em `specs/rate-limiting/spec.md`. Esta seção resume o código atual do master que molda a solução.

- **Limite genérico.** `Services\LimiteDeTentativas` limita por `(escopo, chave)` com janela fixa. Ele grava uma linha por par em `limites_tentativas`, que nasce na primeira tentativa e vale até `expira_em`. As operações são `verificar`, `registrar`, `consumir` e `zerar`. A chave é `sha256` do valor, e qualquer falha do banco vira log (fail-open).
- **Contadores do login.** `SessaoService::entrar` usa dois escopos, `login_ip` (20) e `login_email` (5). Os dois são verificados antes do bcrypt e registrados no erro, e o acerto zera `login_email`. Um e-mail com formato inválido não tem chave estável e conta só no IP.
- **Configuração.** `daConfig` monta os escopos a partir de `config['rate_limit']`. `api/config.php` lê `RATE_LIMIT_*` com o padrão para valores inválidos. O IP chega pronto do controller (`Support\IpDoCliente`, com `TRUSTED_PROXIES` e IPv6 /64).
- **Testes.** `RateLimitApiTest` monta a aplicação com `rate_limit` explícito e troca o IP por `$this->enderecoRemoto`.
- **Referência.** A branch local `limite-de-tentativas` (commit `47c5cce`, não publicada) implementou a mesma ideia com outro mecanismo (uma linha por evento, janela deslizante). Ela serve de referência para cenários e testes, mas não de código.

## Goals / Non-Goals

**Goals:**
- Mudar só o login, reaproveitando o `LimiteDeTentativas` como está. Sem migração e sem dependência nova.
- Um `Retry-After` correto quando mais de um contador do login está estourado.

**Non-Goals:**
- **Trocar a janela fixa pela deslizante da branch `limite-de-tentativas`.** A troca exigiria tabela nova e reescrita de todos os limites. Não é preciso para o objetivo desta change.
- **Notificar a dona de tentativas, adicionar 2FA ou mostrar um captcha.** São defesas complementares e ficam para changes futuras.
- **Mudar o limite do "esqueci a senha" por e-mail.** Ele também é contado por e-mail. Mas cada pedido que passa envia o link para a caixa da dona, e o pedido novo invalida o anterior. Então o atacante não a impede de redefinir: o último link enviado continua válido por 1 hora.

## Decisions

### 1. Novo escopo `login_email_ip`, com o `login_email` virando o teto

- **O escopo novo.** `LimiteDeTentativas::LOGIN_EMAIL_IP = 'login_email_ip'`, com a chave `chave(<e-mail normalizado> . '|' . <ip>)`. O `|` não aparece num e-mail normalizado nem num IP, então dois pares diferentes nunca geram a mesma chave.
- **O escopo `login_email`.** Ele continua com o mesmo nome, a mesma chave (o hash do e-mail) e a mesma variável `RATE_LIMIT_LOGIN_EMAIL`. Só o padrão muda, de 5 para 50.
- **A variável nova.** `RATE_LIMIT_LOGIN_EMAIL_IP`, com padrão 5, entra como `rate_limit.login_email_ip`. `daConfig` usa a janela `janela_minutos` para ela.

**Alternativa: renomear o teto para `login_conta` / `RATE_LIMIT_LOGIN_CONTA`.** Rejeitada, porque o teto conta também e-mails sem conta (para o `429` não revelar contas), e "conta" descreveria mal o que ele mede. Manter o nome deixa a semântica da variável igual: erros por e-mail, de qualquer IP. Muda só o padrão, o que fica documentado como mudança de configuração.

**Alternativa: contar por id da conta em vez de por e-mail.** Rejeitada pelo mesmo motivo. E-mail desconhecido não tem id, e tratá-lo de outro jeito revelaria quais e-mails existem.

### 2. Fluxo do login

1. **Verificação antes do bcrypt.** Verifica os três contadores: `login_ip`, `login_email_ip` e `login_email`. Os dois de e-mail só entram quando o e-mail tem formato válido, como hoje.
2. **Erro.** Na senha errada ou no e-mail desconhecido, registra os três.
3. **Acerto.** Zera só o `login_email_ip`. O `login_email` e o `login_ip` ficam como estão.

**Por que o acerto não zera o teto.** Se zerasse, cada login da dona devolveria 50 tentativas a um ataque distribuído em andamento. Para a dona, o teto só importa durante um ataque desse porte, e a janela de 15 minutos o desfaz sozinha.

**Por que o acerto não zera o `login_ip`.** É o comportamento atual. Um atacante com uma conta própria poderia zerar o contador do próprio IP entre as rodadas de adivinhação.

### 3. `Retry-After` com o maior tempo entre os contadores estourados

Hoje o login chama `verificar` em sequência, e o primeiro contador estourado define o tempo. Com três contadores, isso pode anunciar um tempo menor que o real. O cliente voltaria antes da hora e receberia outro `429`.

Por isso entra `LimiteDeTentativas::verificarTodos(list<array{string, string}> $pares)`, que confere todos os pares e lança uma única `LimiteExcedidoException` com o maior `segundos`. Ele mantém o mesmo comportamento fail-open. O login passa a usá-lo, e o `verificar` simples continua para os outros fluxos.

**Alternativa: manter a sequência.** É mais simples, mas o `Retry-After` deixaria de cumprir "os segundos que faltam para o bloqueio acabar" quando dois limites se sobrepõem.

### 4. Testes

- **Cenários da spec em `RateLimitApiTest`.** Para os cenários com IPs diferentes, os testes trocam `$this->enderecoRemoto` entre as chamadas.
  - O teste atual `testBloqueioPorEmailVaiComOEmailMesmoTrocandoDeIp` inverte e vira "dona entra de outro IP".
  - Entram o teto somando IPs, o acerto que não zera o teto e o `Retry-After` com o maior tempo.
- **Cada login errado paga um bcrypt.** Por isso os testes do teto configuram um `login_email` baixo (por exemplo 10, como no cenário "Teto alterado"), em vez de fazer 50 chamadas.
- **O padrão 50.** É coberto em `ConfigTest` e por um teste de unidade de `daConfig` em `LimiteDeTentativasTest`, que não passa pelo bcrypt.
- **`verificarTodos`.** Ganha testes de unidade: nenhum estourado, um estourado, vários com o maior tempo, e falha do banco indo para o log.
- **Front.** Não muda. A mensagem e o formato do `429` são os mesmos.

## Risks / Trade-offs

- **[Um ataque distribuído adivinha a senha 10 vezes mais rápido (50 em vez de 5 por e-mail a cada 15 min)]** → O limite por e-mail+IP mantém a mesma resistência contra um IP só. O teto é configurável, e o README passa a explicar a troca. Defesas mais fortes (aviso à dona, 2FA) ficam registradas como próximos passos.
- **[Quem controla 10 ou mais IPs ainda tranca a dona por até 15 minutos]** → É aceito: o custo do ataque sobe de "um IP qualquer" para uma botnet. Quem precisar pode subir `RATE_LIMIT_LOGIN_EMAIL`.
- **[Mesmo IP público que o atacante (CGNAT de operadora, Wi-Fi compartilhado)]** → Nesse caso a dona fica sob o limite do par e-mail+IP do atacante. Ela sai do bloqueio trocando de rede (Wi-Fi ↔ dados móveis). Fica documentado.
- **[Atrás de um proxy sem `TRUSTED_PROXIES`, todos dividem o IP do proxy]** → O par e-mail+IP vira, na prática, um limite por e-mail de 5. Isso é o comportamento de hoje, não uma piora. O aviso que já existe no README é reforçado para citar este caso.
- **[A tabela ganha uma linha por par e-mail+IP]** → Num ataque com muitos IPs, são mais linhas, todas com vida de 15 minutos e apagadas pela limpeza que já roda a cada incremento.
- **[Uma instalação com `RATE_LIMIT_LOGIN_EMAIL=5` fixado mantém o bloqueio antigo]** → O `.env.example` e o README explicam o novo significado, e as notas do PR avisam.

## Migration Plan

1. **Deploy do código.** Não há migração.
   - Os contadores `login_email` existentes passam a ser comparados com 50, e um e-mail bloqueado pela regra antiga fica liberado na hora.
   - Os contadores `login_email_ip` começam vazios.
2. **Configuração fixada.** Se a instalação define `RATE_LIMIT_LOGIN_EMAIL`, é preciso revisar o valor.
3. **Rollback.** Reverter o código. As linhas `login_email_ip` deixam de ser lidas e somem quando vencem, na limpeza que roda a cada registro.
