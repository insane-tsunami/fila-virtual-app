# dashboard-navigation Specification

## Purpose

Rotas e menu lateral que permitem ao estabelecimento circular entre as páginas do dashboard e voltar à página inicial.

## Requirements

### Requirement: Rotas do dashboard
A aplicação SHALL servir o dashboard em `/dashboard`, a geração de QR code em `/dashboard/qrcode` e as configurações em `/dashboard/perfil`.

#### Scenario: Acessar cada rota
- **WHEN** alguém acessa `/dashboard`, `/dashboard/qrcode` ou `/dashboard/perfil`
- **THEN** a página correspondente (Dashboard, Gerar QRCode ou Perfil) é exibida

### Requirement: Menu lateral
Todas as páginas do dashboard SHALL exibir um menu com os itens "Dashboard", "Gerar QRCODE", "Configurações" e "Sair", cada um navegando para `/dashboard`, `/dashboard/qrcode`, `/dashboard/perfil` e `/`, respectivamente.

#### Scenario: Navegar pelo menu
- **WHEN** o estabelecimento aciona um item do menu
- **THEN** a aplicação navega para a rota associada ao item

### Requirement: Sair encerra o acesso ao dashboard
Ao acionar "Sair", o dashboard SHALL apagar a chave de acesso guardada e navegar para a página inicial `/`. Voltar ao dashboard depois disso SHALL pedir a chave de novo.

#### Scenario: Acionar "Sair"
- **WHEN** o estabelecimento aciona "Sair"
- **THEN** a chave guardada é apagada e a aplicação navega para `/`

#### Scenario: Voltar depois de sair
- **WHEN** o estabelecimento acessa `/dashboard` depois de acionar "Sair"
- **THEN** a tela de chave é exibida
