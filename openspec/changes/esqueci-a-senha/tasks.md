# Tasks

## 1. Envio de e-mail plugável

- [x] 1.1 Adicionar `symfony/mailer` (`composer require` em `server/`) e verificar que `composer validate --strict` e `composer install` passam e o `composer.lock` está em dia
- [x] 1.2 Criar a interface `Mailer`, os drivers `MailerDesligado`, `MailerLog` e `MailerSmtp` (exceção própria que não repassa a mensagem original) e a fábrica `Mailer::daConfig` (valor desconhecido vira `desligado` e é logado); verificar com testes unitários: desligado sem corpo no log, log com mensagem inteira, valor desconhecido, destinatário ou assunto com quebra de linha recusados, `smtp` sem `MAIL_DSN`/`MAIL_FROM` falha sem vazar nada e `smtp` com transporte falso entrega ao servidor com o remetente certo
- [x] 1.3 Ler `mail` (`driver`, `dsn`, `from`) e `app_url` em `api/config.php` e testar a leitura (padrões e ambiente) no `ConfigTest`, sem tocar o ambiente real
- [x] 1.4 Documentar `MAIL_DRIVER`, `MAIL_DSN`, `MAIL_FROM` e `APP_URL` em `server/.env.example` e na tabela de `server/README.md`, com o aviso de que `log` é só para desenvolvimento e testes, e conferir que os nomes batem com `api/config.php`

## 2. Token e serviço de redefinição

- [x] 2.1 Criar a migração `0007` com `redefinicoes_senha` (`conta_id` com chave estrangeira, `token_hash` único, `criado_em`, `expira_em`) e estender o `MigrationTest`; verificar que passa no SQLite e que a migração roda no MySQL do CI
- [x] 2.2 Acrescentar `SessaoService::encerrarTodas(int $contaId)` e implementar `RecuperacaoSenhaService` (`pedir` e `redefinir` como no design, link `APP_URL/redefinir-senha#token=`, texto do e-mail, falha do envio capturada e logada, token novo apaga os anteriores e os vencidos); verificar com `RecuperacaoSenhaServiceTest`: e-mail com e sem conta (mesma resposta, e-mail só quando há conta), e-mail com maiúsculas, formato inválido, token só como hash, pedido novo invalida o anterior, token vencido, uso único, senha inválida sem queimar o token, todas as sessões encerradas, sem `APP_URL` não envia, falha do `Mailer` não vaza

## 3. API e limites

- [x] 3.1 Acrescentar os escopos `esqueci_email`, `esqueci_ip` e `redefinir_ip` em `LimiteDeTentativas` (`daConfig` inclusive) e `rate_limit` em `api/config.php` (`esqueci_email`, `esqueci_ip`, `esqueci_janela_minutos`, `redefinir_ip`, inválido cai no padrão); verificar com testes do limitador e do `ConfigTest`
- [x] 3.2 Criar o `RecuperacaoSenhaController` e as rotas públicas `POST /api/senha/esqueci` (`202`) e `POST /api/senha/redefinir` (`200`), ligadas em `Aplicacao` com o `Mailer` da configuração, e documentar as rotas, o `429` e o fluxo no `server/README.md`; verificar com `PasswordRecoveryApiTest` os cenários da spec: respostas iguais com e sem conta, `422`, redefinição completa (login antigo recusado, novo aceito, sessões `401`, sem sessão na resposta), uso único, token inválido, e-mail do `MailerLog`/falso com o link no fragmento e o prazo
- [x] 3.3 Aplicar e testar os limites nas rotas novas em `PasswordRecoveryApiTest` ou `RateLimitApiTest`: quarto pedido do mesmo e-mail e 11º do IP dão `429` sem enviar e-mail (e-mail sem conta igual), 21º token inválido bloqueia mesmo com token válido, senha inválida não conta, outro IP livre e limite configurado; confirmar com mutações (sem consumir o limite do pedido, sem queimar os tokens ao redefinir, sem encerrar todas as sessões) que a suíte falha

## 4. Front

- [ ] 4.1 Criar `pedirRedefinicao` e `redefinirSenha` em `src/api.js` e testar em `api.test.js` (corpo, rota e erro `422`/`429` como `ApiError`)
- [ ] 4.2 Criar as telas `EsqueciSenha` e `RedefinirSenha` (token do fragmento, endereço limpo, link sem token, senhas diferentes, `422` e `429` na tela, falha de rede, sucesso para `/login` com aviso), as rotas anônimas em `App.js`, o link "Esqueci a senha" e o aviso (que não reaparece ao recarregar) no `Login`; verificar com testes das duas telas e do `Login` e com `yarn test`, lint e `CI=true yarn build`

## 5. Documentação e verificação final

- [ ] 5.1 Atualizar `README.md` (fluxo "Esqueci a senha", `APP_URL` e `MAIL_*`, sem prometer envio sem configurar) e `openspec/config.yaml` (rotas novas, tabela `redefinicoes_senha`, `MAIL_DRIVER`, tirar "esqueci a senha" das pendências); conferir que os comandos documentados rodam como escritos
- [ ] 5.2 Integração: subir a API com SQLite e `MAIL_DRIVER=log`, cadastrar uma conta, pedir a redefinição por `curl`, tirar o token do log, redefinir, conferir que o login antigo falha e o novo funciona e que o mesmo token dá `422`; rodar `openspec validate esqueci-a-senha --strict` e a suíte completa da API e do front
