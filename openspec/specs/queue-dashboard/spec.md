# queue-dashboard Specification

## Purpose

Painel do estabelecimento para acompanhar a fila virtual de clientes, ver quem está em atendimento e finalizar o atendimento para chamar o próximo.

## Requirements

### Requirement: Exibição da fila
O dashboard SHALL listar os clientes da fila vindos da API, na ordem da fila, mostrando a posição e o telefone mascarado de cada um, e SHALL destacar o primeiro como em atendimento e o segundo como próximo. O dashboard MUST NOT exibir clientes fictícios.

#### Scenario: Fila com vários clientes
- **WHEN** o estabelecimento abre o dashboard e a API devolve clientes na fila
- **THEN** cada cliente aparece com sua posição e telefone mascarado
- **AND** o primeiro cliente aparece no painel "Em Atendimento"
- **AND** o segundo cliente aparece destacado como próximo

#### Scenario: Telefone mascarado
- **WHEN** a fila é exibida
- **THEN** nenhum telefone aparece completo; cada um aparece como recebido da API, no formato `*****` mais os quatro últimos dígitos

### Requirement: Finalizar atendimento
O dashboard SHALL permitir finalizar o atendimento atual chamando a API para o cliente em atendimento e, depois da resposta, mostrar a fila atualizada: o cliente atual sai, o seguinte passa a em atendimento e a ordem dos demais não muda. Enquanto a chamada estiver em andamento, "Finalizar Atendimento" MUST NOT disparar uma segunda finalização.

#### Scenario: Finalizar com clientes aguardando
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e há mais clientes na fila
- **THEN** o cliente atual sai da fila
- **AND** o antigo próximo passa a ser o cliente em atendimento
- **AND** o cliente seguinte passa a ser o próximo

#### Scenario: Finalizar o último cliente
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e só há o cliente atual
- **THEN** a fila fica vazia
- **AND** o painel "Em Atendimento" mostra a mensagem de que todos os clientes foram atendidos

#### Scenario: Atendimento já finalizado em outro lugar
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e a API responde `409` porque aquela entrada não está mais em atendimento
- **THEN** o dashboard recarrega a fila e mostra o estado atual, sem finalizar mais ninguém

#### Scenario: Falha ao finalizar
- **WHEN** o estabelecimento aciona "Finalizar Atendimento" e a API não responde
- **THEN** o dashboard mantém a fila como estava e mostra "Não foi possível falar com o servidor. Tente de novo."

### Requirement: Estados vazios sem ruído visual
O dashboard SHALL tratar a fila vazia ou sem clientes aguardando exibindo apenas mensagens de texto, e MUST NOT exibir valores soltos como `0` na tela.

#### Scenario: Nenhum cliente na fila
- **WHEN** a fila não tem nenhum cliente
- **THEN** o painel "Fila" não exibe lista nem o número `0`
- **AND** o painel "Em Atendimento" exibe a mensagem de que todos os clientes foram atendidos

#### Scenario: Apenas o cliente atual na fila
- **WHEN** a fila tem somente o cliente em atendimento
- **THEN** o painel "Fila" exibe a mensagem "Fila vazia" abaixo desse cliente

### Requirement: Atualização automática da fila
Enquanto o dashboard estiver aberto, ele SHALL consultar a fila na API a cada 5 segundos e atualizar o que mostra, sem iniciar uma consulta enquanto a anterior ainda não terminou, e SHALL parar de consultar ao sair da página.

#### Scenario: Cliente novo entra na fila
- **WHEN** um cliente entra na fila pela página pública enquanto o dashboard está aberto
- **THEN** o dashboard mostra o novo cliente na fila em até 5 segundos, sem o estabelecimento fazer nada

#### Scenario: Consulta lenta
- **WHEN** uma consulta ainda não respondeu quando o intervalo seguinte termina
- **THEN** nenhuma nova consulta é iniciada até a anterior terminar

#### Scenario: Sair da página
- **WHEN** o estabelecimento navega para outra página do dashboard
- **THEN** o dashboard deixa de consultar a fila

### Requirement: Falhas de comunicação do dashboard
Se a consulta da fila falhar por falta de resposta da API, o dashboard SHALL manter a última fila exibida, avisar "Não foi possível atualizar a fila. Tentando de novo..." e tentar de novo na consulta seguinte, retirando o aviso quando a API responder.

#### Scenario: Falha e recuperação
- **WHEN** uma consulta periódica falha e a seguinte responde
- **THEN** a última fila ficou na tela durante a falha, com o aviso
- **AND** quando a API responde, a fila é atualizada e o aviso some

#### Scenario: Primeira carga sem resposta
- **WHEN** a primeira consulta da fila falha
- **THEN** o dashboard mostra o aviso de falha, não mostra clientes e tenta de novo na consulta seguinte

### Requirement: Identificação da loja da conta
Todas as páginas do dashboard SHALL exibir, na barra lateral, a inicial e o nome da loja da conta logada, com o avatar no tamanho padrão da barra. O dashboard MUST NOT usar um nome de loja fixo no código.

#### Scenario: Navegação entre páginas do dashboard
- **WHEN** a dona navega entre Dashboard, Gerar QRCODE e Configurações
- **THEN** a barra lateral mostra o mesmo nome e a mesma inicial em todas as páginas
- **AND** o avatar tem o mesmo tamanho em todas elas

#### Scenario: Nome da loja da conta
- **WHEN** a dona da loja `Moda Azul` abre o dashboard
- **THEN** a barra lateral mostra `Moda Azul` e a inicial `M`

#### Scenario: Contas diferentes, lojas diferentes
- **WHEN** duas contas com lojas diferentes entram uma depois da outra no mesmo navegador
- **THEN** cada uma vê apenas o nome, a fila e o QR code da própria loja
