# api-platform Specification

## Purpose

Define as convenções comuns da API da fila, o formato JSON das respostas e dos erros e o acesso entre origens, de que o front depende em todas as chamadas.

## Requirements

### Requirement: Respostas e erros em JSON
Todas as respostas da API SHALL usar `Content-Type: application/json`. Erros SHALL trazer o corpo `{"erro": "<mensagem>"}` com o status HTTP adequado, incluindo `404` para rota inexistente e `400` para corpo que não é JSON válido.

#### Scenario: Rota inexistente
- **WHEN** alguém chama uma rota que a API não tem
- **THEN** a resposta é `404` em JSON com o campo `erro`

#### Scenario: Corpo inválido
- **WHEN** o cliente envia a entrada na fila com um corpo que não é JSON válido
- **THEN** a resposta é `400` em JSON com o campo `erro` e nenhuma entrada é criada

#### Scenario: Erro de validação
- **WHEN** uma chamada é recusada por dados inválidos
- **THEN** a resposta traz o status adequado (por exemplo `422`) e `erro` descreve o problema

### Requirement: Acesso entre origens configurável
Quando `CORS_ORIGIN` estiver configurada, a API SHALL incluir `Access-Control-Allow-Origin` com esse valor nas respostas e SHALL responder `204` às requisições de preflight (`OPTIONS`) permitindo os métodos `GET`, `POST`, `PUT` e `OPTIONS` e os cabeçalhos `Content-Type` e `X-API-Key`. Sem `CORS_ORIGIN`, a API MUST NOT enviar cabeçalhos CORS.

#### Scenario: Origem configurada
- **WHEN** `CORS_ORIGIN` está configurada e o navegador envia um preflight para uma rota da API
- **THEN** a resposta é `204` com os cabeçalhos de permissão de origem, métodos (`GET`, `POST`, `PUT` e `OPTIONS`) e cabeçalhos

#### Scenario: Origem não configurada
- **WHEN** `CORS_ORIGIN` não está configurada
- **THEN** nenhuma resposta da API traz cabeçalhos CORS
