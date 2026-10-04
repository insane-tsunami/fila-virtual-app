# Spec Delta

## Purpose

Prova que o e-mail de uma conta é de quem a controla, por um link enviado ao próprio endereço, e dá à pessoa que errou o e-mail uma saída para corrigi-lo.

## ADDED Requirements

### Requirement: Estado de confirmação do e-mail
Toda conta SHALL ter o estado `email_confirmado` (verdadeiro ou falso). Uma conta nova SHALL nascer com o e-mail **não confirmado**. A conta não confirmada MUST poder entrar e usar o dashboard normalmente; a confirmação só muda o que o "esqueci a senha" faz (ver `password-recovery`).

#### Scenario: Conta nova
- **WHEN** uma conta é criada pelo cadastro
- **THEN** o e-mail dela está não confirmado e ela já pode usar o dashboard

#### Scenario: Conta confirmada
- **WHEN** o e-mail da conta é confirmado
- **THEN** `email_confirmado` passa a ser verdadeiro e continua assim nos logins seguintes

### Requirement: Confirmação enviada no cadastro
O cadastro SHALL enviar ao e-mail da conta nova uma mensagem com o link de confirmação. Se o envio falhar, o cadastro MUST continuar válido (`201`) e a falha SHALL ser registrada no log, sem o link.

#### Scenario: E-mail de confirmação no cadastro
- **WHEN** alguém se cadastra
- **THEN** o endereço cadastrado recebe um e-mail em português com o link de confirmação e o aviso de que vale por 24 horas

#### Scenario: Envio falha no cadastro
- **WHEN** o envio do e-mail de confirmação falha durante o cadastro
- **THEN** o cadastro responde `201`, a conta fica criada e o erro vai para o log

### Requirement: Token de confirmação
O link SHALL carregar um token aleatório e imprevisível, com pelo menos 256 bits, e o servidor SHALL guardar apenas um hash dele. O token SHALL valer por 24 horas, ser de uso único e ficar preso ao e-mail para o qual foi enviado. Uma conta MUST ter no máximo um token válido: um reenvio ou uma troca de e-mail invalida o anterior.

#### Scenario: Token no banco
- **WHEN** a confirmação é enviada
- **THEN** o banco não contém o valor do token enviado, só o seu hash

#### Scenario: Reenvio invalida o anterior
- **WHEN** a confirmação é enviada duas vezes para a mesma conta e o token do primeiro e-mail é usado
- **THEN** o primeiro token é recusado e só o do segundo e-mail vale

#### Scenario: Token vencido
- **WHEN** o token é usado depois de 24 horas do envio
- **THEN** a confirmação é recusada

### Requirement: Link do e-mail de confirmação
O e-mail SHALL conter o link `<APP_URL>/confirmar-email#token=<token>`, com o token no fragmento, e SHALL informar que o link vale por 24 horas. Se `APP_URL` não estiver configurada, o link MUST NOT ser enviado e o problema SHALL ser registrado no log, sem mudar a resposta da chamada.

#### Scenario: Conteúdo do e-mail
- **WHEN** a confirmação é enviada
- **THEN** o e-mail contém o link com o token no fragmento e o prazo de 24 horas

#### Scenario: Sem APP_URL
- **WHEN** `APP_URL` não está configurada e a confirmação é pedida
- **THEN** nenhum e-mail é enviado, o erro vai para o log e a chamada segue normalmente

### Requirement: Confirmar o e-mail
`POST /api/email/confirmar`, com o corpo `{"token"}`, SHALL confirmar o e-mail da conta do token, apagar o token e responder `200` com a mensagem "E-mail confirmado.". A chamada MUST NOT exigir login. Token ausente, desconhecido, vencido, já usado ou preso a um e-mail que já não é o da conta SHALL ser recusado com `422` e a mensagem "Link inválido ou expirado. Peça um novo e-mail de confirmação.".

#### Scenario: Confirmação válida
- **WHEN** a pessoa envia o token do e-mail de confirmação
- **THEN** a resposta é `200` e `email_confirmado` da conta passa a ser verdadeiro

#### Scenario: Sem estar logada
- **WHEN** a confirmação é chamada sem `Authorization`
- **THEN** ela funciona normalmente

#### Scenario: Token de uso único
- **WHEN** o mesmo token é usado uma segunda vez
- **THEN** a resposta é `422` com "Link inválido ou expirado. Peça um novo e-mail de confirmação."

#### Scenario: Token inválido
- **WHEN** o token não existe, está vencido ou não vem no corpo
- **THEN** a resposta é `422` com a mesma mensagem e nada é alterado

#### Scenario: Token de um e-mail que foi trocado
- **WHEN** a conta troca de e-mail e o token do e-mail antigo é usado
- **THEN** a resposta é `422` e a conta continua não confirmada

### Requirement: Reenviar a confirmação
`POST /api/conta/email/reenviar` SHALL, para uma sessão válida, enviar de novo a confirmação ao e-mail da conta, invalidar o token anterior e responder `202` com a mensagem "Enviamos um novo link de confirmação.". Se o e-mail já está confirmado, SHALL responder `409` e não enviar nada. Sem sessão válida, SHALL responder `401`.

#### Scenario: Reenviar
- **WHEN** a dona com e-mail não confirmado pede o reenvio
- **THEN** a resposta é `202` e um novo e-mail com o link é enviado

#### Scenario: Já confirmado
- **WHEN** a dona com e-mail confirmado pede o reenvio
- **THEN** a resposta é `409` e nenhum e-mail é enviado

#### Scenario: Sem sessão
- **WHEN** o reenvio é chamado sem sessão válida
- **THEN** a resposta é `401`

### Requirement: Trocar o e-mail enquanto não confirmado
`PUT /api/conta/email`, com o corpo `{"email", "senha"}`, SHALL trocar o e-mail de uma conta **não confirmada** quando a senha atual confere e o novo e-mail é válido e livre, invalidar a confirmação anterior, enviar a nova confirmação ao novo endereço e responder `200` com a conta (`email`, `cnpj`, `email_confirmado` falso). A senha errada SHALL ser recusada com `422`, o e-mail de outra conta com `409`, o mesmo e-mail da conta com `422`, e a conta já confirmada com `409`. Sem sessão válida, SHALL responder `401`.

#### Scenario: Corrigir o e-mail
- **WHEN** a dona com e-mail não confirmado informa um e-mail novo e a senha certa
- **THEN** a resposta é `200` com o novo e-mail, a confirmação vai para o novo endereço e o login passa a valer com o novo e-mail

#### Scenario: Senha errada
- **WHEN** a troca é pedida com a senha atual errada
- **THEN** a resposta é `422` e o e-mail não muda

#### Scenario: E-mail de outra conta
- **WHEN** o novo e-mail já pertence a outra conta
- **THEN** a resposta é `409` e o e-mail não muda

#### Scenario: Conta já confirmada
- **WHEN** uma conta com e-mail confirmado tenta trocar o e-mail
- **THEN** a resposta é `409` e o e-mail não muda

#### Scenario: Confirmação antiga deixa de valer
- **WHEN** o e-mail é trocado depois de a confirmação ter sido enviada ao endereço antigo
- **THEN** o token do endereço antigo é recusado

### Requirement: Contas existentes ficam não confirmadas
As contas que já existiam quando esta mudança entrar SHALL ficar com o e-mail **não confirmado**, sem anistia, e SHALL poder confirmá-lo pelo reenvio.

#### Scenario: Conta antiga
- **WHEN** uma conta criada antes desta mudança consulta os dados da conta
- **THEN** `email_confirmado` é falso

### Requirement: Página de confirmação
A página `/confirmar-email` SHALL ser aberta com ou sem login, ler o token do fragmento do endereço, tirá-lo do endereço e confirmar o e-mail. Com sucesso, SHALL mostrar "E-mail confirmado." e um botão "Entrar". Com `422`, SHALL mostrar a mensagem da API e orientar a pedir um novo link pelo dashboard. Sem token, SHALL avisar que o link é inválido.

#### Scenario: Abrir o link do e-mail
- **WHEN** a pessoa abre `/confirmar-email#token=abc`
- **THEN** a tela confirma o e-mail, mostra "E-mail confirmado." e o fragmento deixa de aparecer no endereço

#### Scenario: Aberta já logada
- **WHEN** a pessoa já logada no navegador abre o link de confirmação
- **THEN** a página funciona e não redireciona para o dashboard

#### Scenario: Link vencido
- **WHEN** a API responde `422` com "Link inválido ou expirado. Peça um novo e-mail de confirmação."
- **THEN** a tela mostra essa mensagem e orienta a pedir um novo link pelo dashboard

#### Scenario: Link sem token
- **WHEN** a pessoa abre `/confirmar-email` sem fragmento
- **THEN** a tela avisa que o link é inválido e não faz requisição

#### Scenario: API fora do ar
- **WHEN** a API não responde
- **THEN** a tela mostra a mensagem fixa de falha de rede

### Requirement: Faixa de aviso no dashboard
Enquanto o e-mail da conta logada não estiver confirmado, as páginas do dashboard SHALL mostrar a faixa "Confirme seu e-mail" com os botões "Reenviar e-mail" e "Já confirmei" e o link "Trocar e-mail" (para `/dashboard/perfil`). "Reenviar e-mail" SHALL pedir o reenvio e mostrar a mensagem da API; "Já confirmei" SHALL reconsultar a conta e, se confirmada, a faixa SHALL sumir. Com o e-mail confirmado, a faixa MUST NOT aparecer.

#### Scenario: Conta não confirmada
- **WHEN** a dona com e-mail não confirmado abre o dashboard
- **THEN** a faixa "Confirme seu e-mail" aparece com as três ações

#### Scenario: Conta confirmada
- **WHEN** a dona com e-mail confirmado abre o dashboard
- **THEN** a faixa não aparece

#### Scenario: Reenviar
- **WHEN** a dona aciona "Reenviar e-mail"
- **THEN** a tela mostra a mensagem recebida da API e a faixa continua

#### Scenario: Já confirmei
- **WHEN** a dona confirmou o e-mail em outro aparelho e aciona "Já confirmei"
- **THEN** a conta é reconsultada e a faixa some

#### Scenario: Limite de tentativas
- **WHEN** a API responde `429` ao reenvio
- **THEN** a faixa mostra a mensagem recebida e a sessão continua ativa
