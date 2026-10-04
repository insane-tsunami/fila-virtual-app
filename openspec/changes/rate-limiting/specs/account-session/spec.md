# Spec Delta

## MODIFIED Requirements

### Requirement: Login
`POST /api/sessoes`, com o corpo `{"email", "senha"}`, SHALL abrir uma sessão quando o e-mail (sem diferenciar maiúsculas) e a senha conferem e responder `200` com `token`, `expira_em`, a conta e a loja dela. Credenciais erradas SHALL ser recusadas com `401` e a mesma mensagem "E-mail ou senha incorretos." tanto para e-mail desconhecido quanto para senha errada. A chamada MUST NOT exigir login e SHALL responder `429`, sem abrir sessão, quando o limite de tentativas estiver esgotado (ver a capability `rate-limiting`).

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

#### Scenario: Limite de tentativas esgotado
- **WHEN** o e-mail ou o IP já esgotou o limite de tentativas erradas e o login é chamado, mesmo com a senha certa
- **THEN** a resposta é `429` e nenhuma sessão é aberta
