# rate-limiting Specification

## Purpose
Limita as tentativas de login, de cadastro e de troca de senha para dificultar adivinhação de senha, criação de contas em massa e a sobrecarga da API por tentativas repetidas.

## Requirements

### Requirement: Limite de erros no login
`POST /api/sessoes` SHALL bloquear por 15 minutos o e-mail que acumular 5 tentativas erradas e o IP que acumular 20 tentativas erradas dentro de uma janela de 15 minutos. Enquanto bloqueado, o login MUST responder `429` sem verificar a senha, mesmo que ela esteja correta. Só as tentativas com a senha ou o e-mail errados contam; e-mail desconhecido conta como erro, e a chamada recusada por dados faltando (`422`) MUST NOT contar.

#### Scenario: Quinto erro seguido no mesmo e-mail
- **WHEN** o mesmo e-mail recebe 5 logins com a senha errada e depois uma sexta chamada, mesmo com a senha certa
- **THEN** a sexta resposta é `429`, nenhuma sessão é aberta e a senha não é verificada

#### Scenario: E-mail desconhecido também bloqueia
- **WHEN** um e-mail sem conta recebe 5 logins e depois uma sexta chamada
- **THEN** a sexta resposta é `429`, igual à de um e-mail que existe

#### Scenario: Login correto zera o contador do e-mail
- **WHEN** um e-mail acumula 4 erros e depois faz login com a senha certa
- **THEN** o login responde `200` e o e-mail volta a poder errar 4 vezes sem bloqueio

#### Scenario: Vários e-mails do mesmo IP
- **WHEN** um IP acumula 20 tentativas erradas (em e-mails diferentes) e faz uma 21ª chamada
- **THEN** a 21ª resposta é `429`

#### Scenario: Dados faltando não contam
- **WHEN** o login é chamado 6 vezes sem informar a senha
- **THEN** todas as respostas são `422` e nenhum bloqueio é aplicado

#### Scenario: Janela vencida
- **WHEN** passam 15 minutos desde a primeira tentativa errada do bloqueio
- **THEN** o e-mail e o IP voltam a poder tentar o login

### Requirement: Limite de cadastros por IP
`POST /api/contas` SHALL aceitar no máximo 5 chamadas por hora por IP, contando todas, inclusive as recusadas por dados inválidos (`422`) ou por e-mail ou CNPJ já usado (`409`). A sexta chamada na janela MUST ser recusada com `429` sem criar nada.

#### Scenario: Sexto cadastro na hora
- **WHEN** um IP faz 5 chamadas de cadastro e depois uma sexta, com dados válidos
- **THEN** a sexta resposta é `429` e nenhuma conta ou loja é criada

#### Scenario: Chamadas recusadas também contam
- **WHEN** um IP faz 5 chamadas de cadastro todas recusadas por `409` ou `422`
- **THEN** a sexta chamada responde `429`

#### Scenario: Outro IP não é afetado
- **WHEN** um IP está bloqueado para o cadastro e outro IP faz um cadastro válido
- **THEN** o cadastro do outro IP responde `201`

### Requirement: Limite de erros na troca de senha
`PUT /api/conta/senha` SHALL bloquear por 15 minutos a conta que acumular 5 senhas atuais erradas dentro de uma janela de 15 minutos. Enquanto bloqueada, a troca MUST responder `429` sem verificar a senha, mesmo que ela esteja correta. Uma troca feita com sucesso SHALL zerar o contador da conta.

#### Scenario: Quinto erro na senha atual
- **WHEN** a conta logada envia 5 senhas atuais erradas e depois uma sexta chamada com a senha atual certa
- **THEN** a sexta resposta é `429` e a senha não é alterada

#### Scenario: Troca bem-sucedida zera o contador
- **WHEN** a conta acumula 4 erros e depois troca a senha com sucesso
- **THEN** a troca responde `200` e a conta volta a poder errar 4 vezes sem bloqueio

#### Scenario: Outra conta não é afetada
- **WHEN** uma conta está bloqueada para a troca de senha e outra conta troca a própria senha
- **THEN** a troca da outra conta responde `200`

### Requirement: Resposta de limite excedido
Uma chamada bloqueada SHALL responder `429` com o corpo `{"erro": "<mensagem>"}`, o cabeçalho `Retry-After` com os segundos que faltam para o bloqueio acabar e uma mensagem em português que informe esse tempo em minutos. Com `CORS_ORIGIN` configurada, a resposta SHALL incluir `Access-Control-Expose-Headers: Retry-After`. O limite MUST NOT revelar se um e-mail tem conta: o bloqueio por e-mail SHALL ser igual para e-mails com e sem conta.

#### Scenario: Formato do 429
- **WHEN** uma chamada é bloqueada por excesso de tentativas
- **THEN** a resposta é `429` com `erro` em português citando o tempo de espera em minutos e um `Retry-After` com um número inteiro positivo de segundos, no máximo a janela do limite

#### Scenario: Retry-After exposto ao navegador
- **WHEN** `CORS_ORIGIN` está configurada e uma chamada é bloqueada
- **THEN** a resposta traz `Access-Control-Expose-Headers` com `Retry-After`

#### Scenario: Bloqueio não revela e-mails
- **WHEN** um e-mail com conta e um e-mail sem conta recebem o mesmo número de logins errados
- **THEN** os dois passam a receber `429` no mesmo ponto, com respostas equivalentes

### Requirement: IP do cliente
A API SHALL identificar o cliente pelo endereço da conexão. Só quando esse endereço estiver na lista `TRUSTED_PROXIES` (IPs ou faixas CIDR) a API SHALL usar o `X-Forwarded-For`, escolhendo o IP mais à direita que não seja de um proxy confiável. Cabeçalho ausente ou com valor que não é IP SHALL cair no endereço da conexão. Endereços IPv6 SHALL ser agrupados pelos 64 primeiros bits (/64).

#### Scenario: Sem proxy confiável
- **WHEN** `TRUSTED_PROXIES` está vazia e a chamada traz um `X-Forwarded-For` qualquer
- **THEN** o limite usa o endereço da conexão e ignora o cabeçalho

#### Scenario: Atrás de um proxy confiável
- **WHEN** a conexão vem de um IP listado em `TRUSTED_PROXIES` com `X-Forwarded-For: 203.0.113.9`
- **THEN** o limite por IP vale para `203.0.113.9`

#### Scenario: Cabeçalho forjado antes do proxy
- **WHEN** a conexão vem de um proxy confiável e o cabeçalho é `1.2.3.4, 203.0.113.9`, em que só o último valor foi acrescentado pelo proxy
- **THEN** o limite por IP vale para `203.0.113.9`

#### Scenario: Cabeçalho inválido
- **WHEN** a conexão vem de um proxy confiável e o `X-Forwarded-For` não é um IP
- **THEN** o limite usa o endereço da conexão

#### Scenario: IPv6 agrupado
- **WHEN** duas chamadas vêm de endereços IPv6 diferentes dentro do mesmo /64
- **THEN** elas contam no mesmo limite por IP

### Requirement: Limites configuráveis
Os limites e as janelas SHALL poder ser configurados por variáveis de ambiente `RATE_LIMIT_*`, com padrão de 5 erros por e-mail, 20 erros por IP e 5 erros por conta em 15 minutos, e 5 cadastros por IP em 60 minutos. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado.

#### Scenario: Limite alterado
- **WHEN** o limite de erros por e-mail está configurado em 3
- **THEN** o terceiro erro seguido já bloqueia o e-mail

#### Scenario: Valor inválido
- **WHEN** uma variável `RATE_LIMIT_*` tem o valor `0`, negativo ou texto
- **THEN** o padrão daquele limite é usado

### Requirement: Contadores seguros e sem derrubar a API
Os contadores SHALL ser guardados no banco sem o e-mail em claro (só um hash do e-mail normalizado) e SHALL deixar de existir depois que a janela vence. Se a leitura ou a gravação dos contadores falhar, a chamada MUST seguir como se não houvesse limite e o erro SHALL ser registrado no log.

#### Scenario: E-mail não fica em claro
- **WHEN** uma tentativa de login errada é registrada
- **THEN** a tabela de contadores não contém o e-mail, só um hash dele

#### Scenario: Contadores vencidos somem
- **WHEN** a janela de um contador vence e uma nova tentativa é registrada
- **THEN** o contador vencido não é mais considerado e acaba removido

#### Scenario: Falha nos contadores
- **WHEN** o banco falha ao consultar os contadores durante um login com a senha correta
- **THEN** o login responde `200` e o erro vai para o log

### Requirement: Mensagem de limite nas telas
As telas de login, de cadastro e de troca de senha SHALL mostrar a mensagem do `429` no mesmo lugar dos outros erros da API, e MUST NOT tratar o `429` como sessão expirada nem como falha de rede.

#### Scenario: Login bloqueado
- **WHEN** a API responde `429` ao login
- **THEN** a tela de login mostra a mensagem recebida e continua na tela de login

#### Scenario: Cadastro bloqueado
- **WHEN** a API responde `429` ao cadastro
- **THEN** a tela de cadastro mostra a mensagem recebida

#### Scenario: Troca de senha bloqueada
- **WHEN** a API responde `429` à troca de senha no Perfil
- **THEN** o Perfil mostra a mensagem recebida e a sessão continua ativa

### Requirement: Limite nos pedidos de redefinição de senha
`POST /api/senha/esqueci` SHALL aceitar no máximo 3 pedidos por hora por e-mail e 10 por hora por IP, contando todos os pedidos, inclusive os de e-mail sem conta. O pedido que passar do limite MUST ser recusado com `429`, sem enviar e-mail, e o bloqueio por e-mail SHALL ser igual para e-mails com e sem conta. A resposta `429` SHALL seguir o formato definido para o limite excedido.

#### Scenario: Quarto pedido do mesmo e-mail
- **WHEN** o mesmo e-mail faz 3 pedidos na hora e depois um quarto
- **THEN** o quarto responde `429` e nenhum e-mail novo é enviado

#### Scenario: E-mail sem conta bloqueia igual
- **WHEN** um e-mail sem conta faz 3 pedidos e depois um quarto
- **THEN** o quarto responde `429`, como no caso de um e-mail com conta

#### Scenario: Muitos pedidos do mesmo IP
- **WHEN** um IP faz 10 pedidos com e-mails diferentes e depois um 11º
- **THEN** o 11º responde `429`

### Requirement: Limite de tokens inválidos na redefinição
`POST /api/senha/redefinir` SHALL bloquear por 15 minutos o IP que acumular 20 tentativas com token inválido dentro de uma janela de 15 minutos. Enquanto bloqueado, a chamada MUST responder `429`, mesmo com um token válido. Só as tentativas com token inválido contam; a redefinição concluída ou recusada por senha inválida MUST NOT contar.

#### Scenario: Vigésimo primeiro token inválido
- **WHEN** um IP envia 20 tokens inválidos e depois uma 21ª chamada, mesmo com token válido
- **THEN** a 21ª resposta é `429` e a senha não muda

#### Scenario: Senha inválida não conta
- **WHEN** um IP envia 21 redefinições com token válido e senha curta demais
- **THEN** todas as respostas são `422` e nenhum bloqueio é aplicado

### Requirement: Limites da redefinição configuráveis
Os limites da redefinição SHALL poder ser configurados por `RATE_LIMIT_ESQUECI_EMAIL`, `RATE_LIMIT_ESQUECI_IP`, `RATE_LIMIT_ESQUECI_JANELA_MIN` e `RATE_LIMIT_REDEFINIR_IP`, com os padrões de 3 pedidos por e-mail, 10 por IP, janela de 60 minutos e 20 tokens inválidos por IP na janela de 15 minutos. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado. As telas da redefinição SHALL mostrar a mensagem do `429` no mesmo lugar dos outros erros.

#### Scenario: Limite do pedido alterado
- **WHEN** `RATE_LIMIT_ESQUECI_EMAIL` está configurada em 1
- **THEN** o segundo pedido do mesmo e-mail na janela já responde `429`

#### Scenario: Valor inválido
- **WHEN** uma dessas variáveis tem `0`, valor negativo ou texto
- **THEN** o padrão daquele limite é usado

#### Scenario: Tela bloqueada
- **WHEN** a API responde `429` ao pedido ou à redefinição
- **THEN** a tela mostra a mensagem recebida e continua na mesma tela

### Requirement: Limite no reenvio e na troca de e-mail
`POST /api/conta/email/reenviar` e `PUT /api/conta/email` SHALL aceitar, juntos, no máximo 3 envios de confirmação por hora por conta, contando todas as chamadas que chegam a enviar. A chamada que passar do limite MUST ser recusada com `429` e sem enviar e-mail. As senhas atuais erradas na troca de e-mail SHALL contar no mesmo limite de erros de senha da conta que vale para a troca de senha.

#### Scenario: Quarto envio na hora
- **WHEN** a mesma conta faz 3 reenvios ou trocas na hora e depois um quarto
- **THEN** o quarto responde `429` e nenhum e-mail novo é enviado

#### Scenario: Senha errada na troca de e-mail
- **WHEN** a conta erra a senha atual 5 vezes entre trocas de e-mail e troca de senha, e depois tenta de novo com a senha certa
- **THEN** a chamada responde `429` e o e-mail não muda

#### Scenario: Outra conta não é afetada
- **WHEN** uma conta esgotou o limite do reenvio e outra conta pede o reenvio
- **THEN** a chamada da outra conta responde `202`

### Requirement: Limite de tokens inválidos na confirmação
`POST /api/email/confirmar` SHALL bloquear por 15 minutos o IP que acumular 20 tentativas com token inválido dentro de uma janela de 15 minutos. Enquanto bloqueado, a chamada MUST responder `429`, mesmo com um token válido. Só as tentativas com token inválido contam; a confirmação concluída MUST NOT contar.

#### Scenario: Vigésimo primeiro token inválido
- **WHEN** um IP envia 20 tokens inválidos e depois uma 21ª chamada, mesmo com token válido
- **THEN** a 21ª resposta é `429` e o e-mail não é confirmado

#### Scenario: Confirmação concluída não conta
- **WHEN** um IP confirma um e-mail com um token válido
- **THEN** nenhum contador de erros do IP é incrementado

### Requirement: Limites da confirmação configuráveis
Os limites da confirmação SHALL poder ser configurados por `RATE_LIMIT_CONFIRMACAO_CONTA`, `RATE_LIMIT_CONFIRMACAO_JANELA_MIN` e `RATE_LIMIT_CONFIRMAR_IP`, com os padrões de 3 envios por conta na janela de 60 minutos e 20 tokens inválidos por IP na janela de 15 minutos. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado. As telas da confirmação SHALL mostrar a mensagem do `429` no mesmo lugar dos outros erros.

#### Scenario: Limite configurado
- **WHEN** `RATE_LIMIT_CONFIRMACAO_CONTA` está configurada em 1
- **THEN** o segundo envio da mesma conta na janela já responde `429`

#### Scenario: Valor inválido
- **WHEN** uma dessas variáveis tem `0`, valor negativo ou texto
- **THEN** o padrão daquele limite é usado
