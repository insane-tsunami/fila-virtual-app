# Tasks

## 1. Contadores e configuração

- [x] 1.1 Criar a migração `0006` com a tabela `limites_tentativas` (chave `(escopo, chave)`, `contagem`, `expira_em`, índice em `expira_em`) e o modelo; estender o `MigrationTest` e verificar que passa no SQLite e a migração roda no MySQL do CI
- [x] 1.2 Implementar `LimiteDeTentativas` (`verificar`, `registrar`, `zerar`, janela fixa, incremento atômico, limpeza de linhas vencidas, falha aberta com log) e `LimiteExcedidoException`; verificar com testes unitários de limite, janela vencida, chaves independentes, zerar e falha do banco (conexão quebrada continua sem lançar)
- [x] 1.3 Ler `rate_limit` e `trusted_proxies` em `api/config.php` com os padrões 5/20/5/15 e 5/60 e valor inválido caindo no padrão; verificar com teste da leitura (sem tocar o ambiente real)
- [x] 1.4 Documentar as variáveis em `server/.env.example` e na tabela de `server/README.md` e confirmar que os nomes batem com `api/config.php`

## 2. IP do cliente

- [x] 2.1 Implementar `IpDoCliente` (`REMOTE_ADDR`, `TRUSTED_PROXIES` com IP e CIDR v4/v6, `X-Forwarded-For` da direita para a esquerda, cabeçalho inválido, IPv6 pelo /64, `desconhecido` sem endereço); verificar com testes dos cenários da spec, incluindo o cabeçalho forjado antes do proxy
- [x] 2.2 Documentar `TRUSTED_PROXIES` e o aviso sobre proxy na seção de hospedagem do `server/README.md`

## 3. Aplicar os limites

- [x] 3.1 Tratar `LimiteExcedidoException` em `Aplicacao` (`429`, mensagem em minutos, `Retry-After`) e expor `Retry-After` no `Cors`; verificar com teste de API do formato do `429` e do cabeçalho de CORS (e que sem `CORS_ORIGIN` não sai cabeçalho)
- [x] 3.2 Aplicar no login (`SessaoService::entrar` + `SessaoController`): `verificar` e-mail e IP, registrar erro nos dois, zerar o e-mail no sucesso, `422` e e-mail inválido sem contar no e-mail; verificar com `SessaoServiceTest` e `SessionApiTest` (5º erro, e-mail desconhecido, senha certa bloqueada, zerar, 21º do IP, janela vencida, e-mail só como hash na tabela)
- [x] 3.3 Aplicar no cadastro (`ContaService::cadastrar` + `ContaController`): contar toda chamada por IP e recusar a 6ª em 60 min; verificar com `AccountApiTest` (6º cadastro sem criar nada, `409`/`422` contam, outro IP livre)
- [x] 3.4 Aplicar na troca de senha (`ContaService::trocarSenha`, chave = id da conta): bloquear no 5º erro, zerar no sucesso; verificar com `AccountApiTest` (bloqueio, senha certa bloqueada sem alterar, zerar, outra conta livre)
- [x] 3.5 Ligar tudo em `Aplicacao` (limitador com o logger, config e IP do cliente nos controllers) e verificar que a suíte inteira da API passa e que o login com o banco dos contadores quebrado ainda responde `200`

## 4. Front, documentação e verificação final

- [x] 4.1 Acrescentar testes do `429` em Login, Cadastro e Perfil (mensagem exibida, continua na tela, sessão não expira) e verificar com `yarn test`, lint e `CI=true yarn build`
- [x] 4.2 Atualizar `README.md` (comportamento do limite e `TRUSTED_PROXIES`) e `openspec/config.yaml` (tirar "limite de tentativas" das pendências, citar a tabela e as rotas limitadas); verificar que os comandos documentados rodam como escritos
- [x] 4.3 Integração: subir a API com SQLite, simular com `curl` os 5 erros seguidos no login e conferir o `429`, o `Retry-After` e o login correto de outro e-mail; rodar `openspec validate rate-limiting --strict`
