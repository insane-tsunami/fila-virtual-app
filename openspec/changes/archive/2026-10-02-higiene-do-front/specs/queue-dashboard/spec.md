# Spec Delta

## Purpose

Painel do estabelecimento para acompanhar a fila virtual de clientes, ver quem está em atendimento e finalizar o atendimento para chamar o próximo.

## ADDED Requirements

### Requirement: Exibição da fila
O dashboard SHALL listar os clientes na ordem da fila, mostrando a posição e o telefone mascarado de cada um, e SHALL destacar o primeiro como em atendimento e o segundo como próximo.

#### Scenario: Fila com vários clientes
- **WHEN** o estabelecimento abre o dashboard e há clientes na fila
- **THEN** cada cliente aparece com sua posição e telefone mascarado
- **AND** o primeiro cliente aparece no painel "Em Atendimento"
- **AND** o segundo cliente aparece destacado como próximo

### Requirement: Finalizar atendimento
O dashboard SHALL permitir finalizar o atendimento atual, removendo o cliente da fila e promovendo o seguinte a em atendimento, sem alterar a ordem dos demais.

#### Scenario: Finalizar com clientes aguardando
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e há mais clientes na fila
- **THEN** o cliente atual sai da fila
- **AND** o antigo próximo passa a ser o cliente em atendimento
- **AND** o cliente seguinte passa a ser o próximo

#### Scenario: Finalizar o último cliente
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e só há o cliente atual
- **THEN** a fila fica vazia
- **AND** o painel "Em Atendimento" mostra a mensagem de que todos os clientes foram atendidos

### Requirement: Estados vazios sem ruído visual
O dashboard SHALL tratar a fila vazia ou sem clientes aguardando exibindo apenas mensagens de texto, e MUST NOT exibir valores soltos como `0` na tela.

#### Scenario: Nenhum cliente na fila
- **WHEN** a fila não tem nenhum cliente
- **THEN** o painel "Fila" não exibe lista nem o número `0`
- **AND** o painel "Em Atendimento" exibe a mensagem de que todos os clientes foram atendidos

#### Scenario: Apenas o cliente atual na fila
- **WHEN** a fila tem somente o cliente em atendimento
- **THEN** o painel "Fila" exibe a mensagem "Fila vazia" abaixo desse cliente

### Requirement: Identificação do estabelecimento
Todas as páginas do dashboard SHALL exibir, na barra lateral, a inicial e o nome do mesmo estabelecimento, com o avatar no tamanho padrão da barra.

#### Scenario: Navegação entre páginas do dashboard
- **WHEN** o estabelecimento navega entre Dashboard, Gerar QRCODE e Configurações
- **THEN** a barra lateral mostra o mesmo nome e a mesma inicial em todas as páginas
- **AND** o avatar tem o mesmo tamanho em todas elas
