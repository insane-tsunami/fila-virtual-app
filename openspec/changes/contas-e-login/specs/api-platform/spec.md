# Spec Delta

## MODIFIED Requirements

### Requirement: Acesso entre origens configurável
Quando `CORS_ORIGIN` estiver configurada, a API SHALL incluir `Access-Control-Allow-Origin` com esse valor nas respostas e SHALL responder `204` às requisições de preflight (`OPTIONS`) permitindo os métodos `GET`, `POST`, `PUT`, `DELETE` e `OPTIONS` e os cabeçalhos `Content-Type` e `Authorization`. Sem `CORS_ORIGIN`, a API MUST NOT enviar cabeçalhos CORS.

#### Scenario: Origem configurada
- **WHEN** `CORS_ORIGIN` está configurada e o navegador envia um preflight para uma rota da API
- **THEN** a resposta é `204` com os cabeçalhos de permissão de origem, métodos (`GET`, `POST`, `PUT`, `DELETE` e `OPTIONS`) e cabeçalhos (`Content-Type` e `Authorization`)

#### Scenario: Origem não configurada
- **WHEN** `CORS_ORIGIN` não está configurada
- **THEN** nenhuma resposta da API traz cabeçalhos CORS
