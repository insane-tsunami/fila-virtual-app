## ADDED Requirements

### Requirement: Sair da fila
Enquanto a entrada estiver ativa, a página SHALL oferecer "Sair da fila". Ao acionar, SHALL pedir a saída à API pelo código guardado e, se a API aceitar, apagar o código e voltar ao formulário. Se a API não responder, SHALL manter a entrada na tela e mostrar "Não foi possível falar com o servidor. Tente de novo.".

#### Scenario: Sair da fila aguardando
- **WHEN** o visitante está aguardando e aciona "Sair da fila"
- **THEN** a página volta ao formulário
- **AND** o código guardado é apagado

#### Scenario: Sair em atendimento
- **WHEN** o visitante é o próximo a ser atendido e aciona "Sair da fila"
- **THEN** a página volta ao formulário e o código guardado é apagado

#### Scenario: API fora do ar ao sair
- **WHEN** o visitante aciona "Sair da fila" e a API não responde
- **THEN** a página mantém a entrada e mostra "Não foi possível falar com o servidor. Tente de novo."

#### Scenario: Entrada encerrada
- **WHEN** a API informa que a entrada foi finalizada ou cancelada
- **THEN** a página não mostra "Sair da fila"
