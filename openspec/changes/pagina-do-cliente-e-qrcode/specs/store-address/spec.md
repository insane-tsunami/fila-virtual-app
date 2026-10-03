# Spec Delta

## Purpose

Expõe os dados públicos de uma loja e o endereço público que ela configura, usado para montar o link do QR code de entrada na fila virtual.

## ADDED Requirements

### Requirement: Dados públicos da loja
`GET /api/filas/{slug}` SHALL devolver `nome`, `slug` e `endereco_publico` do estabelecimento, sem exigir chave. `endereco_publico` SHALL ser `null` quando a loja não tiver configurado um endereço.

#### Scenario: Loja sem endereço configurado
- **WHEN** um visitante consulta os dados de uma loja que não configurou o endereço
- **THEN** a resposta é `200` com `nome`, `slug` e `endereco_publico` igual a `null`

#### Scenario: Loja com endereço configurado
- **WHEN** um visitante consulta os dados de uma loja que configurou `https://loja.exemplo.com`
- **THEN** a resposta é `200` com `endereco_publico` igual a `https://loja.exemplo.com`

#### Scenario: Loja inexistente
- **WHEN** alguém consulta um `slug` que não existe
- **THEN** a resposta é `404`

#### Scenario: Consulta sem chave
- **WHEN** a consulta é feita sem o cabeçalho `X-API-Key`
- **THEN** ela funciona normalmente

### Requirement: Definir o endereço público da loja
`PUT /api/filas/{slug}/endereco`, com o corpo `{"endereco_publico": "<url>"}`, SHALL gravar o endereço da loja e responder `200` com os dados públicos da loja. O valor `null` ou vazio SHALL limpar o endereço. A chamada MUST seguir a regra da chave de acesso provisória.

#### Scenario: Definir um endereço válido
- **WHEN** o dashboard envia `{"endereco_publico": "https://loja.exemplo.com"}` com a chave correta
- **THEN** a resposta é `200` com esse endereço
- **AND** a consulta seguinte dos dados da loja devolve o mesmo endereço

#### Scenario: Barra final é removida
- **WHEN** o dashboard envia `https://loja.exemplo.com/`
- **THEN** o endereço gravado e devolvido é `https://loja.exemplo.com`

#### Scenario: Limpar o endereço
- **WHEN** o dashboard envia `{"endereco_publico": null}` ou uma string vazia
- **THEN** a resposta é `200` com `endereco_publico` igual a `null` e a loja volta a não ter endereço configurado

#### Scenario: Loja inexistente
- **WHEN** o dashboard define o endereço de um `slug` que não existe
- **THEN** a resposta é `404`

### Requirement: Validação do endereço público
O endereço MUST ser apenas uma origem: esquema `http` ou `https`, com host e porta opcional, sem usuário nem senha, sem caminho, sem query string e sem fragmento, e com até 255 caracteres. Qualquer outro valor SHALL ser recusado com `422`, mantendo o endereço anterior.

#### Scenario: Origens aceitas
- **WHEN** o dashboard envia `http://localhost:3000` ou `https://loja.exemplo.com:8443`
- **THEN** o endereço é gravado como enviado

#### Scenario: Valores recusados
- **WHEN** o dashboard envia `ftp://loja.exemplo.com`, `loja.exemplo.com`, `javascript:alert(1)`, `https://usuario:senha@loja.exemplo.com`, `https://loja.exemplo.com/caminho`, `https://loja.exemplo.com?a=1`, um texto com mais de 255 caracteres ou um valor que não é texto
- **THEN** a resposta é `422` com uma mensagem de erro
- **AND** o endereço anterior da loja permanece como estava
