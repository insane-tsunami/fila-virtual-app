# data-retention Specification

## Purpose

Prazos de guarda dos dados pessoais e do que vence: um comando de limpeza, rodado pela hospedagem, encerra as entradas esquecidas na fila, apaga o telefone das entradas antigas e remove sessões, tokens e contadores vencidos.

## Requirements

### Requirement: Comando de limpeza
O comando `php bin/limpar` SHALL aplicar todas as regras de retenção numa execução e mostrar quantas entradas encerrou, quantos telefones apagou e quantas sessões, tokens e contadores removeu. Rodar de novo logo em seguida SHALL não mudar nada. Num banco com migrações pendentes (ou sem tabelas), o comando SHALL sair com erro e pedir `php bin/migrate`, sem alterar nada.

#### Scenario: Limpeza com dados vencidos
- **WHEN** há entradas esquecidas, telefones antigos e sessões vencidas e o comando roda
- **THEN** ele aplica as regras, mostra as contagens e sai com sucesso

#### Scenario: Segunda execução
- **WHEN** o comando roda duas vezes seguidas
- **THEN** a segunda mostra zero em todas as contagens

#### Scenario: Migrações pendentes
- **WHEN** o comando roda num banco vazio ou em que falta aplicar uma migração
- **THEN** ele sai com erro, pede `php bin/migrate` e não altera nada

### Requirement: Encerrar entradas esquecidas
O comando SHALL marcar como `cancelado` toda entrada `aguardando` parada há mais do que o prazo desde que entrou e toda entrada `em_atendimento` parada há mais do que o prazo desde que começou a ser atendida, gravando a hora do encerramento. Se a entrada encerrada estava em atendimento, a entrada ativa mais antiga da loja SHALL passar a `em_atendimento`. O prazo padrão é de 24 horas.

#### Scenario: Loja fechou sem finalizar
- **WHEN** uma loja tem uma entrada em atendimento há 25 horas e uma aguardando há 25 horas
- **THEN** as duas passam a `cancelado` e a fila da loja fica vazia

#### Scenario: Promove a próxima
- **WHEN** a entrada em atendimento está parada há 25 horas e a seguinte entrou há 1 hora
- **THEN** a primeira passa a `cancelado` e a seguinte passa a `em_atendimento`

#### Scenario: Entrada dentro do prazo
- **WHEN** uma entrada está aguardando há 23 horas
- **THEN** ela continua `aguardando`

### Requirement: Apagar o telefone das entradas encerradas
O comando SHALL apagar o telefone (deixando-o vazio) de toda entrada `finalizado` ou `cancelado` encerrada há mais do que o prazo, mantendo o código, o status e as datas. O prazo padrão é de 30 dias. O comando MUST NOT apagar o telefone de entradas ativas nem apagar entradas.

#### Scenario: Entrada finalizada há 31 dias
- **WHEN** uma entrada foi finalizada há 31 dias
- **THEN** o telefone dela fica vazio e o código, o status e as datas continuam iguais

#### Scenario: Entrada finalizada há 29 dias
- **WHEN** uma entrada foi finalizada há 29 dias
- **THEN** o telefone dela continua gravado

#### Scenario: Consulta depois do apagamento
- **WHEN** o cliente consulta pelo código uma entrada cujo telefone foi apagado
- **THEN** a resposta continua a trazer o código, o status e `posicao` `null`

### Requirement: Remover sessões, tokens e contadores vencidos
O comando SHALL remover as sessões vencidas, os tokens de redefinição de senha e de confirmação de e-mail vencidos e os contadores de tentativas vencidos. O que ainda vale MUST continuar a funcionar. O comando MUST NOT apagar contas, nem as não confirmadas.

#### Scenario: Sessão vencida e sessão válida
- **WHEN** há uma sessão vencida e outra válida e o comando roda
- **THEN** só a vencida é removida e a válida continua a dar acesso

#### Scenario: Token de confirmação ainda válido
- **WHEN** um token de confirmação de e-mail ainda vale e o comando roda
- **THEN** o link de confirmação continua a funcionar

#### Scenario: Conta não confirmada com prazo vencido
- **WHEN** uma conta não confirmada passou do prazo de confirmação e o comando roda
- **THEN** a conta e a loja dela continuam no banco

### Requirement: Prazos configuráveis
Os prazos SHALL vir de `RETENCAO_ENTRADA_ATIVA_HORAS` (padrão `24`) e `RETENCAO_TELEFONE_DIAS` (padrão `30`). Só inteiros positivos SHALL valer; vazio, zero, negativo ou texto SHALL usar o padrão.

#### Scenario: Prazo do telefone menor
- **WHEN** `RETENCAO_TELEFONE_DIAS` é `7` e uma entrada foi finalizada há 8 dias
- **THEN** o comando apaga o telefone dela

#### Scenario: Valor inválido
- **WHEN** `RETENCAO_ENTRADA_ATIVA_HORAS` é `0`
- **THEN** o comando usa o prazo de 24 horas
