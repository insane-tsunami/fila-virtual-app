# Spec Delta

## ADDED Requirements

### Requirement: Limite no reenvio e na troca de e-mail
`POST /api/conta/email/reenviar` e `PUT /api/conta/email` SHALL aceitar, juntos, no máximo 3 envios de confirmação por hora por conta, contando todas as chamadas que chegam a enviar. A chamada que passar do limite MUST ser recusada com `429` e sem enviar e-mail. As senhas atuais erradas na troca de e-mail SHALL contar no mesmo limite de erros de senha da conta que vale para a troca de senha.

#### Scenario: Quarto envio na hora
- **WHEN** a mesma conta faz 3 reenvios ou trocas na hora e depois um quarto
- **THEN** o quarto responde `429` e nenhum e-mail novo é enviado

#### Scenario: Senha errada na troca de e-mail
- **WHEN** a conta erra a senha atual 5 vezes entre trocas de e-mail e troca de senha, e depois tenta de novo com a senha certa
- **THEN** a chamada responde `429` e o e-mail não muda

#### Scenario: Outra conta não é afetada
- **WHEN** uma conta esgotou o limite do reenvio e outra conta pede o reenvio
- **THEN** a chamada da outra conta responde `202`

### Requirement: Limite de tokens inválidos na confirmação
`POST /api/email/confirmar` SHALL bloquear por 15 minutos o IP que acumular 20 tentativas com token inválido dentro de uma janela de 15 minutos. Enquanto bloqueado, a chamada MUST responder `429`, mesmo com um token válido. Só as tentativas com token inválido contam; a confirmação concluída MUST NOT contar.

#### Scenario: Vigésimo primeiro token inválido
- **WHEN** um IP envia 20 tokens inválidos e depois uma 21ª chamada, mesmo com token válido
- **THEN** a 21ª resposta é `429` e o e-mail não é confirmado

#### Scenario: Confirmação concluída não conta
- **WHEN** um IP confirma um e-mail com um token válido
- **THEN** nenhum contador de erros do IP é incrementado

### Requirement: Limites da confirmação configuráveis
Os limites da confirmação SHALL poder ser configurados por `RATE_LIMIT_CONFIRMACAO_CONTA`, `RATE_LIMIT_CONFIRMACAO_JANELA_MIN` e `RATE_LIMIT_CONFIRMAR_IP`, com os padrões de 3 envios por conta na janela de 60 minutos e 20 tokens inválidos por IP na janela de 15 minutos. Valor que não seja um inteiro positivo SHALL ser ignorado e o padrão usado. As telas da confirmação SHALL mostrar a mensagem do `429` no mesmo lugar dos outros erros.

#### Scenario: Limite configurado
- **WHEN** `RATE_LIMIT_CONFIRMACAO_CONTA` está configurada em 1
- **THEN** o segundo envio da mesma conta na janela já responde `429`

#### Scenario: Valor inválido
- **WHEN** uma dessas variáveis tem `0`, valor negativo ou texto
- **THEN** o padrão daquele limite é usado
