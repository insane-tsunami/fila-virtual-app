# qrcode-generation Specification

## Purpose

Página em que o estabelecimento gera o QR code que os clientes leem para entrar na fila virtual.

## Requirements

### Requirement: Página de geração de QR code
A página `/dashboard/qrcode` SHALL ter o título "Gerar QRCode" e um botão com o ícone de QR code e o texto "Gerar QRCode".

#### Scenario: Abrir a página
- **WHEN** o estabelecimento acessa `/dashboard/qrcode`
- **THEN** o título "Gerar QRCode" e o botão "Gerar QRCode" aparecem

### Requirement: QR code da loja
Ao acionar "Gerar QRCode", a página SHALL buscar os dados públicos da loja, desenhar o QR code da URL `<endereço público da loja>/fila/<slug>` e exibir essa URL em texto. Se a loja não tiver endereço público configurado, a página SHALL usar como base a origem do próprio front (`window.location.origin`).

#### Scenario: Loja com endereço público configurado
- **WHEN** o estabelecimento aciona "Gerar QRCode" e a loja `veste-bem` tem o endereço `https://loja.exemplo.com`
- **THEN** a página desenha um QR code e mostra a URL `https://loja.exemplo.com/fila/veste-bem`

#### Scenario: Loja sem endereço público configurado
- **WHEN** o estabelecimento aciona "Gerar QRCode" e a loja não configurou o endereço
- **THEN** a página usa a origem do próprio front e mostra a URL `<origem do front>/fila/veste-bem`

#### Scenario: O QR code corresponde à URL mostrada
- **WHEN** o QR code é exibido
- **THEN** o conteúdo codificado é exatamente a URL mostrada em texto

### Requirement: Falha ao gerar o QR code
Se a API não responder ou a loja não existir, a página SHALL mostrar "Não foi possível gerar o QR code. Tente de novo." e MUST NOT exibir um QR code.

#### Scenario: API fora do ar
- **WHEN** o estabelecimento aciona "Gerar QRCode" e a API não responde
- **THEN** a página mostra "Não foi possível gerar o QR code. Tente de novo." e nenhum QR code aparece

#### Scenario: Nova tentativa
- **WHEN** o estabelecimento aciona o botão de novo depois de uma falha e a API responde
- **THEN** o QR code é exibido e a mensagem de erro some
