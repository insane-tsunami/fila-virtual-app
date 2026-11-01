## ADDED Requirements

### Requirement: Sair da fila pela própria entrada
`DELETE /api/filas/{slug}/entradas/{codigo}` SHALL encerrar a entrada ativa como `cancelado`, gravar a hora do encerramento e apagar o telefone. Se a entrada estava `em_atendimento`, a ativa mais antiga da loja SHALL passar a `em_atendimento`. Para uma entrada já `finalizado` ou `cancelado`, SHALL responder `200` com o estado atual sem alterar nada. Um `codigo` inexistente na loja SHALL responder `404`.

#### Scenario: Sair aguardando
- **WHEN** o cliente que está `aguardando` chama a saída com o próprio código
- **THEN** a entrada passa a `cancelado` com a hora do encerramento e `posicao` `null`
- **AND** o telefone dela é apagado

#### Scenario: Sair em atendimento promove a próxima
- **WHEN** a entrada em atendimento sai e há outra ativa na loja
- **THEN** a que saiu passa a `cancelado` e a ativa mais antiga passa a `em_atendimento`

#### Scenario: Sair de novo
- **WHEN** o cliente chama a saída para uma entrada que já está encerrada
- **THEN** a resposta é `200` com o estado atual e nada muda

#### Scenario: Código inexistente
- **WHEN** a saída usa um `codigo` que não existe nessa loja
- **THEN** a resposta é `404`

#### Scenario: Telefone apagado depois da saída
- **WHEN** o cliente consulta pelo código uma entrada da qual saiu
- **THEN** a resposta continua a trazer `codigo`, `status` `cancelado` e `posicao` `null`, sem o telefone
