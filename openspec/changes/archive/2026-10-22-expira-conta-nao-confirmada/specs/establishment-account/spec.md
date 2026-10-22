## MODIFIED Requirements

### Requirement: E-mail e CNPJ únicos
Cada e-mail e cada CNPJ SHALL pertencer a uma única conta, sem diferenciar maiúsculas de minúsculas. Um cadastro com e-mail ou CNPJ já usado SHALL ser recusado com `409`, sem criar nada e sem alterar a conta existente, exceto quando todas as contas que o bloqueiam podem ser substituídas (ver "Cadastro sobre conta não confirmada abandonada"). Dois cadastros simultâneos com o mesmo e-mail ou CNPJ MUST NOT criar duas contas nem responder `500`.

#### Scenario: E-mail já cadastrado
- **WHEN** alguém se cadastra com um e-mail que já tem uma conta que não pode ser substituída, mesmo com maiúsculas diferentes
- **THEN** a resposta é `409` e a conta existente não muda

#### Scenario: CNPJ já cadastrado
- **WHEN** alguém se cadastra com um CNPJ que já tem uma conta que não pode ser substituída, com ou sem máscara
- **THEN** a resposta é `409`

#### Scenario: CNPJ repetido com outra caixa
- **WHEN** alguém se cadastra com `12.abc.345/01de-35` e já existe uma conta que não pode ser substituída com `12ABC34501DE35`
- **THEN** a resposta é `409` e a conta existente não muda

#### Scenario: Cadastros simultâneos
- **WHEN** dois cadastros com o mesmo e-mail ou o mesmo CNPJ chegam ao mesmo tempo
- **THEN** só um deles cria a conta e o outro recebe `409`, nunca `500`

## ADDED Requirements

### Requirement: Cadastro sobre conta não confirmada abandonada
Uma conta SHALL poder ser substituída por um cadastro com o mesmo e-mail ou CNPJ quando, ao mesmo tempo, o e-mail dela não está confirmado, o prazo `confirmar_ate` dela já passou e a loja dela não recebeu nenhuma entrada na fila nos últimos 14 dias. Se todas as contas que bloqueiam o cadastro podem ser substituídas, elas SHALL ser apagadas e o cadastro segue com `201`; senão, a resposta é o `409` de sempre e nada é apagado.

#### Scenario: E-mail ocupado por conta abandonada
- **WHEN** alguém se cadastra com um e-mail cuja conta não está confirmada, passou do prazo e não teve entradas na fila nos últimos 14 dias
- **THEN** a resposta é `201`, a conta antiga deixa de existir e a nova fica com o e-mail

#### Scenario: CNPJ ocupado por conta abandonada com outro e-mail
- **WHEN** alguém se cadastra com um CNPJ cuja conta, com outro e-mail, não está confirmada, passou do prazo e não teve entradas nos últimos 14 dias
- **THEN** a resposta é `201` e a conta antiga deixa de existir

#### Scenario: Ainda dentro do prazo
- **WHEN** a conta que bloqueia não está confirmada, mas o prazo `confirmar_ate` dela ainda não passou
- **THEN** a resposta é `409` e a conta não muda

#### Scenario: Loja com clientes recentes
- **WHEN** a conta que bloqueia não está confirmada e passou do prazo, mas a loja dela recebeu uma entrada na fila nos últimos 14 dias
- **THEN** a resposta é `409` e a conta não muda

#### Scenario: E-mail e CNPJ de contas diferentes, uma protegida
- **WHEN** o e-mail do cadastro é de uma conta abandonada e o CNPJ é de outra conta que não pode ser substituída
- **THEN** a resposta é `409` e nenhuma das duas contas muda

#### Scenario: E-mail e CNPJ de duas contas abandonadas
- **WHEN** o e-mail do cadastro é de uma conta abandonada e o CNPJ é de outra conta abandonada
- **THEN** as duas são apagadas e a resposta é `201`

### Requirement: Conta confirmada nunca é substituída
Uma conta com o e-mail confirmado MUST NOT ser substituída nem apagada por um cadastro, qualquer que seja a idade dela ou a atividade da loja.

#### Scenario: Conta confirmada antiga e sem clientes
- **WHEN** alguém se cadastra com o e-mail ou o CNPJ de uma conta confirmada que passou do prazo e nunca teve entradas na fila
- **THEN** a resposta é `409` e a conta, a loja e as sessões dela não mudam

### Requirement: O que sai com a conta substituída
Ao substituir uma conta, SHALL ser apagados junto com ela a loja (com o slug e a fila), as entradas da fila dessa loja, as sessões e os links de confirmação e de redefinição de senha. O token de sessão antigo SHALL passar a receber `401` e o link de confirmação antigo `422`. Outras contas e lojas, inclusive a loja de demonstração, MUST NOT ser alteradas.

#### Scenario: Sessão da conta substituída
- **WHEN** a conta antiga tinha uma sessão aberta e foi substituída
- **THEN** chamar `GET /api/conta` com o token antigo responde `401`

#### Scenario: Link de confirmação da conta substituída
- **WHEN** a conta antiga tinha um link de confirmação válido e foi substituída
- **THEN** usar esse link responde `422`

#### Scenario: Loja da conta substituída
- **WHEN** a conta antiga foi substituída
- **THEN** `GET /api/filas/{slug}` com o slug antigo responde `404` e as entradas da fila dela não existem mais

#### Scenario: Outras lojas intactas
- **WHEN** uma conta é substituída
- **THEN** as outras contas, as lojas delas, as filas e a loja de demonstração continuam iguais
