# qrcode-generation Specification

## Purpose

Página em que o estabelecimento gera o QR code que os clientes leem para entrar na fila virtual.

## Requirements

### Requirement: Página de geração de QR code
A página `/dashboard/qrcode` SHALL ter o título "Gerar QRCode" e um botão com o ícone de QR code e o texto "Gerar QRCode".

#### Scenario: Abrir a página
- **WHEN** o estabelecimento acessa `/dashboard/qrcode`
- **THEN** o título "Gerar QRCode" e o botão "Gerar QRCode" aparecem

### Requirement: Geração sem efeito (estado atual)
Enquanto o QR code não estiver implementado, acionar o botão "Gerar QRCode" MUST NOT produzir imagem, download nem mudança na página.

#### Scenario: Acionar o botão
- **WHEN** o estabelecimento aciona "Gerar QRCode"
- **THEN** nenhum QR code é exibido e a página permanece como estava
