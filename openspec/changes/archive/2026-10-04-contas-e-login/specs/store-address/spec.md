# Spec Delta

## MODIFIED Requirements

### Requirement: Dados públicos da loja
`GET /api/filas/{slug}` SHALL devolver `nome`, `slug` e `endereco_publico` do estabelecimento, sem exigir login. `endereco_publico` SHALL ser `null` quando a loja não tiver configurado um endereço.

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
- **WHEN** a consulta é feita sem credencial de login (sem o cabeçalho `Authorization`)
- **THEN** ela funciona normalmente

### Requirement: Definir o endereço público da loja
`PUT /api/filas/{slug}/endereco`, com o corpo `{"endereco_publico": "<url>"}`, SHALL gravar o endereço da loja e responder `200` com os dados públicos da loja. O valor `null` ou vazio SHALL limpar o endereço. A chamada MUST seguir as regras de `account-session`: exigir uma sessão válida e só valer para a loja da conta logada.

#### Scenario: Definir um endereço válido
- **WHEN** a dona da loja envia `{"endereco_publico": "https://loja.exemplo.com"}` com uma sessão válida
- **THEN** a resposta é `200` com esse endereço
- **AND** a consulta seguinte dos dados da loja devolve o mesmo endereço

#### Scenario: Barra final é removida
- **WHEN** a dona da loja envia `https://loja.exemplo.com/`
- **THEN** o endereço gravado e devolvido é `https://loja.exemplo.com`

#### Scenario: Limpar o endereço
- **WHEN** a dona da loja envia `{"endereco_publico": null}` ou uma string vazia
- **THEN** a resposta é `200` com `endereco_publico` igual a `null` e a loja volta a não ter endereço configurado

#### Scenario: Loja inexistente
- **WHEN** uma conta logada define o endereço de um `slug` que não existe
- **THEN** a resposta é `404`

#### Scenario: Sem sessão ou loja de outra conta
- **WHEN** o endereço é definido sem sessão válida, ou para a loja de outra conta
- **THEN** a resposta é `401` ou `403`, respectivamente, e o endereço não muda
