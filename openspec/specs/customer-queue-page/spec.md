# customer-queue-page Specification

## Purpose
Página pública, aberta pelo QR code da loja, em que o visitante entra na fila virtual com o telefone e acompanha a própria posição sem precisar de conta.

## Requirements

### Requirement: Página pública da fila da loja
A rota `/fila/:slug` SHALL exibir, sem exigir login, o nome da loja e um formulário com o campo "Seu telefone (com DDD)" e o botão "Entrar na fila". Se a loja não existir, a página SHALL mostrar "Loja não encontrada." e nenhum formulário.

#### Scenario: Abrir a fila de uma loja
- **WHEN** um visitante acessa `/fila/veste-bem` e a loja existe
- **THEN** a página mostra o nome da loja, o campo "Seu telefone (com DDD)" e o botão "Entrar na fila"

#### Scenario: Loja inexistente
- **WHEN** o visitante acessa `/fila/nao-existe`
- **THEN** a página mostra "Loja não encontrada." e não mostra o formulário

### Requirement: Entrar na fila
Ao enviar o formulário, a página SHALL entrar na fila pela API e passar a mostrar a posição e a situação do visitante, guardando no navegador o código da entrada. Se o telefone for recusado pela API, a página SHALL mostrar a mensagem de erro recebida e manter o formulário.

#### Scenario: Entrar em uma fila vazia
- **WHEN** o visitante envia um telefone válido e ninguém está na fila
- **THEN** a página mostra "É a sua vez!" e "Sua posição: 1"

#### Scenario: Entrar em uma fila com clientes
- **WHEN** o visitante envia um telefone válido e há dois clientes na fila
- **THEN** a página mostra "Aguardando" e "Sua posição: 3"

#### Scenario: Telefone inválido
- **WHEN** o visitante envia um telefone que a API recusa com `422`
- **THEN** a página mostra a mensagem de erro devolvida pela API e mantém o formulário com o telefone digitado

#### Scenario: Telefone que já está na fila
- **WHEN** o visitante envia um telefone que já tem entrada ativa
- **THEN** a página mostra a posição da entrada existente, sem criar outra

### Requirement: Acompanhamento automático da posição
Enquanto a entrada estiver ativa, a página SHALL consultar a posição na API a cada 5 segundos e atualizar o que mostra, e SHALL parar de consultar quando o atendimento for finalizado.

#### Scenario: A fila anda
- **WHEN** o visitante está na posição 3 e a API passa a devolver a posição 2
- **THEN** a página passa a mostrar "Sua posição: 2" sem o visitante fazer nada

#### Scenario: Atendimento finalizado
- **WHEN** a API informa que a entrada foi finalizada
- **THEN** a página mostra "Atendimento finalizado" e para de consultar
- **AND** o botão "Entrar na fila de novo" volta ao formulário e apaga o código guardado

### Requirement: Continuar depois de fechar a página
A página SHALL guardar no navegador, por loja, o código da entrada. Ao ser reaberta com um código guardado, SHALL mostrar a posição atual sem pedir o telefone de novo. Se a API não reconhecer o código, a página SHALL descartá-lo e mostrar o formulário. Se o armazenamento do navegador estiver bloqueado, a página SHALL continuar funcionando sem guardar o código.

#### Scenario: Reabrir a página
- **WHEN** o visitante já entrou na fila, fecha a página e a abre de novo
- **THEN** a página mostra a posição atual sem pedir o telefone

#### Scenario: Código desconhecido
- **WHEN** o código guardado não existe mais na API (`404`)
- **THEN** a página apaga o código e mostra o formulário

#### Scenario: Armazenamento bloqueado
- **WHEN** o navegador não permite guardar dados
- **THEN** o visitante consegue entrar na fila e ver a posição normalmente

### Requirement: Falhas de comunicação
Se a API não responder ao entrar na fila, a página SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter o telefone digitado. Se uma consulta periódica falhar, a página SHALL manter a última posição mostrada, avisar da falha e tentar de novo na consulta seguinte.

#### Scenario: API fora do ar ao entrar
- **WHEN** o visitante envia o telefone e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e mantém o telefone digitado

#### Scenario: Falha durante o acompanhamento
- **WHEN** uma consulta periódica falha e a seguinte responde
- **THEN** a página manteve a última posição durante a falha e volta a se atualizar quando a API responde

### Requirement: Sem telefones na tela de acompanhamento
A tela de acompanhamento MUST NOT exibir telefones, nem o do próprio visitante nem os de outras pessoas, e SHALL mostrar apenas a posição e a situação.

#### Scenario: Tela de acompanhamento
- **WHEN** o visitante acompanha a própria posição
- **THEN** a página não contém nenhum número de telefone
