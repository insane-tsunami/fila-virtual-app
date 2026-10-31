## MODIFIED Requirements

### Requirement: Sem entrada duplicada para o mesmo telefone
Se o telefone, já normalizado, tem uma entrada ativa (`aguardando` ou `em_atendimento`) no estabelecimento, o sistema SHALL responder `200` com essa entrada e MUST NOT criar outra. Entradas `finalizado` ou `cancelado` não contam como ativas.

#### Scenario: Mesmo telefone entra de novo
- **WHEN** um telefone que já está aguardando envia a entrada outra vez, mesmo escrito de outra forma
- **THEN** a resposta é `200` com o mesmo `codigo` e a mesma `posicao`
- **AND** a fila continua com uma única entrada desse telefone

#### Scenario: Telefone de um atendimento já finalizado
- **WHEN** um telefone cuja entrada anterior está `finalizado` envia a entrada
- **THEN** uma nova entrada é criada com `201`

#### Scenario: Telefone de uma entrada cancelada
- **WHEN** um telefone cuja entrada anterior está `cancelado` envia a entrada
- **THEN** uma nova entrada é criada com `201`

### Requirement: Consulta da própria posição
`GET /api/filas/{slug}/entradas/{codigo}` SHALL devolver `codigo`, `posicao` e `status` da entrada. A posição é 1 para quem está em atendimento, 2 para o próximo e assim por diante; para entrada `finalizado` ou `cancelado`, `posicao` SHALL ser `null`. A resposta MUST NOT conter o telefone.

#### Scenario: A posição avança quando a fila anda
- **WHEN** um cliente é o terceiro da fila e o atendimento do primeiro é finalizado
- **THEN** a consulta seguinte desse cliente devolve `posicao` igual a 2

#### Scenario: Entrada finalizada
- **WHEN** o cliente consulta uma entrada cujo atendimento foi finalizado
- **THEN** a resposta traz `status` igual a `finalizado` e `posicao` igual a `null`

#### Scenario: Entrada cancelada
- **WHEN** o cliente consulta uma entrada encerrada sem atendimento
- **THEN** a resposta traz `status` igual a `cancelado` e `posicao` igual a `null`

#### Scenario: Código inexistente ou de outro estabelecimento
- **WHEN** o cliente consulta um `codigo` que não existe nesse estabelecimento
- **THEN** a resposta é `404`

#### Scenario: Resposta sem dados pessoais
- **WHEN** o cliente consulta a própria entrada
- **THEN** a resposta não contém o telefone, nem inteiro nem mascarado
