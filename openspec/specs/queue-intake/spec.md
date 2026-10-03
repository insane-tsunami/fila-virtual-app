# queue-intake Specification

## Purpose

Permite que o cliente entre na fila virtual de um estabelecimento informando seu telefone e acompanhe a própria posição, sem acesso aos dados de outras pessoas.

## Requirements

### Requirement: Entrada na fila
O sistema SHALL permitir que um cliente entre na fila de um estabelecimento com `POST /api/filas/{slug}/entradas`, enviando `telefone` em JSON, e SHALL responder `201` com `codigo`, `posicao` e `status` da nova entrada.

#### Scenario: Entrar em uma fila com clientes
- **WHEN** há dois clientes ativos na fila e um novo cliente envia um telefone válido
- **THEN** a resposta é `201` com `posicao` igual a 3 e `status` igual a `aguardando`
- **AND** a resposta traz um `codigo` para consultas futuras

#### Scenario: Entrar em uma fila vazia
- **WHEN** não há ninguém ativo na fila e um cliente envia um telefone válido
- **THEN** a resposta é `201` com `posicao` igual a 1 e `status` igual a `em_atendimento`

#### Scenario: Estabelecimento inexistente
- **WHEN** o cliente envia a entrada para um `slug` que não existe
- **THEN** a resposta é `404` e nenhuma entrada é criada

### Requirement: Validação e normalização do telefone
O telefone MUST ter de 10 a 13 dígitos depois de remover pontuação e espaços. Números com 10 ou 11 dígitos SHALL ser gravados com o DDI `55` na frente; números com 12 ou 13 dígitos MUST começar com `55`. Qualquer outro valor SHALL ser recusado com `422`.

#### Scenario: Telefone formatado sem DDI
- **WHEN** o cliente envia `(11) 97177-8203`
- **THEN** a entrada é criada e o telefone é gravado como `5511971778203`

#### Scenario: Telefone já com DDI
- **WHEN** o cliente envia `+55 11 97177-8203`
- **THEN** a entrada é criada e o telefone é gravado como `5511971778203`

#### Scenario: Telefone inválido
- **WHEN** o cliente envia `123`, um texto sem dígitos ou omite o campo `telefone`
- **THEN** a resposta é `422` com uma mensagem de erro e nenhuma entrada é criada

### Requirement: Sem entrada duplicada para o mesmo telefone
Se o telefone, já normalizado, tem uma entrada ativa (`aguardando` ou `em_atendimento`) no estabelecimento, o sistema SHALL responder `200` com essa entrada e MUST NOT criar outra.

#### Scenario: Mesmo telefone entra de novo
- **WHEN** um telefone que já está aguardando envia a entrada outra vez, mesmo escrito de outra forma
- **THEN** a resposta é `200` com o mesmo `codigo` e a mesma `posicao`
- **AND** a fila continua com uma única entrada desse telefone

#### Scenario: Telefone de um atendimento já finalizado
- **WHEN** um telefone cuja entrada anterior está `finalizado` envia a entrada
- **THEN** uma nova entrada é criada com `201`

### Requirement: Consulta da própria posição
`GET /api/filas/{slug}/entradas/{codigo}` SHALL devolver `codigo`, `posicao` e `status` da entrada. A posição é 1 para quem está em atendimento, 2 para o próximo e assim por diante; para entrada finalizada, `posicao` SHALL ser `null`. A resposta MUST NOT conter o telefone.

#### Scenario: A posição avança quando a fila anda
- **WHEN** um cliente é o terceiro da fila e o atendimento do primeiro é finalizado
- **THEN** a consulta seguinte desse cliente devolve `posicao` igual a 2

#### Scenario: Entrada finalizada
- **WHEN** o cliente consulta uma entrada cujo atendimento foi finalizado
- **THEN** a resposta traz `status` igual a `finalizado` e `posicao` igual a `null`

#### Scenario: Código inexistente ou de outro estabelecimento
- **WHEN** o cliente consulta um `codigo` que não existe nesse estabelecimento
- **THEN** a resposta é `404`

#### Scenario: Resposta sem dados pessoais
- **WHEN** o cliente consulta a própria entrada
- **THEN** a resposta não contém o telefone, nem inteiro nem mascarado

### Requirement: Código de acesso imprevisível
O `codigo` de uma entrada SHALL ser aleatório, com pelo menos 16 caracteres, e MUST NOT ser sequencial nem derivado do telefone, para que uma entrada não possa ser descoberta por tentativa.

#### Scenario: Códigos de entradas consecutivas
- **WHEN** duas entradas são criadas em sequência
- **THEN** seus códigos têm pelo menos 16 caracteres e um não pode ser deduzido a partir do outro
