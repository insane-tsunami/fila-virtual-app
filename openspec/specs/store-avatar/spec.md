# store-avatar Specification

## Purpose
Avatar opcional da loja, uma cor e um ícone escolhidos de uma lista fixa, que a dona define pela API e o dashboard mostra no lugar da inicial do nome.

## Requirements

### Requirement: Cores e ícones do avatar
O avatar da loja SHALL ser uma cor e um ícone escolhidos destas listas fixas. Cores: `roxo`, `azul`, `verde`, `laranja`, `vermelho` e `cinza`. Ícones: `loja`, `mercado`, `restaurante`, `cafe`, `farmacia` e `beleza`. Uma loja sem avatar definido (a loja nova e as que já existiam) SHALL ter o avatar `null`.

#### Scenario: Loja nova
- **WHEN** uma conta é criada pelo cadastro
- **THEN** a loja dela tem o avatar `null`

#### Scenario: Lojas que já existiam
- **WHEN** uma loja criada antes desta mudança é consultada
- **THEN** o avatar dela é `null`

### Requirement: Avatar nas respostas da loja
Toda resposta da API que traz os dados da loja (cadastro, login, `GET /api/conta`, `GET /api/filas/{slug}` e as respostas de definir o endereço e o avatar) SHALL incluir o campo `avatar`, com `null` ou com o objeto `{"cor", "icone"}`.

#### Scenario: Loja sem avatar
- **WHEN** a dona entra e a loja dela não tem avatar
- **THEN** a resposta do login traz a loja com `avatar` igual a `null`

#### Scenario: Loja com avatar
- **WHEN** a loja tem a cor `azul` e o ícone `cafe` e alguém consulta `GET /api/filas/{slug}`
- **THEN** a resposta traz `avatar` igual a `{"cor": "azul", "icone": "cafe"}`

### Requirement: Definir o avatar da loja
`PUT /api/filas/{slug}/avatar`, com o corpo `{"cor", "icone"}`, SHALL gravar o avatar da loja e responder `200` com os dados da loja. O corpo `{"cor": null, "icone": null}` SHALL apagar o avatar, que volta a `null`. A chamada MUST seguir as regras de `account-session`: exigir uma sessão válida e só valer para a loja da conta logada.

#### Scenario: Escolher um avatar
- **WHEN** a dona envia `{"cor": "verde", "icone": "restaurante"}` com uma sessão válida
- **THEN** a resposta é `200` com `avatar` igual a `{"cor": "verde", "icone": "restaurante"}`
- **AND** o login e `GET /api/conta` seguintes trazem o mesmo avatar

#### Scenario: Voltar à inicial
- **WHEN** a dona com avatar definido envia `{"cor": null, "icone": null}`
- **THEN** a resposta é `200` com `avatar` igual a `null`

#### Scenario: Sem sessão, loja de outra conta ou loja inexistente
- **WHEN** o avatar é definido sem sessão válida, para a loja de outra conta ou para um `slug` que não existe
- **THEN** a resposta é `401`, `403` ou `404`, respectivamente, e nenhum avatar muda

### Requirement: Validação do avatar
A cor e o ícone MUST vir juntos e ambos das listas fixas, ou ambos `null`. Qualquer outro corpo SHALL ser recusado com `422` e a mensagem "Escolha uma cor e um ícone da lista.", mantendo o avatar anterior.

#### Scenario: Valores recusados
- **WHEN** a dona envia uma cor ou um ícone fora da lista, só a cor ou só o ícone, um deles `null` e o outro não, um valor que não é texto ou um corpo sem os campos
- **THEN** a resposta é `422` com "Escolha uma cor e um ícone da lista."
- **AND** o avatar anterior da loja continua o mesmo

#### Scenario: Diferença de maiúsculas
- **WHEN** a dona envia `{"cor": "Azul", "icone": "CAFE"}`
- **THEN** a resposta é `422`, porque os valores da lista são em minúsculas
