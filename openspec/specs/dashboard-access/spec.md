# dashboard-access Specification

## Purpose
Tela em que o dono informa a chave de acesso provisória para usar as páginas protegidas do dashboard, guardada só durante a sessão do navegador e nunca embutida no pacote do front, até existir login de verdade.

## Requirements

### Requirement: Tela de chave nas páginas protegidas
As páginas `/dashboard` e `/dashboard/qrcode` SHALL exigir uma chave de acesso: sem chave guardada, mostram um campo "Chave de acesso" e o botão "Entrar" no lugar do conteúdo. A página `/dashboard/perfil` MUST NOT exigir a chave.

#### Scenario: Abrir o dashboard sem chave
- **WHEN** o estabelecimento acessa `/dashboard` ou `/dashboard/qrcode` sem chave guardada
- **THEN** a página mostra o campo "Chave de acesso" e o botão "Entrar", e não mostra a fila nem o QR code
- **AND** nenhuma chamada protegida da API é feita

#### Scenario: Abrir o perfil sem chave
- **WHEN** o estabelecimento acessa `/dashboard/perfil` sem chave guardada
- **THEN** a página de configurações aparece normalmente

### Requirement: Validar a chave ao entrar
Ao acionar "Entrar", a página SHALL validar a chave junto à API. Se a API a aceitar, a chave SHALL ser guardada e a página pedida SHALL ser exibida. Se a API a recusar, a página SHALL mostrar "Chave inválida." e manter o campo, sem guardar nada. Se a API não responder, a página SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter o campo.

#### Scenario: Chave correta
- **WHEN** o estabelecimento informa a chave certa e aciona "Entrar"
- **THEN** a página pedida é exibida e a chave fica guardada para o resto da sessão do navegador

#### Scenario: Chave errada
- **WHEN** o estabelecimento informa uma chave que a API recusa com `401`
- **THEN** a página mostra "Chave inválida." e o campo continua disponível
- **AND** a chave não fica guardada

#### Scenario: API fora do ar ao entrar
- **WHEN** o estabelecimento aciona "Entrar" e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e não guarda a chave

### Requirement: A chave só vive na sessão do navegador
A chave SHALL ser guardada apenas no armazenamento de sessão do navegador, de modo que fechar a aba ou a janela a apaga. A chave MUST NOT ser gravada em armazenamento persistente nem fazer parte do pacote do front. Se o armazenamento de sessão estiver bloqueado, o dashboard SHALL continuar funcionando com a chave só em memória, até a página ser recarregada.

#### Scenario: Recarregar a página na mesma sessão
- **WHEN** o estabelecimento já informou a chave e recarrega `/dashboard`
- **THEN** a página é exibida sem pedir a chave de novo

#### Scenario: Armazenamento bloqueado
- **WHEN** o navegador não permite guardar dados da sessão e o estabelecimento informa a chave certa
- **THEN** a página pedida é exibida e funciona; ao recarregar, a chave é pedida de novo

### Requirement: Chave recusada durante o uso
Se qualquer chamada protegida da API for recusada com `401` depois de a chave ter sido aceita, o dashboard SHALL apagar a chave guardada e voltar à tela de chave, avisando "Chave inválida." para o dono informar de novo.

#### Scenario: Chave trocada no servidor
- **WHEN** o dashboard está aberto e a chamada periódica da fila é recusada com `401`
- **THEN** a chave guardada é apagada e a tela de chave aparece com o aviso "Chave inválida."
