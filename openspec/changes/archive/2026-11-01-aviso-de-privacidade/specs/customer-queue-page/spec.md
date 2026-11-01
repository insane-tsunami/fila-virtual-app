## ADDED Requirements

### Requirement: Aviso de privacidade na fila
O formulário de entrada SHALL mostrar, sob o campo de telefone, a finalidade do número, o prazo de apagamento e o link para `/privacidade`. A ausência da configuração do aviso MUST NOT impedir a entrada na fila.

#### Scenario: Aviso visível
- **WHEN** o visitante abre a página pública da fila
- **THEN** vê o aviso curto com o prazo de apagamento e o link para o aviso completo

#### Scenario: API do aviso fora do ar
- **WHEN** `GET /api/privacidade` falha e o visitante envia um telefone válido
- **THEN** a página continua a entrar na fila normalmente
