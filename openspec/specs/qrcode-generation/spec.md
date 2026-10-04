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

### Requirement: Endereço público da loja na página do QR code
A página `/dashboard/qrcode` SHALL mostrar o campo "Endereço público da loja" preenchido com o endereço atual da loja (vazio se não houver) e o botão "Salvar endereço". Ao salvar, a página SHALL enviar o valor à API; se for aceito, SHALL mostrar "Endereço salvo." e passar a usá-lo no próximo QR code. Se a API o recusar com `422`, SHALL mostrar a mensagem recebida e manter o campo como digitado. Salvar um campo vazio SHALL apagar o endereço, voltando ao uso da origem do próprio front. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter o campo.

#### Scenario: Ver o endereço atual
- **WHEN** o estabelecimento abre `/dashboard/qrcode` e a loja tem o endereço `https://loja.exemplo.com`
- **THEN** o campo "Endereço público da loja" mostra `https://loja.exemplo.com`

#### Scenario: Salvar um endereço válido
- **WHEN** o estabelecimento digita `https://loja.exemplo.com` e aciona "Salvar endereço"
- **THEN** a página mostra "Endereço salvo."
- **AND** o QR code gerado em seguida usa esse endereço

#### Scenario: Endereço recusado
- **WHEN** o estabelecimento digita um endereço que a API recusa com `422`
- **THEN** a página mostra a mensagem de erro devolvida pela API e mantém o texto digitado

#### Scenario: Apagar o endereço
- **WHEN** o estabelecimento esvazia o campo e aciona "Salvar endereço"
- **THEN** a página mostra "Endereço salvo."
- **AND** o QR code gerado em seguida usa a origem do próprio front

#### Scenario: Salvar descarta o QR code antigo
- **WHEN** o QR code já está na tela e o estabelecimento salva um endereço
- **THEN** o QR code e a URL em texto saem da tela, e um novo só aparece ao acionar "Gerar QRCode"
