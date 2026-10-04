# Design

## Context

O PHP não guarda estado entre requisições e o projeto não tem Redis nem APCu; o que há é o banco (MySQL em produção, SQLite nos testes) e um migrador próprio (`bin/migrate`). O login (`SessaoService::entrar`) já gasta sempre um bcrypt e já limpa sessões vencidas no próprio fluxo. Os erros viram HTTP em `Aplicacao::tratadorDeErros` por tipo de exceção. A hospedagem ainda não foi definida, então a API pode estar exposta direto ou atrás de proxy. O front mostra `e.message` da API nos três formulários, e só trata `401` como sessão expirada (no Perfil). Motivação e escopo: ver proposal.md.

## Goals / Non-Goals

**Goals:**
- Limitar login, cadastro e troca de senha com contadores no banco, sem dependência nova.
- Não gastar bcrypt em chamada bloqueada.
- Identificar o IP do cliente sem permitir que ele o forje.

**Non-Goals:**
- Janela deslizante, CAPTCHA, bloqueio permanente, notificação ao dono.
- Proteger a rota pública de entrada na fila.
- Garantia exata do limite sob concorrência alta (ver Riscos).

## Decisions

**Tabela `limites_tentativas` (migração `0006`).** Colunas `escopo` (string 32), `chave` (char 64), `contagem` (unsigned int), `expira_em` (datetime), com chave primária composta `(escopo, chave)` e índice em `expira_em`. Escopos: `login_email`, `login_ip`, `cadastro_ip`, `senha_conta`. A `chave` é o sha256 do e-mail normalizado, do IP (IPv6 reduzido ao /64) ou do id da conta, então o e-mail não fica em claro. Alternativa descartada: guardar cada tentativa (janela deslizante), que cresce a tabela e complica a limpeza sem ganho relevante aqui.

**Janela fixa.** A linha nasce na primeira tentativa com `expira_em = agora + janela`; enquanto não vence, só a contagem sobe. Vencida, a linha é descartada e recomeça. No pior caso o atacante faz 2x o limite na virada da janela; aceito.

**Serviço `LimiteDeTentativas`** com três operações sobre `(escopo, chave)`:
- `verificar`: lê sem alterar e lança `LimiteExcedidoException` (com os segundos restantes) se `contagem >= limite` e a linha não venceu.
- `registrar`: descarta a linha vencida da chave, `insertOrIgnore` com contagem 0 e `UPDATE contagem = contagem + 1`. O incremento é feito pelo banco e não por leitura e gravação em PHP. Também apaga as linhas vencidas de todas as chaves (mesmo padrão da limpeza de sessões).
- `zerar`: apaga a linha da chave.
O cadastro usa `registrar` e depois confere o limite (consome a tentativa antes de validar, para contar `409`/`422`). Login e troca de senha usam `verificar` antes e `registrar` só no erro.

**Onde aplicar: nos serviços, não em middleware.** O limite por e-mail precisa do corpo da requisição e do resultado da verificação da senha, que só os serviços têm. O IP chega por parâmetro (`entrar`, `cadastrar`), vindo do controller. O `LimiteDeTentativas` é injetado como opcional: sem ele (testes de serviço que não tratam do limite) nada é limitado, e `Aplicacao` sempre o injeta.

**Ordem no login:** `verificar` e-mail e IP, depois a senha; erro registra nos dois escopos, sucesso zera só o escopo do e-mail. E-mail com formato inválido não conta no escopo do e-mail (não há chave estável), só no do IP. Entrada incompleta (`422`) não registra nada.

**Falha aberta.** Cada operação do limite roda dentro de `try/catch (Throwable)`: em falha, loga com o logger da aplicação e age como se não houvesse limite. A `LimiteExcedidoException` é a única que passa. Alternativa descartada: falhar fechado, que derrubaria o login de todos por um problema na tabela de contadores.

**`429`.** Nova `LimiteExcedidoException` (segundos restantes); o `tratadorDeErros` mapeia para `429`, mensagem "Muitas tentativas. Tente de novo em N minuto(s)." (N = segundos arredondados para cima em minutos) e `Retry-After` em segundos, como já faz com `Allow` no `405`. `Cors` passa a enviar `Access-Control-Expose-Headers: Retry-After` junto com a origem.

**IP do cliente (`IpDoCliente`).** Lê `REMOTE_ADDR`; se ele estiver em `TRUSTED_PROXIES` (lista separada por vírgulas de IPs ou CIDRs, IPv4 e IPv6), percorre o `X-Forwarded-For` da direita para a esquerda e devolve o primeiro IP que não seja de proxy confiável; valor que não é IP, ou lista toda confiável, cai no `REMOTE_ADDR`. IPv6 é reduzido ao /64 para o atacante não trocar de endereço dentro do bloco dele. Sem `REMOTE_ADDR` (testes) usa a chave fixa `desconhecido`. Alternativas descartadas: confiar sempre no `X-Forwarded-For` (forjável) e usar só o `REMOTE_ADDR` (atrás de proxy todo mundo vira o mesmo IP).

**Configuração.** `api/config.php` ganha `rate_limit` (`login_email`, `login_ip`, `senha_conta` e `janela_minutos`, `cadastro_ip` e `cadastro_janela_minutos`) lidos de `RATE_LIMIT_LOGIN_EMAIL`, `RATE_LIMIT_LOGIN_IP`, `RATE_LIMIT_SENHA_CONTA`, `RATE_LIMIT_JANELA_MIN`, `RATE_LIMIT_CADASTRO_IP` e `RATE_LIMIT_CADASTRO_JANELA_MIN`, e `trusted_proxies`. Valor que não é inteiro positivo cai no padrão (5, 20, 5, 15, 5 e 60). `.env.example` e `server/README.md` documentam as variáveis e o aviso sobre proxy na seção de hospedagem.

**Front.** Sem mudança de código: `api.js` já lança `ApiError` com a mensagem da API, e Login, Cadastro e Perfil já a exibem; o Perfil só trata `401` como sessão expirada, então o `429` não derruba a sessão. Entram apenas testes que travam isso.

## Risks / Trade-offs

- [Concorrência passa um pouco do limite: a verificação e o registro não são atômicos entre si] → o incremento é atômico no banco, então o excesso é de poucas tentativas por rajada; aceitável para esses limites.
- [Bloqueio por e-mail vira arma contra a vítima] → janela curta (15 min), e o login correto zera o contador; quem erra 5 vezes o e-mail de outra pessoa a bloqueia por até 15 minutos. Aceito e documentado.
- [Vários usuários legítimos atrás do mesmo IP (wifi de loja) esgotam o limite por IP] → limite por IP de login é folgado (20); o de cadastro (5/h) é apertado de propósito e pode ser aumentado por variável.
- [`TRUSTED_PROXIES` mal configurada: vazia atrás de proxy faz todos parecerem um IP só; larga demais permite forjar] → o README avisa; o padrão seguro é não confiar em cabeçalho.
- [Falha aberta deixa o login sem proteção enquanto a tabela estiver com problema] → o erro vai para o log; escolha de disponibilidade.
- [Crescimento da tabela por ataques com muitos IPs e e-mails] → linhas vencem em no máximo 60 minutos e são apagadas no fluxo; cada chave ocupa uma linha só.

## Migration Plan

Rodar `php bin/migrate` no deploy cria a tabela; sem as variáveis novas valem os padrões, então não há passo obrigatório de configuração (quem está atrás de proxy deve definir `TRUSTED_PROXIES`). Rollback: reverter o código e, se quiser, apagar a tabela `limites_tentativas`; nada mais depende dela.
