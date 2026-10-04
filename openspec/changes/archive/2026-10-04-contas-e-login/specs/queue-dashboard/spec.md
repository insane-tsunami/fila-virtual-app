# Spec Delta

## REMOVED Requirements

### Requirement: Identificação do estabelecimento
**Reason**: o nome da loja deixa de ser um valor fixo com reserva e passa a vir da conta logada.
**Migration**: o comportamento novo está no requisito "Identificação da loja da conta".

## ADDED Requirements

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
