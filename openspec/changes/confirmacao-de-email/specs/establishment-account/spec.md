# Spec Delta

## MODIFIED Requirements

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

### Requirement: Dados da conta logada
`GET /api/conta` SHALL, para uma sessão válida, responder `200` com os dados da conta (`email`, `cnpj`, `email_confirmado`) e da loja dela (`nome`, `slug`, `endereco_publico`).

#### Scenario: Consultar a própria conta
- **WHEN** a conta logada consulta `GET /api/conta`
- **THEN** a resposta é `200` com o e-mail, o CNPJ, `email_confirmado` e a loja da própria conta

#### Scenario: Sem sessão
- **WHEN** `GET /api/conta` é chamado sem sessão válida
- **THEN** a resposta é `401`
