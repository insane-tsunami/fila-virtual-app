# Spec Delta

## Purpose

Rotas e menu lateral que permitem ao estabelecimento circular entre as páginas do dashboard e voltar à página inicial.

## ADDED Requirements

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

### Requirement: Sair sem sessão (estado atual)
Enquanto não houver autenticação, "Sair" MUST apenas navegar para a página inicial, sem encerrar sessão nem limpar dados.

#### Scenario: Acionar "Sair"
- **WHEN** o estabelecimento aciona "Sair"
- **THEN** a aplicação navega para `/` e nenhum dado é apagado
