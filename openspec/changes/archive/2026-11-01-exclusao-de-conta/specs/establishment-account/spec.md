## ADDED Requirements

### Requirement: Exclusão da conta pela dona
`DELETE /api/conta` com `{ "senha": "..." }` na sessão de uma conta SHALL apagar a conta com a loja, as entradas da fila da loja, as sessões, os tokens de confirmação e de redefinição e os contadores presos à conta, e SHALL responder `200`. Sem sessão válida SHALL responder `401`; com a senha atual errada SHALL responder `422` sem apagar nada. Os contadores por e-mail e por IP MUST ficar.

#### Scenario: Excluir a conta
- **WHEN** a dona envia a senha atual correta em `DELETE /api/conta`
- **THEN** a resposta é `200`
- **AND** a conta, a loja e as entradas da loja deixam de existir

#### Scenario: Senha errada
- **WHEN** a dona envia uma senha atual incorreta
- **THEN** a resposta é `422` e nada é apagado

#### Scenario: Sem sessão
- **WHEN** a exclusão é chamada sem um token válido
- **THEN** a resposta é `401` e nada é apagado

#### Scenario: Sessão deixa de valer
- **WHEN** a conta é excluída e o token antigo é usado de novo
- **THEN** a API responde `401`
