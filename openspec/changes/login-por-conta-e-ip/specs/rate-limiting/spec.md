# Spec Delta

## MODIFIED Requirements

### Requirement: Limite de erros no login
`POST /api/sessoes` SHALL contar os erros de login em três limites, todos na janela de 15 minutos. Com 5 erros de um e-mail vindos do mesmo IP, o e-mail fica bloqueado só naquele IP. Com 50 erros de um e-mail somando todos os IPs, o e-mail fica bloqueado em qualquer IP. Com 20 erros de um IP, o IP fica bloqueado. Bloqueado, o login MUST responder `429` sem verificar a senha, mesmo correta. Contam só a senha ou o e-mail errados, inclusive o e-mail desconhecido. O `422` MUST NOT contar.

#### Scenario: Quinto erro seguido no mesmo e-mail
- **WHEN** o mesmo IP faz 5 logins com a senha errada para o mesmo e-mail e depois uma sexta chamada, mesmo com a senha certa
- **THEN** a sexta resposta é `429`, nenhuma sessão é aberta e a senha não é verificada

#### Scenario: Dona entra de outro IP
- **WHEN** um IP acumula 5 erros para um e-mail e, em seguida, outro IP faz login nesse e-mail com a senha certa
- **THEN** o login do outro IP responde `200`

#### Scenario: Outro e-mail no mesmo IP
- **WHEN** um IP acumula 5 erros para um e-mail e depois faz login em outro e-mail com a senha certa
- **THEN** o login no outro e-mail responde `200`

#### Scenario: Teto do e-mail somando IPs
- **WHEN** um e-mail acumula 50 erros espalhados por vários IPs, com menos de 5 em cada um, e um IP novo faz uma chamada seguinte, mesmo com a senha certa
- **THEN** a resposta é `429` e nenhuma sessão é aberta

#### Scenario: E-mail desconhecido também bloqueia
- **WHEN** um IP faz 5 logins num e-mail sem conta e depois uma sexta chamada
- **THEN** a sexta resposta é `429`, igual à de um e-mail que existe

#### Scenario: Login correto zera o contador do e-mail
- **WHEN** um IP acumula 4 erros para um e-mail e depois faz login com a senha certa
- **THEN** o login responde `200` e esse IP volta a poder errar 4 vezes nesse e-mail sem bloqueio

#### Scenario: Vários e-mails do mesmo IP
- **WHEN** um IP acumula 20 tentativas erradas (em e-mails diferentes) e faz uma 21ª chamada
- **THEN** a 21ª resposta é `429`

#### Scenario: Dados faltando não contam
- **WHEN** o login é chamado 6 vezes sem informar a senha
- **THEN** todas as respostas são `422` e nenhum bloqueio é aplicado

#### Scenario: Janela vencida
- **WHEN** passam 15 minutos desde a primeira tentativa errada do bloqueio
- **THEN** o e-mail e o IP voltam a poder tentar o login

### Requirement: Resposta de limite excedido
Uma chamada bloqueada SHALL responder `429` com o corpo `{"erro": "<mensagem>"}`, o cabeçalho `Retry-After` com os segundos que faltam para o bloqueio acabar e uma mensagem em português que informe esse tempo em minutos. Com `CORS_ORIGIN` configurada, a resposta SHALL incluir `Access-Control-Expose-Headers: Retry-After`. O limite MUST NOT revelar se um e-mail tem conta: os bloqueios por e-mail, no mesmo IP ou somando todos os IPs, SHALL ser iguais para e-mails com e sem conta.

#### Scenario: Formato do 429
- **WHEN** uma chamada é bloqueada por excesso de tentativas
- **THEN** a resposta é `429` com `erro` em português citando o tempo de espera em minutos e um `Retry-After` com um número inteiro positivo de segundos, no máximo a janela do limite

#### Scenario: Retry-After exposto ao navegador
- **WHEN** `CORS_ORIGIN` está configurada e uma chamada é bloqueada
- **THEN** a resposta traz `Access-Control-Expose-Headers` com `Retry-After`

#### Scenario: Bloqueio não revela e-mails
- **WHEN** um e-mail com conta e um e-mail sem conta recebem o mesmo número de logins errados
- **THEN** os dois passam a receber `429` no mesmo ponto, com respostas equivalentes

#### Scenario: Teto somando IPs não revela e-mails
- **WHEN** um e-mail com conta e um e-mail sem conta recebem 50 logins errados espalhados pelos mesmos IPs
- **THEN** os dois passam a receber `429` de qualquer IP no mesmo ponto, com respostas equivalentes

### Requirement: Limites configuráveis
Os limites e as janelas SHALL poder ser configurados por variáveis de ambiente `RATE_LIMIT_*`. Os padrões são, em 15 minutos, 5 erros por e-mail no mesmo IP, 50 erros por e-mail somando todos os IPs, 20 erros por IP e 5 erros por conta, e, em 60 minutos, 5 cadastros por IP. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado.

#### Scenario: Limite alterado
- **WHEN** o limite de erros por e-mail no mesmo IP está configurado em 3
- **THEN** o terceiro erro seguido do mesmo IP já bloqueia o e-mail naquele IP

#### Scenario: Teto alterado
- **WHEN** o limite de erros por e-mail somando todos os IPs está configurado em 10
- **THEN** depois de 10 erros espalhados por IPs diferentes o e-mail fica bloqueado em qualquer IP

#### Scenario: Valor inválido
- **WHEN** uma variável `RATE_LIMIT_*` tem o valor `0`, negativo ou texto
- **THEN** o padrão daquele limite é usado

## ADDED Requirements

### Requirement: Login certo zera só o contador do IP
Um login com a senha certa SHALL zerar o contador de erros daquele e-mail no IP de onde ele veio. Ele MUST NOT zerar o contador do e-mail somando todos os IPs nem o contador do IP.

#### Scenario: Login certo não devolve tentativas ao ataque distribuído
- **WHEN** um e-mail acumula 49 erros espalhados por vários IPs, a dona faz login com a senha certa de outro IP e um IP novo erra mais uma vez
- **THEN** a chamada seguinte para esse e-mail, de qualquer IP, responde `429`
