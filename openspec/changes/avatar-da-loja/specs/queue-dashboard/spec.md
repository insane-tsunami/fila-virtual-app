## MODIFIED Requirements

### Requirement: Identificação da loja da conta
Todas as páginas do dashboard SHALL exibir, na barra lateral, o avatar e o nome da loja da conta logada, com o avatar no tamanho padrão da barra. Com o avatar da loja definido, a barra SHALL mostrar o ícone escolhido sobre a cor escolhida; sem avatar (ou com um valor desconhecido), SHALL mostrar a inicial do nome, como padrão. O dashboard MUST NOT usar um nome de loja fixo no código.

#### Scenario: Navegação entre páginas do dashboard
- **WHEN** a dona navega entre Dashboard, Gerar QRCODE e Configurações
- **THEN** a barra lateral mostra o mesmo nome e o mesmo avatar em todas as páginas
- **AND** o avatar tem o mesmo tamanho em todas elas

#### Scenario: Nome da loja da conta
- **WHEN** a dona da loja `Moda Azul`, sem avatar definido, abre o dashboard
- **THEN** a barra lateral mostra `Moda Azul` e a inicial `M`

#### Scenario: Loja com avatar
- **WHEN** a dona da loja `Moda Azul`, com a cor `azul` e o ícone `cafe`, abre o dashboard
- **THEN** a barra lateral mostra `Moda Azul` e o ícone de café sobre azul, no lugar da inicial

#### Scenario: Avatar desconhecido
- **WHEN** a sessão traz um avatar com uma cor ou um ícone que o dashboard não conhece
- **THEN** a barra lateral mostra a inicial, sem quebrar

#### Scenario: Contas diferentes, lojas diferentes
- **WHEN** duas contas com lojas diferentes entram uma depois da outra no mesmo navegador
- **THEN** cada uma vê apenas o nome, o avatar, a fila e o QR code da própria loja
