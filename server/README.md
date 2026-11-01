# API da fila (server)

Backend PHP do ZeraFilas: a API que o cliente usa para entrar na fila virtual e que o dashboard do estabelecimento usa para ver e avançar a fila. Cada estabelecimento tem a sua **conta** (e-mail e senha) e só enxerga e mexe na própria loja; o cliente da fila não precisa de conta. Veja [Segurança e pendências](#segurança-e-pendências).

> Instruções para agentes de código deste repositório: [`../AGENTS.md`](../AGENTS.md).

## Requisitos

- PHP **8.3 ou mais novo**, com as extensões `pdo_sqlite` (testes) e `pdo_mysql` (produção)
- [Composer](https://getcomposer.org/) 2

## Instalação

```bash
cd server
composer install
```

## Configuração

A configuração vem de **variáveis de ambiente** (o PHP não lê o `.env` sozinho: exporte as variáveis no ambiente do servidor ou configure-as no painel da hospedagem). O modelo está em [`.env.example`](.env.example).

| Variável | Para quê | Padrão |
|---|---|---|
| `DB_DRIVER` | `mysql` (produção) ou `sqlite` (testes e desenvolvimento) | `mysql` |
| `DB_HOST`, `DB_USER`, `DB_PASS` | Acesso ao MySQL | `localhost`, vazio, vazio |
| `DB_NAME` | Nome do banco MySQL, ou caminho do arquivo SQLite | `zerafilas` |
| `CORS_ORIGIN` | Única origem permitida para CORS, por exemplo `https://zerafilas.exemplo.com`. Vazia = sem cabeçalhos CORS | vazia |
| `APP_URL` | Endereço público do front, sem barra final (ex.: `https://zerafilas.exemplo.com`): base do link de redefinição de senha enviado por e-mail. Sem ela o link não é montado e nenhum e-mail sai | vazia |
| `MAIL_DRIVER` | Como enviar e-mail: `desligado` (não envia, só avisa no log), `log` (grava a mensagem inteira no log, **só para desenvolvimento e testes**: o link de redefinição é um segredo) ou `smtp` (envio real). Outro valor é tratado como `desligado` e avisado no log | `desligado` |
| `MAIL_DSN` | Servidor SMTP do driver `smtp`, no formato do Symfony Mailer, ex.: `smtp://usuario:senha@smtp.exemplo.com:587`. É um segredo: nunca commite | vazia |
| `MAIL_FROM` | Remetente dos e-mails do driver `smtp`, ex.: `ZeraFilas <nao-responda@exemplo.com>` | vazia |
| `TRUSTED_PROXIES` | IPs ou faixas CIDR de proxies confiáveis, separados por vírgula (ex.: `10.0.0.1,172.16.0.0/12`). Só atrás deles a API lê o `X-Forwarded-For` para descobrir o IP do cliente (ver [Limite de tentativas](#limite-de-tentativas)) | vazia |
| `RATE_LIMIT_LOGIN_EMAIL_IP` | Erros de login de um e-mail vindos do mesmo IP que bloqueiam o e-mail só naquele IP | `5` |
| `RATE_LIMIT_LOGIN_EMAIL` | Teto: erros de login de um e-mail somando todos os IPs que bloqueiam o e-mail em qualquer IP. Até esta versão era o único limite por e-mail, com padrão `5`; quem fixou `5` mantém o bloqueio antigo, em que qualquer um tranca a dona fora do painel | `50` |
| `RATE_LIMIT_LOGIN_IP` | Erros de login por IP que bloqueiam o IP na janela | `20` |
| `RATE_LIMIT_SENHA_CONTA` | Senhas atuais erradas na troca de senha que bloqueiam a conta na janela | `5` |
| `RATE_LIMIT_JANELA_MIN` | Janela, em minutos, dos quatro limites acima | `15` |
| `RATE_LIMIT_CADASTRO_IP` | Tentativas de cadastro por IP na janela do cadastro | `5` |
| `RATE_LIMIT_CADASTRO_JANELA_MIN` | Janela, em minutos, do limite de cadastro | `60` |
| `RATE_LIMIT_ESQUECI_EMAIL` | Pedidos de redefinição de senha por e-mail na janela do "esqueci a senha" | `3` |
| `RATE_LIMIT_ESQUECI_IP` | Pedidos de redefinição de senha por IP na mesma janela | `10` |
| `RATE_LIMIT_ESQUECI_JANELA_MIN` | Janela, em minutos, dos dois limites de pedido acima | `60` |
| `RATE_LIMIT_CONFIRMACAO_CONTA` | Envios de confirmação de e-mail (reenviar e trocar o e-mail) por conta na janela da confirmação | `3` |
| `RATE_LIMIT_CONFIRMACAO_JANELA_MIN` | Janela, em minutos, do limite acima | `60` |
| `RATE_LIMIT_CONFIRMAR_IP` | Tokens inválidos na confirmação por IP que bloqueiam o IP na janela de `RATE_LIMIT_JANELA_MIN` | `20` |
| `RATE_LIMIT_REDEFINIR_IP` | Tokens inválidos na redefinição por IP que bloqueiam o IP na janela de `RATE_LIMIT_JANELA_MIN` | `20` |
| `RETENCAO_ENTRADA_ATIVA_HORAS` | Horas sem mudança de estado (entrada na fila ou início do atendimento) até o `php bin/limpar` encerrar como `cancelado` uma entrada ativa esquecida | `24` |
| `RETENCAO_TELEFONE_DIAS` | Dias desde o encerramento até o `php bin/limpar` apagar o telefone de uma entrada `finalizado` ou `cancelado` | `30` |
| `PRIVACIDADE_CONTROLADOR` | Nome que aparece como responsável pelo ZeraFilas no aviso de `/privacidade` | `ZeraFilas` |
| `PRIVACIDADE_EMAIL` | Contato de privacidade do aviso. O padrão usa o TLD reservado `.example` de propósito: troque antes de produção | `privacidade@zerafilas.example` |
| `PRIVACIDADE_ENCARREGADO` | Nome ou setor do encarregado de dados, opcional | vazio |

Os `RATE_LIMIT_*` e os `RETENCAO_*` aceitam só inteiros positivos; qualquer outro valor (vazio, `0`, negativo, texto) é ignorado e vale o padrão.

Nunca commite valores reais de banco. (A antiga variável `API_KEY` não existe mais: o dashboard passa a usar login por conta. Se ela ainda estiver no ambiente, é ignorada.)

## Banco e migrações

```bash
php bin/migrate        # ou: composer migrate
```

Cria as tabelas `estabelecimentos` (com o `endereco_publico` de cada loja, opcional), `entradas_fila`, `contas`, `sessoes`, `limites_tentativas` (contadores do limite de tentativas) e `redefinicoes_senha` (tokens do "esqueci a senha") e `confirmacoes_email` (tokens da confirmação do e-mail, mais a coluna `contas.email_confirmado_em`) e não insere nenhuma loja: em banco novo, cada loja nasce do cadastro. A `0009` remove a `veste-bem` herdada de bancos antigos, só se não tiver dona nem entradas na fila (se tiver entradas, mantém e avisa no log do PHP). A `0010` cria `contas.confirmar_ate` (o prazo para confirmar o e-mail) e dá às contas não confirmadas que já existiam **14 dias a partir da migração**; as confirmadas ficam sem prazo. A `0011` cria `estabelecimentos.avatar_cor` e `estabelecimentos.avatar_icone` (o avatar da loja), nulas em todas as lojas. A `0012` torna `entradas_fila.telefone` anulável (o `php bin/limpar` apaga o número das entradas encerradas antigas) e cria um índice em `finalizou_em`; as entradas que já existiam mantêm o telefone. É idempotente: rodar de novo responde "Nada a migrar". As migrações executadas ficam na tabela `migrations`.

> No MySQL, comandos de criação de tabela não são transacionais. Se uma migração falhar no meio, confira a tabela `migrations` e o que foi criado antes de rodar de novo.

## Retenção de dados

```bash
php bin/limpar        # ou: composer limpar
```

Comando idempotente, feito para rodar por cron (ver [Hospedagem](#hospedagem)). Cada execução:

- encerra como `cancelado` as entradas ativas paradas há mais de `RETENCAO_ENTRADA_ATIVA_HORAS` (padrão 24). O prazo conta da última mudança de estado (`entrou_em` para quem aguarda, `iniciou_em` para quem está em atendimento), loja a loja, com a mesma trava de `entrar` e `finalizar`; se a loja ficar sem ninguém em atendimento, a ativa mais antiga é promovida;
- apaga o telefone (fica `null`) das entradas `finalizado` ou `cancelado` encerradas há mais de `RETENCAO_TELEFONE_DIAS` (padrão 30). Código, status e datas continuam;
- remove sessões, tokens de redefinição de senha e de confirmação de e-mail vencidos e contadores de tentativas vencidos;
- imprime quantas linhas mudou e sai com `0`. Com migração pendente, sai com `1` e manda rodar `php bin/migrate` antes, sem mexer em nada.

**Nada é limpo sem o cron.** A aplicação não executa essas regras dentro das requisições: sem o agendamento, as entradas esquecidas ficam na fila e os telefones ficam no banco indefinidamente.

A entrada encerrada pela retenção (`cancelado`) não aparece no dashboard, que só lista as ativas. Na página pública o cliente vê "Entrada encerrada" e pode entrar na fila de novo com o mesmo telefone.

## Privacidade e LGPD

**Aviso.** A página pública `/privacidade` explica quem trata os dados, para quê, com que base legal, por quanto tempo e como exercer os direitos. O formulário da fila mostra um resumo com o prazo de apagamento e o cadastro da loja exige o aceite do aviso. O texto usa `PRIVACIDADE_CONTROLADOR`, `PRIVACIDADE_EMAIL` e `PRIVACIDADE_ENCARREGADO`; os padrões são **provisórios** (`ZeraFilas` e `privacidade@zerafilas.example`) e precisam ser trocados antes de produção. **O texto é um rascunho operacional: não substitui revisão por advogado nem nomeia formalmente um encarregado.**

**Papéis.** Na fila, a loja decide para que o telefone é usado e é a **controladora**; o ZeraFilas atua como **operador**. Os dados da conta da loja são controlados pelo ZeraFilas para prestar o serviço. Falta um contrato ou termo entre a plataforma e as lojas (art. 39) definindo isso por escrito.

**Base legal.** O telefone usa **legítimo interesse** (art. 7º, IX): é necessário para organizar a fila, o cliente espera esse uso ao entrar, e as salvaguardas são o mascaramento no dashboard, a saída imediata com apagamento do número, a retenção de 30 dias e a ausência de venda ou uso para outra finalidade. Esse é o teste de balanceamento resumido; a conta da loja usa execução de contrato (art. 7º, V).

**Direitos do titular.** O cliente elimina o telefone na hora com "Sair da fila" na página da loja, e a dona exclui a conta com tudo o que é dela em Configurações → "Excluir conta" (senha atual obrigatória). Para acesso, correção ou outros pedidos, o caminho é o contato do aviso (`PRIVACIDADE_EMAIL`). O número nunca é devolvido pela API.

**Pendências (revisão jurídica ou hospedagem, #27):** aprovar o texto e os dados reais do controlador/encarregado; contrato com as lojas; retenção de dados fiscais/contábeis; plano de incidente (art. 48); transferência internacional se a hospedagem for fora do Brasil; política de backups e logs (que ainda guardam os números por conta própria).

## Rodar localmente

Com SQLite não é preciso instalar um servidor de banco:

```bash
export DB_DRIVER=sqlite DB_NAME=/tmp/zerafilas.sqlite
touch "$DB_NAME"
php bin/migrate
php bin/seed-demo      # opcional: cria a loja de demonstração `veste-bem` (ou: composer seed-demo)
php -S localhost:8080 -t public
```

O servidor embutido do PHP serve só para desenvolvimento.

A `veste-bem` é só uma demonstração, sem dona e sem endereço público: a página pública dela funciona, mas ninguém a acessa pelo dashboard. `php bin/seed-demo` é idempotente e exige a migração feita antes. Com `DB_DRIVER=mysql` ele recusa rodar, a menos que se passe `--forcar` (`php bin/seed-demo --forcar`), para não criar a demonstração em produção por engano. Os exemplos de `curl` abaixo usam essa loja: rode o `seed-demo` antes deles.

## Testes

```bash
composer test
```

Roda em SQLite em memória, sem servidor de banco. Para rodar também contra MySQL, use um banco **descartável cujo nome contenha `test`** (por exemplo `zerafilas_test`):

```bash
DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_NAME=zerafilas_test DB_USER=... DB_PASS=... composer test
```

Os testes apagam todas as tabelas do banco a cada teste. Por isso eles **recusam** rodar em um MySQL cujo nome não contenha `test`, para nunca apagar um banco real por engano.

## API

Todas as respostas são JSON. Erros têm o formato `{"erro": "mensagem"}`. Exemplos contra `http://localhost:8080`:

**Cadastrar a conta e a loja** (público): cria a conta, a loja dela (o `slug` sai do nome: `Moda & Cia São João` vira `moda-cia-sao-joao`, com `-2`, `-3`... se já existir) e já abre a sessão. O CNPJ pode ter máscara e ser numérico ou **alfanumérico** (12 posições com letras ou números e 2 dígitos verificadores, formato da Receita desde julho de 2026, como `12.ABC.345/01DE-35`); letras minúsculas viram maiúsculas e o **dígito verificador é conferido** (CNPJ com dígito errado ou com os 14 caracteres iguais é `422`). A senha tem de 8 a 72 caracteres. E-mail ou CNPJ já usados são `409` (inclusive quando dois cadastros iguais chegam ao mesmo tempo), menos quando a conta que os ocupa foi abandonada sem confirmar o e-mail: aí ela é substituída (ver [Confirmação do e-mail](#api)).

```bash
curl -X POST http://localhost:8080/api/contas -H 'Content-Type: application/json' \
  -d '{"email": "contato@modaazul.com", "cnpj": "93.339.970/0001-05", "nome": "Moda Azul", "senha": "senha-segura-1"}'
# {"token":"3f9c...","expira_em":"2026-10-11 12:00:00","conta":{"email":"contato@modaazul.com","cnpj":"93339970000105","email_confirmado":false,"confirmar_ate":"2026-10-18 12:00:00"},"loja":{"nome":"Moda Azul","slug":"moda-azul","endereco_publico":null,"avatar":null}}
```

**Entrar** (público): devolve um `token` novo (cada aparelho tem o seu). O token vale 7 dias, é mostrado só nesta resposta (o servidor guarda apenas o hash) e é enviado nas rotas protegidas no cabeçalho `Authorization: Bearer <token>`. Credencial errada é sempre `401` com a mesma mensagem; depois de muitos erros, `429` (ver [Limite de tentativas](#limite-de-tentativas)).

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/sessoes -H 'Content-Type: application/json' \
  -d '{"email": "contato@modaazul.com", "senha": "senha-segura-1"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
```

**Esqueci a senha** (público, em duas chamadas). `POST /api/senha/esqueci` com `{"email"}` responde **sempre `202`** com a mesma mensagem, haja conta ou não (e-mail com formato inválido é `422`), e só manda o e-mail se a conta existe **e o e-mail dela está confirmado** (ver [Confirmação do e-mail](#api)). O e-mail traz o link `<APP_URL>/redefinir-senha#token=<token>`, com o token no **fragmento** (que não vai para logs de servidor nem no `Referer`). O token tem 256 bits, vale **1 hora**, é de **uso único** e o banco guarda só o hash; um pedido novo invalida o anterior. `POST /api/senha/redefinir` com `{"token", "nova_senha"}` troca a senha (8 a 72 caracteres), apaga os tokens da conta e **encerra todas as sessões**; não abre sessão (a pessoa entra de novo). Token ausente, errado, vencido ou já usado é `422` ("Link inválido ou expirado. Peça um novo."); senha inválida é `422` e o token continua valendo. Falha no envio **não aparece** na resposta (senão revelaria quais e-mails têm conta): vai para o log. Os limites estão em [Limite de tentativas](#limite-de-tentativas).

```bash
# Em desenvolvimento, com MAIL_DRIVER=log e APP_URL definidos, o link aparece no log do servidor.
curl -X POST http://localhost:8080/api/senha/esqueci -H 'Content-Type: application/json' -d '{"email": "contato@modaazul.com"}'
# {"mensagem":"Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha."}
curl -X POST http://localhost:8080/api/senha/redefinir -H 'Content-Type: application/json' \
  -d '{"token": "<token do link>", "nova_senha": "outra-senha-22"}'
# {"mensagem":"Senha alterada."}
```

> **E-mail:** sem configuração o envio fica **desligado** (`MAIL_DRIVER=desligado`): o pedido responde `202` mas nenhum e-mail sai e o log avisa. Para enviar de verdade use `MAIL_DRIVER=smtp` com `MAIL_DSN`, `MAIL_FROM` e `APP_URL`. O driver `log` grava o link (um segredo) no log: **só para desenvolvimento e testes**, nunca em produção.

**Confirmação do e-mail.** Toda conta nasce com o e-mail **não confirmado** (`email_confirmado: false` nas respostas de cadastro, login e `GET /api/conta`) e o cadastro envia o e-mail de confirmação sozinho (se o envio falhar, o cadastro continua valendo e o erro vai para o log). A conta não confirmada **entra e usa o dashboard normalmente**; a trava está no "esqueci a senha", que só envia o link para e-mail confirmado (para os demais a resposta é a mesma `202` e nada é enviado). O link é `<APP_URL>/confirmar-email#token=<token>` (token no fragmento), vale **24 horas**, é de uso único, o banco guarda só o hash e ele fica preso ao e-mail para o qual foi enviado (trocar o e-mail invalida o anterior). As contas que já existiam passam a **não confirmadas**.

**Prazo para confirmar e conta abandonada.** A conta não confirmada tem **14 dias** a partir do cadastro para confirmar o e-mail: o prazo vem em `confirmar_ate` (UTC, no formato de `expira_em`) nas respostas de cadastro, login e `GET /api/conta`, e é `null` depois de confirmado. Trocar o e-mail ou reenviar a confirmação não renova o prazo; as contas que já existiam ganharam 14 dias a partir da migração `0010`. Um cadastro novo com o mesmo e-mail ou CNPJ **substitui** a conta antiga só quando, ao mesmo tempo, o e-mail dela não está confirmado, o prazo passou e a loja não recebeu nenhuma entrada na fila nos últimos 14 dias. A substituição apaga a conta com a loja (o slug fica livre), as entradas da fila, as sessões e os links de confirmação e de redefinição, e o cadastro responde `201`. Se alguma conta que ocupa o e-mail ou o CNPJ não pode ser substituída, a resposta é o `409` de sempre e nada é apagado. **Conta confirmada nunca é substituída.** Não há limpeza periódica: a regra só roda quando alguém tenta se cadastrar.

- `POST /api/email/confirmar` com `{"token"}` (público, o link abre em qualquer aparelho): `200` com "E-mail confirmado."; token ausente, errado, vencido ou já usado é `422` ("Link inválido ou expirado. Peça um novo e-mail de confirmação.").
- `POST /api/conta/email/reenviar` (exige `Authorization`): `202` e um novo link (invalida o anterior); `409` se o e-mail já está confirmado.
- `PUT /api/conta/email` com `{"email", "senha"}` (exige `Authorization`): troca o e-mail **enquanto não confirmado**, é a saída para o e-mail digitado errado. Exige a senha atual (`422` se errada), recusa o e-mail de outra conta (`409`), o mesmo e-mail (`422`) e a conta já confirmada (`409`); responde `200` com a `conta` e manda a nova confirmação ao endereço novo.

```bash
curl -X POST http://localhost:8080/api/email/confirmar -H 'Content-Type: application/json' -d '{"token": "<token do link>"}'
# {"mensagem":"E-mail confirmado."}
curl -X POST http://localhost:8080/api/conta/email/reenviar -H "Authorization: Bearer $TOKEN"
# {"mensagem":"Enviamos um novo link de confirmação."}
curl -X PUT http://localhost:8080/api/conta/email -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"email": "certo@modaazul.com", "senha": "senha-segura-1"}'
```

**Dados da conta, trocar a senha, sair e excluir a conta** (exigem `Authorization`). Trocar a senha encerra as outras sessões da conta; senha atual errada é `422` (e não `401`). Excluir a conta apaga a conta, a loja, as entradas da fila, as sessões e os tokens; os contadores por e-mail e por IP ficam (são hashes que vencem sozinhos).

```bash
curl http://localhost:8080/api/conta -H "Authorization: Bearer $TOKEN"
curl -X PUT http://localhost:8080/api/conta/senha -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"senha_atual": "senha-segura-1", "nova_senha": "outra-senha-22"}'
curl -X DELETE http://localhost:8080/api/conta -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"senha": "senha-segura-1"}'
# {"mensagem":"Conta excluída."}
curl -X DELETE http://localhost:8080/api/sessao -H "Authorization: Bearer $TOKEN"   # 204
```

**Dados da loja** (público): nome, slug, o endereço público configurado (`null` se ainda não houver) e o `avatar` (`null` ou `{"cor", "icone"}`, ver abaixo). O front usa isso para montar o link do QRCode. A loja tem esse mesmo formato em todas as respostas que a trazem (cadastro, login, `GET /api/conta`, endereço e avatar).

```bash
curl http://localhost:8080/api/filas/veste-bem
# {"nome":"Veste Bem","slug":"veste-bem","endereco_publico":null,"avatar":null}
```

**Definir o endereço público da loja** (dashboard, exige `Authorization` e que a loja seja da sua conta). Aceita só a **origem** do site (`http`/`https`, host, porta opcional; sem usuário, caminho, query ou fragmento). É normalizado para minúsculas e sem barra final. `null` ou `""` apaga o endereço; o campo ausente é `422`.

```bash
curl -X PUT http://localhost:8080/api/filas/moda-azul/endereco \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"endereco_publico": "https://Loja.Exemplo.com/"}'
# {"nome":"Moda Azul","slug":"moda-azul","endereco_publico":"https://loja.exemplo.com","avatar":null}
```

**Definir o avatar da loja** (dashboard, exige `Authorization` e que a loja seja da sua conta). O avatar é uma cor e um ícone de listas fixas, que o dashboard mostra na barra lateral no lugar da inicial da loja. Cores: `roxo`, `azul`, `verde`, `laranja`, `vermelho`, `cinza`. Ícones: `loja`, `mercado`, `restaurante`, `cafe`, `farmacia`, `beleza`. `{"cor": null, "icone": null}` apaga o avatar (volta à inicial). Qualquer outro corpo (valor fora da lista ou com maiúsculas, só um dos campos, um `null` e o outro não) é `422` com "Escolha uma cor e um ícone da lista." e o avatar anterior fica. Não há envio de imagem.

```bash
curl -X PUT http://localhost:8080/api/filas/moda-azul/avatar \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"cor": "verde", "icone": "restaurante"}'
# {"nome":"Moda Azul","slug":"moda-azul","endereco_publico":null,"avatar":{"cor":"verde","icone":"restaurante"}}
```

**Entrar na fila** (público): `201` com a nova entrada, ou `200` com a entrada existente se o telefone já estiver na fila.

```bash
curl -X POST http://localhost:8080/api/filas/veste-bem/entradas \
  -H 'Content-Type: application/json' \
  -d '{"telefone": "(11) 97177-8203"}'
# {"codigo":"9f3c...","posicao":1,"status":"em_atendimento"}
```

**Consultar a própria posição** (público), com o `codigo` recebido ao entrar. `posicao` é `null` depois de finalizado.

```bash
curl http://localhost:8080/api/filas/veste-bem/entradas/9f3c...
# {"codigo":"9f3c...","posicao":1,"status":"em_atendimento"}
```

**Sair da fila** (público), com o `codigo` da própria entrada. Encerra a entrada como `cancelado`, apaga o telefone na hora e promove a próxima quando ninguém fica em atendimento. Chamar de novo devolve `200` com o estado atual; código inexistente é `404`.

```bash
curl -X DELETE http://localhost:8080/api/filas/veste-bem/entradas/9f3c...
# {"codigo":"9f3c...","posicao":null,"status":"cancelado"}
```

**Aviso de privacidade** (público): a configuração do aviso e os prazos que a retenção aplica, usados pela página `/privacidade` e pelo resumo na fila.

```bash
curl http://localhost:8080/api/privacidade
# {"controlador":"ZeraFilas","email":"privacidade@zerafilas.example","encarregado":"","base_legal":"legítimo interesse (art. 7º, IX, da LGPD)","retencao_entrada_ativa_horas":24,"retencao_telefone_dias":30}
```

**Listar a fila** (dashboard, exige `Authorization` e a loja da sua conta). O telefone vem mascarado.

```bash
curl http://localhost:8080/api/filas/moda-azul/entradas -H "Authorization: Bearer $TOKEN"
# [{"codigo":"9f3c...","posicao":1,"status":"em_atendimento","telefone":"*****8203"}]
```

**Finalizar o atendimento** (dashboard, exige `Authorization` e a loja da sua conta). Só finaliza a entrada que está em atendimento (senão `409`) e promove a próxima.

```bash
curl -X POST http://localhost:8080/api/filas/moda-azul/entradas/9f3c.../finalizar \
  -H "Authorization: Bearer $TOKEN"
# {"finalizada":{"codigo":"9f3c...","posicao":null,"status":"finalizado"},"atual":null}
```

| Status | Quando |
|---|---|
| `400` | Corpo que não é JSON válido |
| `401` | Sem `Authorization` válido (ausente, desconhecido, vencido ou encerrado) ou e-mail/senha incorretos no login |
| `403` | Loja de outra conta, ou loja sem dono |
| `404` | Rota, estabelecimento ou entrada inexistente |
| `405` | Método não permitido |
| `409` | Finalizar uma entrada que não está em atendimento, ou e-mail/CNPJ já cadastrado |
| `422` | Telefone inválido (de 10 a 13 dígitos; o DDI `55` é acrescentado se faltar) endereço público inválido/ausente, dado inválido no cadastro ou senha atual incorreta |
| `500` | Erro inesperado (mensagem genérica; os detalhes vão para o `error_log`) |

O comportamento completo está especificado em [`openspec/specs/`](../openspec/specs) (`queue-intake`, `queue-management`, `store-address`, `establishment-account`, `account-session` e `api-platform`).

## Limite de tentativas

Oito rotas públicas ou de conta têm limite, para dificultar a adivinhação de senha, a criação de contas em massa e a sobrecarga por logins repetidos. Os contadores ficam na tabela `limites_tentativas` (janela fixa; o e-mail e o IP só existem lá como hash) e os números são configuráveis (ver [Configuração](#configuração)):

| Rota | Quem é limitado | Padrão | O que conta |
|---|---|---|---|
| `POST /api/sessoes` (login) | cada e-mail, em cada IP | 5 em 15 min | só os erros (senha errada ou e-mail sem conta); bloqueia o e-mail só naquele IP |
| `POST /api/sessoes` (login) | cada e-mail, somando todos os IPs | 50 em 15 min | só os erros; bloqueia o e-mail em qualquer IP |
| `POST /api/sessoes` (login) | cada IP | 20 em 15 min | só os erros |
| `POST /api/contas` (cadastro) | cada IP | 5 em 60 min | toda chamada, inclusive as recusadas (`409`, `422`) |
| `PUT /api/conta/senha` (trocar a senha) | cada conta | 5 em 15 min | só as senhas atuais erradas |
| `POST /api/senha/esqueci` (pedir o link) | cada e-mail | 3 em 60 min | todo pedido válido, inclusive e-mail sem conta |
| `POST /api/senha/esqueci` (pedir o link) | cada IP | 10 em 60 min | todo pedido válido |
| `POST /api/senha/redefinir` (concluir) | cada IP | 20 em 15 min | só os tokens inválidos |
| `POST /api/conta/email/reenviar` e `PUT /api/conta/email` | cada conta | 3 em 60 min | todo envio de confirmação |
| `PUT /api/conta/email` (senha atual errada) | cada conta | 5 em 15 min | só as senhas erradas, no mesmo limite da troca de senha |
| `POST /api/email/confirmar` | cada IP | 20 em 15 min | só os tokens inválidos |

- Esgotado o limite, a rota responde **`429`** com `{"erro": "Muitas tentativas. Tente de novo em N minutos."}` e o cabeçalho `Retry-After` (segundos), **sem verificar a senha**, mesmo que ela esteja certa. O bloqueio vale até a janela vencer; com mais de um limite esgotado, o `Retry-After` é o maior tempo entre eles.
- **Quem erra a senha de outra pessoa não a tranca fora do painel.** Os 5 erros bloqueiam aquele e-mail só no IP de onde vieram; a dona continua entrando de qualquer outro IP. O teto de 50 erros somando todos os IPs só é atingido por um ataque espalhado por muitos IPs, e aí o e-mail fica bloqueado em qualquer IP até a janela vencer.
- Um login correto zera o contador daquele e-mail naquele IP, mas não o teto do e-mail (zerá-lo devolveria tentativas a um ataque espalhado a cada login da dona) nem o do IP. Uma troca de senha bem-sucedida zera o contador da conta. Dados incompletos (`422`) no login não contam.
- E-mail sem conta conta como erro nos dois limites por e-mail, então o `429` não revela quais e-mails existem.
- **O que se troca:** contra um único IP, a senha resiste como antes (5 tentativas por e-mail a cada 15 minutos); contra um ataque espalhado por muitos IPs, passa a aguentar 50 a cada 15 minutos. Use uma senha forte; quem preferir o contrário pode baixar `RATE_LIMIT_LOGIN_EMAIL`, sabendo que um valor baixo devolve a qualquer um o poder de trancar a dona fora.
- **Mesmo IP que o atacante** (Wi-Fi compartilhado, IP da operadora de celular): a dona cai no mesmo bloqueio do par e-mail+IP. Trocar de rede (Wi-Fi ↔ dados móveis) resolve.
- O **IP** é o da conexão. Só se ele estiver em `TRUSTED_PROXIES` a API usa o `X-Forwarded-For` (o IP mais à direita que não seja de proxy confiável); endereços IPv6 contam pelo bloco /64.
- Se o banco dos contadores falhar, a chamada **segue sem limite** e o erro vai para o log (o limite é uma defesa extra e não pode derrubar o login).

```bash
for i in 1 2 3 4 5 6; do
  curl -s -o /dev/null -w '%{http_code} ' -X POST http://localhost:8080/api/sessoes \
    -H 'Content-Type: application/json' -d '{"email": "contato@modaazul.com", "senha": "errada-errada"}'
done
# 401 401 401 401 401 429
```

## Estrutura

```
server/
  public/            front controller (index.php) e .htaccess para Apache
  api/config.php     lê as variáveis de ambiente e devolve um array
  app/controllers/   ClienteController, DashboardController, LojaController, ContaController, SessaoController, RecuperacaoSenhaController, ConfirmacaoEmailController e PrivacidadeController
  app/services/      FilaService, LojaService (dados, endereço e avatar da loja), ContaService (cadastro, troca de senha), ExclusaoDeConta (apaga a conta com a loja e tudo o que é dela), SessaoService (login, token), LimiteDeTentativas (contadores do limite), RecuperacaoSenhaService (esqueci a senha), ConfirmacaoEmailService (confirmar, reenviar e trocar o e-mail), RetencaoDeDados (limpeza das entradas, telefones, sessões, tokens e contadores vencidos), Mailer com os drivers MailerDesligado, MailerLog e MailerSmtp, e exceções
  app/models/        Estabelecimento, EntradaFila, Conta e Sessao
  app/middleware/    Cors, Autenticacao (token) e LojaDaConta (posse da loja)
  app/support/       Aplicacao (rotas e erros), Database, Migrator, Telefone, EnderecoPublico, Email, Cnpj, Slug, Senha, IpDoCliente, LojaDeDemonstracao, Avatar (cores e ícones aceitos), Json
  database/migrations/
  bin/migrate        runner de migrações
  bin/seed-demo      cria a loja de demonstração `veste-bem` (desenvolvimento)
  bin/limpar         aplica a retenção de dados (rodar por cron)
  tests/
```

## Hospedagem

A hospedagem ainda não foi definida. O código não depende de nenhum provedor: serve qualquer hospedagem com PHP 8.3+ e MySQL, apontando a raiz do site para `public/`, definindo as variáveis de ambiente e rodando `php bin/migrate` a cada deploy. Em Apache, o `public/.htaccess` já direciona tudo ao front controller.

**Agende o `php bin/limpar` (retenção de dados).** A aplicação não limpa sozinha: sem o cron, as entradas esquecidas ficam na fila e os telefones das entradas encerradas ficam no banco além do prazo. De hora em hora basta (mais frequente não muda o resultado; mais raro só atrasa o encerramento e o apagamento). Exemplo de crontab, com o caminho do `server/`:

```cron
0 * * * * cd /caminho/para/server && php bin/limpar >/dev/null
```

**Atrás de proxy ou CDN, configure `TRUSTED_PROXIES`.** Sem isso a API usa o endereço da conexão, que atrás de um proxy é o do próprio proxy: todos os usuários parecem o mesmo IP, o limite de tentativas por IP passa a valer para o site inteiro e o limite de login por e-mail+IP vira um limite por e-mail de 5 erros, com que qualquer um volta a trancar a dona fora do painel. Liste só os proxies que você controla: um endereço na lista é confiado para dizer quem é o cliente. Sem proxy, deixe vazia.

## Segurança e pendências

- **O limite de tentativas cobre só login, cadastro e troca de senha** (ver [Limite de tentativas](#limite-de-tentativas)): não há limite de requisições em geral nem nas rotas públicas da fila. Atrás de proxy, configure `TRUSTED_PROXIES`, senão o limite por IP vale para todos juntos.
- **Cadastro alheio bloqueia por no máximo 14 dias, e a confirmação de e-mail não prova o CNPJ:** quem cadastra o e-mail ou o CNPJ de outra pessoa ocupa o endereço, e o dono de verdade leva `409` até o prazo de confirmação passar; depois disso, se a loja ficou 14 dias sem clientes na fila, o cadastro do dono substitui a conta abandonada (ver [Confirmação do e-mail](#api)). Continua fora: quem confirma um **e-mail próprio** ocupa um CNPJ alheio para sempre (provar o CNPJ depende de consulta à Receita), e quem mantém entradas falsas na própria fila adia a substituição. Uma conta não confirmada com a senha esquecida não recupera por e-mail; enquanto a sessão (7 dias) valer, a pessoa troca o e-mail no Perfil com a senha atual. Além disso, o pedido de redefinição leva um pouco mais de tempo para e-mails com conta (o envio é síncrono), o que dá a um atacante paciente um jeito lento de descobrir contas; o limite por IP e por e-mail reduz isso.
- **O token é um segredo:** quem o tiver age como a dona da loja por até 7 dias (ou até sair ou trocar a senha). O front o guarda em `sessionStorage`, legível por qualquer script da página.
- **A loja `veste-bem` é só uma demonstração:** sem dona, serve a página pública da fila mas ninguém a acessa pelo dashboard. Ela não existe em banco novo; só `php bin/seed-demo` a cria (em MySQL exige `--forcar`). A migração `0009` a remove de bancos antigos se estiver sem dona e sem entradas.
- **O dígito verificador só pega erro de digitação:** não prova que o CNPJ é de quem se cadastra (isso depende de consulta à Receita ou de confirmação de e-mail, que não existem).
- **Qualquer pessoa pode entrar na fila com qualquer telefone:** não há limite de requisições nem confirmação de que o número é de quem o digitou. Isso precisa ser resolvido antes de existirem notificações.
- **O telefone é dado pessoal (LGPD).** O banco guarda o número completo e a API nunca o devolve inteiro ao dashboard. A retenção (`php bin/limpar`) apaga o telefone das entradas `finalizado` ou `cancelado` 30 dias depois do encerramento (`RETENCAO_TELEFONE_DIAS`) e encerra as ativas paradas há 24 h (`RETENCAO_ENTRADA_ATIVA_HORAS`); isso depende de a hospedagem agendar o comando (ver [Hospedagem](#hospedagem)). O cliente sai da fila e apaga o número na hora, e a dona exclui a conta no Perfil. Os backups e os logs do servidor ainda guardam os números por conta própria, e o texto do aviso ainda precisa de revisão jurídica (ver [Privacidade e LGPD](#privacidade-e-lgpd)).
