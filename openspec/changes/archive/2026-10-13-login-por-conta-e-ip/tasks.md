# Tasks

## 1. Contadores e configuração

- [x] 1.1 Acrescentar o escopo `LOGIN_EMAIL_IP` em `LimiteDeTentativas` e em `daConfig` (com a janela `janela_minutos`). Ler `RATE_LIMIT_LOGIN_EMAIL_IP` (padrão 5) em `api/config.php` e subir o padrão de `RATE_LIMIT_LOGIN_EMAIL` para 50. Verificar com `ConfigTest` (padrões, leitura do ambiente, valor inválido usando o padrão) e com um teste de `daConfig` em `LimiteDeTentativasTest` (o 50º erro ainda passa, o seguinte bloqueia, sem bcrypt).
- [x] 1.2 Implementar `LimiteDeTentativas::verificarTodos(list<array{string, string}>)`. Ele lança uma única `LimiteExcedidoException` com o maior `segundos` entre os pares estourados e segue fail-open. Verificar com testes de unidade: nenhum par estourado, um estourado, dois com tempos diferentes (vence o maior) e banco quebrado indo para o log sem lançar.
- [x] 1.3 Documentar a variável nova e o novo significado e padrão de `RATE_LIMIT_LOGIN_EMAIL` em `server/.env.example` e na tabela de variáveis do `server/README.md`. Verificar que os nomes e os padrões batem com `api/config.php`.

## 2. Login

- [x] 2.1 Mudar `SessaoService::entrar`:
  - verificar `login_ip`, `login_email_ip` e `login_email` com `verificarTodos` antes do bcrypt;
  - registrar os três no erro;
  - no acerto, zerar só `login_email_ip`;
  - com e-mail de formato inválido, seguir contando só no IP.

  Verificar com `RateLimitApiTest` (cenários abaixo; os de vários IPs trocam `$this->enderecoRemoto`, e os do teto usam um `login_email` baixo):
  - quinto erro no mesmo e-mail e IP bloqueia mesmo com a senha certa;
  - a dona entra de outro IP (o atual `testBloqueioPorEmailVaiComOEmailMesmoTrocandoDeIp` invertido);
  - outro e-mail no mesmo IP entra;
  - o teto somando IPs bloqueia de um IP novo;
  - o login correto zera só o par;
  - o login correto não zera o teto;
  - e-mail desconhecido bloqueia no mesmo ponto, no par e no teto;
  - o `Retry-After` vem com o maior tempo quando o par e o teto estão estourados;
  - limite do par configurado em 3;
  - janela vencida;
  - o banco não guarda o e-mail nem o IP em claro.
- [x] 2.2 Rodar a suíte inteira da API no SQLite (`composer test`) e no MySQL 8.4 descartável (banco com `test` no nome). Verificar que todos os testes passam nos dois, inclusive os de troca de senha, esqueci a senha e confirmação, que não mudam.

## 3. Documentação e verificação final

- [x] 3.1 Atualizar a seção do limite de tentativas no `server/README.md` com:
  - a tabela das rotas, com o login por e-mail+IP, por e-mail somando IPs e por IP;
  - o login certo, que zera só o par;
  - a troca de resistência contra ataque distribuído;
  - o caso de IP compartilhado com o atacante;
  - o reforço do aviso de `TRUSTED_PROXIES`.

  Atualizar também o `README.md` da raiz e o trecho do limite de tentativas em `openspec/config.yaml`. Verificar que os números citados batem com os padrões de `api/config.php`.
- [x] 3.2 Integração com a API no SQLite em `127.0.0.1:8080`. Simular por `curl`, variando o `REMOTE_ADDR` com `TRUSTED_PROXIES=127.0.0.1` e `X-Forwarded-For`:
  - 5 erros de um IP para um e-mail e o `429` com `Retry-After` nesse IP;
  - o `200` com a senha certa de outro IP;
  - o teto com `RATE_LIMIT_LOGIN_EMAIL=10` bloqueando de um IP novo.

  Rodar `openspec validate login-por-conta-e-ip --strict`.

## Workflow follow-up

- Abrir o PR para `master` quando o usuário pedir. Nas notas, avisar da mudança do padrão de `RATE_LIMIT_LOGIN_EMAIL`.
- Arquivar a change depois do merge e conferir a spec `rate-limiting` resultante com `openspec validate rate-limiting --strict`.
