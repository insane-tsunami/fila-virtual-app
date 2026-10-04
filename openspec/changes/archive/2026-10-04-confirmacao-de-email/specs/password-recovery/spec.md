# Spec Delta

## MODIFIED Requirements

### Requirement: Pedido de redefinição
`POST /api/senha/esqueci`, com o corpo `{"email"}`, SHALL responder `202` com a mesma mensagem ("Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.") em todos os casos válidos, e SHALL enviar o link **só** quando a conta existe **e** o e-mail dela está confirmado. A chamada MUST NOT exigir login. E-mail ausente ou com formato inválido SHALL ser recusado com `422`.

#### Scenario: E-mail com conta
- **WHEN** a dona pede a redefinição com o e-mail confirmado da conta
- **THEN** a resposta é `202` com a mensagem padrão e um e-mail com o link é enviado para esse endereço

#### Scenario: E-mail sem conta
- **WHEN** alguém pede a redefinição com um e-mail que não tem conta
- **THEN** a resposta é `202` com a mesma mensagem e nenhum e-mail é enviado

#### Scenario: E-mail com maiúsculas e espaços
- **WHEN** o pedido traz o e-mail confirmado da conta em maiúsculas ou com espaços nas pontas
- **THEN** o e-mail é encontrado como no login e o link é enviado

#### Scenario: Formato inválido
- **WHEN** o pedido traz um e-mail sem formato válido ou nenhum e-mail
- **THEN** a resposta é `422` e nada é enviado

#### Scenario: E-mail não confirmado
- **WHEN** alguém pede a redefinição com o e-mail de uma conta cujo e-mail não está confirmado
- **THEN** a resposta é `202` com a mesma mensagem, nenhum e-mail é enviado e nenhum token é criado
