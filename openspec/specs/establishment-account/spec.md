# establishment-account Specification

## Purpose
Conta do estabelecimento na API: o cadastro cria a conta e a loja juntas, e a conta logada consulta seus dados e troca a própria senha.

## Requirements

### Requirement: Cadastro da conta e da loja
`POST /api/contas`, com o corpo `{"email", "cnpj", "nome", "senha"}`, SHALL criar, em uma única operação, a conta (com o e-mail **não confirmado**) e a loja que ela possui, abrir uma sessão, enviar a confirmação do e-mail e responder `201` com `token`, `expira_em`, os dados da conta (`email`, `cnpj`, `email_confirmado` falso) e da loja (`nome`, `slug`, `endereco_publico` nulo). Se qualquer parte falhar, nada MUST ser criado. A chamada MUST NOT exigir login.

#### Scenario: Cadastro válido
- **WHEN** alguém envia e-mail, CNPJ, nome do estabelecimento e senha válidos
- **THEN** a resposta é `201` com um `token` de sessão, a conta e a loja criadas
- **AND** o `slug` da loja foi gerado a partir do nome
- **AND** a conta vem com `email_confirmado` falso

#### Scenario: Falha no meio do cadastro
- **WHEN** o cadastro é recusado por qualquer motivo
- **THEN** nenhuma conta, loja ou sessão fica criada

### Requirement: Validação dos dados do cadastro
O e-mail SHALL ter formato válido, até 254 caracteres, e ser guardado sem espaços nas pontas e em minúsculas. O nome SHALL ter de 2 a 120 caracteres sem contar espaços nas pontas. A senha SHALL ter de 8 a 72 bytes. Dado inválido SHALL ser recusado com `422` e uma mensagem que diga qual campo está errado.

#### Scenario: E-mail em maiúsculas
- **WHEN** o e-mail é enviado como ` Contato@VesteBem.com `
- **THEN** ele é guardado como `contato@vestebem.com`

#### Scenario: Campo inválido
- **WHEN** o e-mail não tem formato válido, o CNPJ não tem 14 caracteres válidos, o nome é curto demais ou a senha tem menos de 8 ou mais de 72 bytes
- **THEN** a resposta é `422` com a mensagem do campo e nada é criado

#### Scenario: Campo ausente
- **WHEN** o corpo não traz algum dos quatro campos
- **THEN** a resposta é `422` e nada é criado

### Requirement: Formato do CNPJ
O CNPJ SHALL ser aceito com ou sem máscara, em letras maiúsculas ou minúsculas, e guardado como 14 caracteres sem máscara e em maiúsculas: as 12 primeiras posições são letras de `A` a `Z` ou dígitos e as 2 últimas são os dígitos verificadores. Para o CNPJ inválido, a mensagem do `422` SHALL mandar conferir os caracteres e os dígitos verificadores.

#### Scenario: CNPJ com máscara
- **WHEN** o CNPJ é enviado como `93.339.970/0001-05`
- **THEN** ele é aceito e guardado como `93339970000105`

#### Scenario: CNPJ alfanumérico
- **WHEN** o CNPJ é enviado como `12.ABC.345/01DE-35`
- **THEN** ele é aceito e guardado como `12ABC34501DE35`

#### Scenario: CNPJ em minúsculas
- **WHEN** o CNPJ é enviado como `12.abc.345/01de-35`
- **THEN** ele é aceito e guardado como `12ABC34501DE35`

#### Scenario: Letra nos dígitos verificadores
- **WHEN** as duas últimas posições do CNPJ não são dígitos, como em `12ABC34501DEAB`
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

### Requirement: Dígitos verificadores do CNPJ
Os dígitos verificadores do CNPJ SHALL ser conferidos (módulo 11, pesos 5,4,3,2,9,8,7,6,5,4,3,2 e 6,5,4,3,2,9,8,7,6,5,4,3,2, com cada caractere valendo o seu código ASCII menos 48). Um CNPJ com os 14 caracteres iguais MUST ser recusado.

#### Scenario: Dígito verificador errado
- **WHEN** o CNPJ é enviado como `12ABC34501DE36` ou como `11222333000180`
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

#### Scenario: CNPJ com todos os caracteres iguais
- **WHEN** o CNPJ é enviado como `00000000000000`, que passaria na conta do dígito verificador
- **THEN** a resposta é `422` com a mensagem do CNPJ e nada é criado

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

### Requirement: Slug da loja gerado do nome
O `slug` SHALL ser gerado do nome: minúsculas, sem acentos, com qualquer sequência de caracteres que não sejam letras ou números trocada por um hífen, sem hífens nas pontas e com até 80 caracteres. O slug MUST ser único: se já existir, SHALL receber o sufixo `-2`, `-3` e assim por diante. Um nome sem nenhuma letra ou número SHALL ser recusado com `422`.

#### Scenario: Nome com acento e espaços
- **WHEN** o nome é `Moda & Cia São João`
- **THEN** o slug é `moda-cia-sao-joao`

#### Scenario: Nome repetido
- **WHEN** duas contas escolhem o nome `Veste Bem` (e `veste-bem` já existe)
- **THEN** a nova loja recebe o slug `veste-bem-2`, e uma terceira recebe `veste-bem-3`

#### Scenario: Nome sem letras nem números
- **WHEN** o nome é `!!!`
- **THEN** a resposta é `422` e nada é criado

### Requirement: Senha nunca em claro
A senha SHALL ser guardada apenas como hash gerado por `password_hash`, e MUST NOT aparecer em nenhuma resposta, nem no banco em texto legível.

#### Scenario: Senha no banco
- **WHEN** a conta é criada com a senha `senha-segura-1`
- **THEN** o banco guarda apenas um hash que não contém a senha e que `password_verify` reconhece

#### Scenario: Respostas da conta
- **WHEN** qualquer resposta traz os dados da conta
- **THEN** ela não contém a senha nem o hash

### Requirement: Dados da conta logada
`GET /api/conta` SHALL, para uma sessão válida, responder `200` com os dados da conta (`email`, `cnpj`, `email_confirmado`) e da loja dela (`nome`, `slug`, `endereco_publico`).

#### Scenario: Consultar a própria conta
- **WHEN** a conta logada consulta `GET /api/conta`
- **THEN** a resposta é `200` com o e-mail, o CNPJ, `email_confirmado` e a loja da própria conta

#### Scenario: Sem sessão
- **WHEN** `GET /api/conta` é chamado sem sessão válida
- **THEN** a resposta é `401`

### Requirement: Trocar a senha
`PUT /api/conta/senha`, com o corpo `{"senha_atual", "nova_senha"}`, SHALL trocar a senha da conta logada quando a senha atual estiver correta e a nova valer as mesmas regras do cadastro, e responder `200`. A senha atual errada SHALL ser recusada com `422` (e não `401`, que significa "sem sessão válida"). Ao trocar, todas as **outras** sessões da conta SHALL ser encerradas e a sessão que fez a troca SHALL continuar válida.

#### Scenario: Troca válida
- **WHEN** a conta logada envia a senha atual certa e uma nova senha válida
- **THEN** a resposta é `200` e o login seguinte só funciona com a nova senha

#### Scenario: Senha atual errada
- **WHEN** a senha atual enviada não confere
- **THEN** a resposta é `422` com "Senha atual incorreta." e a senha não muda

#### Scenario: Nova senha inválida
- **WHEN** a nova senha tem menos de 8 ou mais de 72 bytes
- **THEN** a resposta é `422` e a senha não muda

#### Scenario: Outras sessões encerradas
- **WHEN** a conta tem duas sessões abertas e uma delas troca a senha
- **THEN** a sessão que trocou continua válida e a outra passa a receber `401`
