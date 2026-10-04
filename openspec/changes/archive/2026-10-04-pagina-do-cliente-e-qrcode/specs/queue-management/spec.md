# Spec Delta

## MODIFIED Requirements

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
