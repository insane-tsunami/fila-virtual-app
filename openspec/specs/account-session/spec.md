# account-session Specification

## Purpose
Login por e-mail e senha com token de sessão com validade, e a regra de que as rotas do dashboard exigem uma sessão válida e só valem para a loja da própria conta.

## Requirements

### Requirement: Login
`POST /api/sessoes`, com o corpo `{"email", "senha"}`, SHALL abrir uma sessão quando o e-mail (sem diferenciar maiúsculas) e a senha conferem e responder `200` com `token`, `expira_em`, a conta e a loja dela. Credenciais erradas SHALL ser recusadas com `401` e a mesma mensagem "E-mail ou senha incorretos." tanto para e-mail desconhecido quanto para senha errada. A chamada MUST NOT exigir login.

#### Scenario: Credenciais corretas
- **WHEN** a dona envia o e-mail e a senha certos
- **THEN** a resposta é `200` com um `token` novo, a conta e a loja

#### Scenario: Senha errada
- **WHEN** o e-mail existe mas a senha não confere
- **THEN** a resposta é `401` com "E-mail ou senha incorretos." e nenhuma sessão é aberta

#### Scenario: E-mail desconhecido
- **WHEN** o e-mail não tem conta
- **THEN** a resposta é `401` com a mesma mensagem do caso da senha errada

#### Scenario: Vários aparelhos
- **WHEN** a mesma conta faz login duas vezes
- **THEN** as duas sessões ficam válidas ao mesmo tempo, com tokens diferentes

### Requirement: Token de sessão
O token SHALL ser aleatório e imprevisível, com pelo menos 256 bits, e o servidor SHALL guardar apenas um hash dele, nunca o token. Cada sessão SHALL valer por 7 dias a partir da criação, sem renovação. Token expirado, encerrado ou desconhecido SHALL ser tratado como ausente.

#### Scenario: Token no banco
- **WHEN** uma sessão é aberta
- **THEN** o banco não contém o valor do token devolvido, só o seu hash

#### Scenario: Sessão expirada
- **WHEN** uma chamada usa um token cuja validade já passou
- **THEN** a resposta é `401`

### Requirement: Rotas do dashboard exigem sessão
`GET /api/filas/{slug}/entradas`, `POST /api/filas/{slug}/entradas/{codigo}/finalizar`, `PUT /api/filas/{slug}/endereco`, `GET /api/conta`, `PUT /api/conta/senha` e `DELETE /api/sessao` SHALL exigir o cabeçalho `Authorization: Bearer <token>` com uma sessão válida, e SHALL responder `401`, sem dados e sem alterar nada, quando ele faltar ou não valer. Essas rotas MUST NOT aceitar a antiga `X-API-Key`. As chamadas do cliente (entrar na fila, consultar a posição, dados públicos da loja), o cadastro e o login MUST NOT exigir sessão.

#### Scenario: Token válido
- **WHEN** a dona chama a listagem da própria loja com um token válido
- **THEN** a resposta é `200` com a fila

#### Scenario: Sem token ou token inválido
- **WHEN** qualquer rota protegida é chamada sem `Authorization`, com um token desconhecido ou com um esquema diferente de `Bearer`
- **THEN** a resposta é `401` e nada é alterado

#### Scenario: A chave antiga não vale mais
- **WHEN** uma rota protegida é chamada com `X-API-Key` e sem `Authorization`
- **THEN** a resposta é `401`

#### Scenario: Chamadas públicas
- **WHEN** um cliente entra na fila, consulta a posição ou consulta os dados da loja sem enviar `Authorization`
- **THEN** as chamadas funcionam normalmente

### Requirement: Só a loja da própria conta
As rotas do dashboard que levam `{slug}` SHALL só valer para a loja da conta logada: um `slug` de loja que existe mas pertence a outra conta, ou que não tem dono, SHALL ser recusado com `403` sem revelar nem alterar nada; um `slug` inexistente SHALL responder `404`.

#### Scenario: Loja de outra conta
- **WHEN** a conta A lista a fila, finaliza um atendimento ou define o endereço da loja da conta B
- **THEN** a resposta é `403` e nada muda na loja da conta B

#### Scenario: Loja sem dono
- **WHEN** uma conta logada chama uma rota do dashboard de uma loja que não tem dono (como a `veste-bem` original)
- **THEN** a resposta é `403`

#### Scenario: Loja inexistente
- **WHEN** uma conta logada chama uma rota do dashboard com um `slug` que não existe
- **THEN** a resposta é `404`

### Requirement: Sair
`DELETE /api/sessao` SHALL encerrar a sessão do token usado e responder `204`. O token encerrado MUST passar a receber `401`, e as outras sessões da conta MUST continuar válidas.

#### Scenario: Sair
- **WHEN** a dona chama `DELETE /api/sessao` com um token válido
- **THEN** a resposta é `204` e o mesmo token passa a receber `401`

#### Scenario: Outras sessões seguem válidas
- **WHEN** a conta tem duas sessões e uma delas sai
- **THEN** a outra continua válida
