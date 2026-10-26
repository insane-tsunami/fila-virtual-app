## MODIFIED Requirements

### Requirement: Trocar a senha
A página SHALL oferecer os campos "Senha atual", "Nova Senha" e "Confirme a nova senha", todos mascarados, e o botão "Atualizar". Ao acionar "Atualizar", SHALL conferir que a nova senha e a confirmação são iguais e, se forem, enviar a troca à API. Se forem diferentes, SHALL mostrar "As senhas não são iguais." sem fazer requisição. Se a API aceitar, SHALL mostrar "Senha alterada." e esvaziar os três campos.

#### Scenario: Ver os campos
- **WHEN** a dona logada acessa `/dashboard/perfil`
- **THEN** os campos "Senha atual", "Nova Senha", "Confirme a nova senha" e o botão "Atualizar" aparecem, todos os campos de senha mascarados

#### Scenario: Troca bem-sucedida
- **WHEN** a dona informa a senha atual certa, uma nova senha válida repetida na confirmação e aciona "Atualizar"
- **THEN** a página mostra "Senha alterada." e os três campos ficam vazios

#### Scenario: Senhas diferentes
- **WHEN** a nova senha e a confirmação não são iguais
- **THEN** a página mostra "As senhas não são iguais." e nenhuma requisição é feita

## ADDED Requirements

### Requirement: Escolher o avatar da loja
A página SHALL ter a seção "Avatar da loja", com a pré-visualização do avatar, a escolha de uma das 6 cores e de um dos 6 ícones (cada opção com o nome visível ou acessível) e o botão "Salvar avatar", que envia a escolha à API. Com a loja já com avatar, a escolha atual SHALL vir marcada e o botão "Usar a inicial" SHALL aparecer. A página MUST NOT oferecer envio de imagem.

#### Scenario: Loja sem avatar
- **WHEN** a dona cuja loja não tem avatar abre o Perfil
- **THEN** a pré-visualização mostra a inicial da loja, nenhuma cor nem ícone vem marcado e "Usar a inicial" não aparece
- **AND** não há campo para enviar arquivo

#### Scenario: Salvar o avatar
- **WHEN** a dona escolhe a cor "Verde" e o ícone "Restaurante" e aciona "Salvar avatar"
- **THEN** a página mostra "Avatar salvo." e a barra lateral passa a mostrar o ícone de restaurante sobre verde, sem recarregar

#### Scenario: Escolha incompleta
- **WHEN** só a cor ou só o ícone está escolhido
- **THEN** "Salvar avatar" fica desativado e nenhuma requisição é feita

#### Scenario: Voltar à inicial
- **WHEN** a dona com avatar aciona "Usar a inicial"
- **THEN** a página mostra "Avatar removido." e a barra lateral volta a mostrar a inicial

### Requirement: Avatar recusado ou sem resposta
Se a API recusar o avatar com `422`, a página SHALL mostrar a mensagem recebida e manter a escolha. Se a API responder `401`, a sessão SHALL expirar. Se a API não responder, a página SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter a escolha. Em nenhum desses casos o avatar da barra lateral muda.

#### Scenario: Avatar recusado
- **WHEN** a API responde `422` com "Escolha uma cor e um ícone da lista."
- **THEN** a página mostra essa mensagem e a barra lateral continua como estava

#### Scenario: Sessão inválida
- **WHEN** a API responde `401` ao salvar o avatar
- **THEN** a sessão expira e a dona volta ao login

#### Scenario: API fora do ar
- **WHEN** a dona aciona "Salvar avatar" e a API não responde
- **THEN** a página mostra a mensagem fixa de falha de rede e mantém a escolha
