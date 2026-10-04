# password-recovery Specification

## Purpose
Permite que o dono de uma loja que esqueceu a senha crie uma nova por um link enviado ao e-mail da conta, sem revelar a terceiros quais e-mails têm conta.

## Requirements

### Requirement: Pedido de redefinição
`POST /api/senha/esqueci`, com o corpo `{"email"}`, SHALL responder `202` com a mesma mensagem ("Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.") tanto para e-mail com conta quanto sem conta, e SHALL enviar o link só quando a conta existe. A chamada MUST NOT exigir login. E-mail ausente ou com formato inválido SHALL ser recusado com `422`.

#### Scenario: E-mail com conta
- **WHEN** a dona pede a redefinição com o e-mail da conta
- **THEN** a resposta é `202` com a mensagem padrão e um e-mail com o link é enviado para esse endereço

#### Scenario: E-mail sem conta
- **WHEN** alguém pede a redefinição com um e-mail que não tem conta
- **THEN** a resposta é `202` com a mesma mensagem e nenhum e-mail é enviado

#### Scenario: E-mail com maiúsculas e espaços
- **WHEN** o pedido traz o e-mail da conta em maiúsculas ou com espaços nas pontas
- **THEN** o e-mail é encontrado como no login e o link é enviado

#### Scenario: Formato inválido
- **WHEN** o pedido traz um e-mail sem formato válido ou nenhum e-mail
- **THEN** a resposta é `422` e nada é enviado

### Requirement: Token de redefinição
O link SHALL carregar um token aleatório e imprevisível, com pelo menos 256 bits, e o servidor SHALL guardar apenas um hash dele, nunca o token. O token SHALL valer por 1 hora a partir do pedido e SHALL ser de uso único. Um novo pedido da mesma conta MUST invalidar o token anterior.

#### Scenario: Token no banco
- **WHEN** um pedido de redefinição é feito para uma conta
- **THEN** o banco não contém o valor do token enviado por e-mail, só o seu hash

#### Scenario: Pedido novo invalida o anterior
- **WHEN** a dona pede a redefinição duas vezes e tenta usar o token do primeiro e-mail
- **THEN** o primeiro token é recusado e só o do segundo e-mail vale

#### Scenario: Token vencido
- **WHEN** o token é usado depois de 1 hora do pedido
- **THEN** a redefinição é recusada

### Requirement: Link do e-mail
O e-mail SHALL conter o link `<APP_URL>/redefinir-senha#token=<token>`, com o token no fragmento (e não na query), e SHALL informar que o link vale por 1 hora. Se `APP_URL` não estiver configurada, o link MUST NOT ser enviado e o problema SHALL ser registrado no log, sem mudar a resposta do pedido.

#### Scenario: Conteúdo do e-mail
- **WHEN** a redefinição é pedida para uma conta
- **THEN** o e-mail em português contém o link com o token no fragmento e o aviso de que vale por 1 hora

#### Scenario: Sem APP_URL
- **WHEN** `APP_URL` não está configurada e a redefinição é pedida para uma conta
- **THEN** nenhum e-mail é enviado, o erro vai para o log e a resposta continua `202`

### Requirement: Concluir a redefinição
`POST /api/senha/redefinir`, com o corpo `{"token", "nova_senha"}`, SHALL trocar a senha da conta do token quando ele é válido e a nova senha vale as mesmas regras do cadastro (8 a 72 bytes), e responder `200`. A troca SHALL invalidar todos os tokens da conta e encerrar **todas** as sessões dela. A resposta MUST NOT abrir uma sessão nem devolver token de sessão. Token ausente, desconhecido, vencido ou já usado SHALL ser recusado com `422` e a mensagem "Link inválido ou expirado. Peça um novo.".

#### Scenario: Redefinição válida
- **WHEN** a dona envia o token do e-mail e uma nova senha válida
- **THEN** a resposta é `200`, o login com a senha antiga é recusado e o login com a nova senha funciona

#### Scenario: Sessões encerradas
- **WHEN** a conta tem sessões abertas e a senha é redefinida
- **THEN** todos os tokens de sessão dela passam a receber `401`

#### Scenario: Sem sessão na resposta
- **WHEN** a redefinição é concluída
- **THEN** a resposta não traz token de sessão e a conta continua sem sessão aberta

#### Scenario: Token de uso único
- **WHEN** o mesmo token é usado uma segunda vez
- **THEN** a resposta é `422` com "Link inválido ou expirado. Peça um novo." e a senha não muda

#### Scenario: Token inválido
- **WHEN** o token enviado não existe, ou o corpo não traz token
- **THEN** a resposta é `422` com a mesma mensagem e nada é alterado

#### Scenario: Nova senha inválida
- **WHEN** o token é válido mas a nova senha tem menos de 8 ou mais de 72 bytes
- **THEN** a resposta é `422` com a mensagem de senha inválida e o token continua válido para uma nova tentativa

### Requirement: Falha do envio não aparece na resposta
Se o envio do e-mail falhar, a resposta do pedido de redefinição SHALL continuar `202` com a mesma mensagem, e a falha SHALL ser registrada no log, para que a resposta não revele quais e-mails têm conta.

#### Scenario: Envio falha
- **WHEN** o e-mail de uma conta existente não pode ser enviado
- **THEN** a resposta é `202` com a mensagem padrão e o erro vai para o log

### Requirement: Telas de redefinição
A página `/esqueci-senha` SHALL pedir o e-mail e, depois de enviado, mostrar a mensagem da API sem dizer se o e-mail tem conta. A página `/redefinir-senha` SHALL ler o token do fragmento do endereço, tirá-lo do endereço, pedir "Nova Senha" e "Confirmar Nova Senha" e, ao concluir, levar ao `/login` com o aviso "Senha alterada. Entre com a nova senha.". As duas telas SHALL ser abertas só por quem não está logada.

#### Scenario: Pedir o link
- **WHEN** a pessoa informa o e-mail em `/esqueci-senha` e envia
- **THEN** a tela mostra a mensagem recebida da API e nenhum dado sobre a existência da conta

#### Scenario: Abrir o link do e-mail
- **WHEN** a pessoa abre `/redefinir-senha#token=abc`
- **THEN** a tela pede a nova senha e o fragmento deixa de aparecer no endereço

#### Scenario: Link sem token
- **WHEN** a pessoa abre `/redefinir-senha` sem fragmento
- **THEN** a tela avisa que o link é inválido e oferece pedir um novo em `/esqueci-senha`

#### Scenario: Senhas diferentes
- **WHEN** "Nova Senha" e "Confirmar Nova Senha" são diferentes
- **THEN** a tela mostra "As senhas não são iguais." e nenhuma requisição é feita

#### Scenario: Redefinição concluída
- **WHEN** a API responde `200`
- **THEN** a pessoa vai para `/login` e vê "Senha alterada. Entre com a nova senha."

#### Scenario: Link vencido
- **WHEN** a API responde `422` com "Link inválido ou expirado. Peça um novo."
- **THEN** a tela mostra essa mensagem e oferece pedir um novo link

#### Scenario: API fora do ar
- **WHEN** a API não responde
- **THEN** as telas mostram a mensagem fixa de falha de rede e mantêm o que foi digitado
