# Spec Delta

## ADDED Requirements

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
