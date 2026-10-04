# queue-management Specification

## Purpose

Permite que o estabelecimento veja a fila com os telefones protegidos e finalize o atendimento atual para chamar o próximo, com uma chave de acesso provisória até existir login.

## Requirements

### Requirement: Chave de acesso provisória
As chamadas do dashboard (listar a fila, finalizar atendimento e definir o endereço público da loja) SHALL exigir o cabeçalho `X-API-Key` igual ao valor configurado em `API_KEY`, e SHALL responder `401` quando a chave faltar ou for diferente. Se `API_KEY` não estiver configurada, essas chamadas MUST ser recusadas com `401`. As chamadas do cliente e a consulta dos dados públicos da loja MUST NOT exigir a chave. Esta proteção é provisória, até existir login.

#### Scenario: Chave correta
- **WHEN** o dashboard chama a listagem com `X-API-Key` igual a `API_KEY`
- **THEN** a resposta é `200` com a fila

#### Scenario: Chave ausente ou errada
- **WHEN** a listagem, a finalização ou a definição do endereço é chamada sem `X-API-Key` ou com um valor diferente
- **THEN** a resposta é `401`, sem dados da fila e sem alterar nenhuma entrada nem o endereço da loja

#### Scenario: Chave não configurada
- **WHEN** `API_KEY` não está configurada no servidor e o dashboard envia qualquer `X-API-Key`
- **THEN** a resposta é `401`

#### Scenario: Chamadas do cliente
- **WHEN** um cliente entra na fila, consulta a própria posição ou consulta os dados públicos da loja sem enviar `X-API-Key`
- **THEN** as chamadas funcionam normalmente

### Requirement: Listagem da fila com telefone mascarado
`GET /api/filas/{slug}/entradas` SHALL devolver as entradas ativas em ordem de chegada, cada uma com `codigo`, `posicao`, `status` e `telefone` mascarado no formato `*****` mais os quatro últimos dígitos. A resposta MUST NOT conter o telefone completo.

#### Scenario: Fila com três clientes
- **WHEN** o dashboard lista uma fila com três clientes ativos
- **THEN** a resposta traz as três entradas em ordem de chegada, com `posicao` 1, 2 e 3
- **AND** o primeiro tem `status` `em_atendimento` e os demais `aguardando`
- **AND** cada `telefone` aparece como `*****` seguido dos quatro últimos dígitos, por exemplo `*****8203`

#### Scenario: Fila vazia
- **WHEN** não há entradas ativas
- **THEN** a resposta é `200` com uma lista vazia

#### Scenario: Entradas finalizadas não aparecem
- **WHEN** o dashboard lista a fila depois de finalizar atendimentos
- **THEN** as entradas finalizadas não estão na lista

### Requirement: Finalizar o atendimento atual
`POST /api/filas/{slug}/entradas/{codigo}/finalizar` SHALL finalizar a entrada indicada apenas se ela estiver `em_atendimento`: marca-a como `finalizado`, promove a entrada ativa mais antiga a `em_atendimento` e responde `200` com `finalizada` e `atual` (`null` se a fila ficou vazia). Entrada que não está em atendimento MUST ser recusada com `409` sem alterar nada.

#### Scenario: Finalizar com clientes aguardando
- **WHEN** o dashboard finaliza o cliente em atendimento e há outros aguardando
- **THEN** a resposta é `200` e o cliente finalizado passa a `finalizado`
- **AND** o aguardante mais antigo passa a `em_atendimento` e é devolvido em `atual`
- **AND** os demais avançam uma posição, mantendo a ordem de chegada

#### Scenario: Finalizar o último cliente
- **WHEN** o dashboard finaliza o único cliente ativo
- **THEN** a resposta é `200` com `atual` igual a `null` e a fila fica vazia

#### Scenario: Entrada que não está em atendimento
- **WHEN** o dashboard tenta finalizar uma entrada que está `aguardando`
- **THEN** a resposta é `409` e nenhuma entrada muda

#### Scenario: Finalização repetida
- **WHEN** a mesma finalização é enviada duas vezes seguidas, como em um duplo clique
- **THEN** a primeira finaliza o atendimento e a segunda recebe `409`
- **AND** apenas um atendimento foi finalizado e o próximo cliente não foi finalizado por engano

#### Scenario: Entrada inexistente
- **WHEN** o dashboard tenta finalizar um `codigo` que não existe nesse estabelecimento
- **THEN** a resposta é `404`
