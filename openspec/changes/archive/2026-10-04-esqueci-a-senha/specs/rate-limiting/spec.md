# Spec Delta

## ADDED Requirements

### Requirement: Limite nos pedidos de redefinição de senha
`POST /api/senha/esqueci` SHALL aceitar no máximo 3 pedidos por hora por e-mail e 10 por hora por IP, contando todos os pedidos, inclusive os de e-mail sem conta. O pedido que passar do limite MUST ser recusado com `429`, sem enviar e-mail, e o bloqueio por e-mail SHALL ser igual para e-mails com e sem conta. A resposta `429` SHALL seguir o formato definido para o limite excedido.

#### Scenario: Quarto pedido do mesmo e-mail
- **WHEN** o mesmo e-mail faz 3 pedidos na hora e depois um quarto
- **THEN** o quarto responde `429` e nenhum e-mail novo é enviado

#### Scenario: E-mail sem conta bloqueia igual
- **WHEN** um e-mail sem conta faz 3 pedidos e depois um quarto
- **THEN** o quarto responde `429`, como no caso de um e-mail com conta

#### Scenario: Muitos pedidos do mesmo IP
- **WHEN** um IP faz 10 pedidos com e-mails diferentes e depois um 11º
- **THEN** o 11º responde `429`

### Requirement: Limite de tokens inválidos na redefinição
`POST /api/senha/redefinir` SHALL bloquear por 15 minutos o IP que acumular 20 tentativas com token inválido dentro de uma janela de 15 minutos. Enquanto bloqueado, a chamada MUST responder `429`, mesmo com um token válido. Só as tentativas com token inválido contam; a redefinição concluída ou recusada por senha inválida MUST NOT contar.

#### Scenario: Vigésimo primeiro token inválido
- **WHEN** um IP envia 20 tokens inválidos e depois uma 21ª chamada, mesmo com token válido
- **THEN** a 21ª resposta é `429` e a senha não muda

#### Scenario: Senha inválida não conta
- **WHEN** um IP envia 21 redefinições com token válido e senha curta demais
- **THEN** todas as respostas são `422` e nenhum bloqueio é aplicado

### Requirement: Limites da redefinição configuráveis
Os limites da redefinição SHALL poder ser configurados por `RATE_LIMIT_ESQUECI_EMAIL`, `RATE_LIMIT_ESQUECI_IP`, `RATE_LIMIT_ESQUECI_JANELA_MIN` e `RATE_LIMIT_REDEFINIR_IP`, com os padrões de 3 pedidos por e-mail, 10 por IP, janela de 60 minutos e 20 tokens inválidos por IP na janela de 15 minutos. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado. As telas da redefinição SHALL mostrar a mensagem do `429` no mesmo lugar dos outros erros.

#### Scenario: Limite do pedido alterado
- **WHEN** `RATE_LIMIT_ESQUECI_EMAIL` está configurada em 1
- **THEN** o segundo pedido do mesmo e-mail na janela já responde `429`

#### Scenario: Valor inválido
- **WHEN** uma dessas variáveis tem `0`, valor negativo ou texto
- **THEN** o padrão daquele limite é usado

#### Scenario: Tela bloqueada
- **WHEN** a API responde `429` ao pedido ou à redefinição
- **THEN** a tela mostra a mensagem recebida e continua na mesma tela
