## MODIFIED Requirements

### Requirement: Acompanhamento automático da posição
Enquanto a entrada estiver ativa, a página SHALL consultar a posição na API a cada 5 segundos e atualizar o que mostra, e SHALL parar de consultar quando a entrada for finalizada ou cancelada. Nos dois casos, SHALL oferecer "Entrar na fila de novo", que volta ao formulário e apaga o código guardado.

#### Scenario: A fila anda
- **WHEN** o visitante está na posição 3 e a API passa a devolver a posição 2
- **THEN** a página passa a mostrar "Sua posição: 2" sem o visitante fazer nada

#### Scenario: Atendimento finalizado
- **WHEN** a API informa que a entrada foi finalizada
- **THEN** a página mostra "Atendimento finalizado" e para de consultar
- **AND** o botão "Entrar na fila de novo" volta ao formulário e apaga o código guardado

#### Scenario: Entrada cancelada
- **WHEN** a API informa que a entrada foi cancelada
- **THEN** a página mostra "Entrada encerrada" e "Sua entrada foi encerrada sem atendimento." e para de consultar
- **AND** o botão "Entrar na fila de novo" volta ao formulário e apaga o código guardado

#### Scenario: Reabrir com a entrada cancelada
- **WHEN** o visitante reabre a página e o código guardado é de uma entrada cancelada
- **THEN** a página mostra "Entrada encerrada" sem consultar de novo
