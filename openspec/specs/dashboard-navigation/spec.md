# dashboard-navigation Specification

## Purpose

Rotas e menu lateral que permitem ao estabelecimento circular entre as páginas do dashboard e voltar à página inicial.

## Requirements

### Requirement: Rotas do dashboard
A aplicação SHALL servir o dashboard em `/dashboard`, a geração de QR code em `/dashboard/qrcode` e as configurações em `/dashboard/perfil`, todas exigindo sessão válida (ver `establishment-login`).

#### Scenario: Acessar cada rota
- **WHEN** a dona logada acessa `/dashboard`, `/dashboard/qrcode` ou `/dashboard/perfil`
- **THEN** a página correspondente (Dashboard, Gerar QRCode ou Perfil) é exibida

#### Scenario: Acessar cada rota sem login
- **WHEN** alguém sem sessão acessa uma dessas rotas
- **THEN** a aplicação navega para `/login`

### Requirement: Menu lateral
Todas as páginas do dashboard SHALL exibir um menu com os itens "Dashboard", "Gerar QRCODE", "Configurações" e "Sair", cada um navegando para `/dashboard`, `/dashboard/qrcode`, `/dashboard/perfil` e `/`, respectivamente.

#### Scenario: Navegar pelo menu
- **WHEN** o estabelecimento aciona um item do menu
- **THEN** a aplicação navega para a rota associada ao item

### Requirement: Sair encerra a sessão
Ao acionar "Sair", o dashboard SHALL pedir à API que encerre a sessão, apagar o token guardado e navegar para a página inicial `/`, mesmo que a API não responda. Voltar ao dashboard depois disso SHALL levar ao login.

#### Scenario: Acionar "Sair"
- **WHEN** a dona aciona "Sair"
- **THEN** a sessão é encerrada na API, o token guardado é apagado e a aplicação navega para `/`

#### Scenario: Sair com a API fora do ar
- **WHEN** a dona aciona "Sair" e a API não responde
- **THEN** o token guardado é apagado e a aplicação navega para `/` do mesmo jeito

#### Scenario: Voltar depois de sair
- **WHEN** a pessoa acessa `/dashboard` depois de acionar "Sair"
- **THEN** a aplicação navega para `/login`
