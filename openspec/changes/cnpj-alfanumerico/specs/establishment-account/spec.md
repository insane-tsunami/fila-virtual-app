# Spec Delta

## MODIFIED Requirements

### Requirement: Validação dos dados do cadastro
O e-mail SHALL ter formato válido, até 254 caracteres, e ser guardado sem espaços nas pontas e em minúsculas. O CNPJ SHALL ser aceito com ou sem máscara, em letras maiúsculas ou minúsculas, e guardado como 14 caracteres sem máscara e em maiúsculas: as 12 primeiras posições são letras de `A` a `Z` ou dígitos e as 2 últimas são os dígitos verificadores, que SHALL ser conferidos (módulo 11, pesos 5,4,3,2,9,8,7,6,5,4,3,2 e 6,5,4,3,2,9,8,7,6,5,4,3,2, com cada caractere valendo o seu código ASCII menos 48). Um CNPJ com os 14 caracteres iguais MUST ser recusado. O nome SHALL ter de 2 a 120 caracteres sem contar espaços nas pontas. A senha SHALL ter de 8 a 72 bytes. Dado inválido SHALL ser recusado com `422` e uma mensagem que diga qual campo está errado; para o CNPJ, a mensagem SHALL mandar conferir os caracteres e os dígitos verificadores.

#### Scenario: CNPJ com máscara
- **WHEN** o CNPJ é enviado como `93.339.970/0001-05`
- **THEN** ele é aceito e guardado como `93339970000105`

#### Scenario: CNPJ alfanumérico
- **WHEN** o CNPJ é enviado como `12.ABC.345/01DE-35`
- **THEN** ele é aceito e guardado como `12ABC34501DE35`

#### Scenario: CNPJ em minúsculas
- **WHEN** o CNPJ é enviado como `12.abc.345/01de-35`
- **THEN** ele é aceito e guardado como `12ABC34501DE35`

#### Scenario: Dígito verificador errado
- **WHEN** o CNPJ é enviado como `12ABC34501DE36` ou como `11222333000180`
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

#### Scenario: CNPJ com todos os caracteres iguais
- **WHEN** o CNPJ é enviado como `00000000000000`, que passaria na conta do dígito verificador
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

#### Scenario: Letra nos dígitos verificadores
- **WHEN** as duas últimas posições do CNPJ não são dígitos, como em `12ABC34501DEAB`
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

#### Scenario: E-mail em maiúsculas
- **WHEN** o e-mail é enviado como ` Contato@VesteBem.com `
- **THEN** ele é guardado como `contato@vestebem.com`

#### Scenario: Campo inválido
- **WHEN** o e-mail não tem formato válido, o CNPJ não tem 14 caracteres válidos, o nome é curto demais ou a senha tem menos de 8 ou mais de 72 bytes
- **THEN** a resposta é `422` com a mensagem do campo e nada é criado

#### Scenario: Campo ausente
- **WHEN** o corpo não traz algum dos quatro campos
- **THEN** a resposta é `422` e nada é criado

### Requirement: E-mail e CNPJ únicos
Cada e-mail e cada CNPJ SHALL pertencer a uma única conta, sem diferenciar maiúsculas de minúsculas. Um cadastro com e-mail ou CNPJ já usado SHALL ser recusado com `409`, sem criar nada e sem alterar a conta existente.

#### Scenario: E-mail já cadastrado
- **WHEN** alguém se cadastra com um e-mail que já tem conta, mesmo com maiúsculas diferentes
- **THEN** a resposta é `409` e a conta existente não muda

#### Scenario: CNPJ já cadastrado
- **WHEN** alguém se cadastra com um CNPJ que já tem conta, com ou sem máscara
- **THEN** a resposta é `409`

#### Scenario: CNPJ repetido com outra caixa
- **WHEN** alguém se cadastra com `12.abc.345/01de-35` e já existe uma conta com `12ABC34501DE35`
- **THEN** a resposta é `409` e a conta existente não muda
