# Spec Delta

## MODIFIED Requirements

### Requirement: Dados de cadastro somente leitura
A página `/dashboard/perfil` SHALL exibir o e-mail e o CNPJ da conta logada em campos somente leitura, com as mensagens "Não é possivel alterar o e-mail" e "Não é possivel alterar o CNPJ".

#### Scenario: Abrir as configurações
- **WHEN** a dona logada acessa `/dashboard/perfil`
- **THEN** o e-mail e o CNPJ da própria conta aparecem preenchidos e não podem ser editados
- **AND** as mensagens de que não é possível alterá-los aparecem

## REMOVED Requirements

### Requirement: Campos de alteração
**Reason**: a página passa a trocar a senha de verdade (com a senha atual) e o seletor de avatar sai, porque ainda não há onde guardar a imagem.
**Migration**: os campos novos estão em "Trocar a senha". O avatar volta em uma mudança própria, junto com o armazenamento de imagens.

### Requirement: Atualização sem persistência (estado atual)
**Reason**: o backend de contas passou a existir; os dados vêm da conta e "Atualizar" troca a senha.
**Migration**: o comportamento novo está no requisito "Trocar a senha".

## ADDED Requirements

### Requirement: Trocar a senha
A página SHALL oferecer os campos "Senha atual", "Nova Senha" e "Confirme a nova senha", todos mascarados, e o botão "Atualizar". Ao acionar "Atualizar", SHALL conferir que a nova senha e a confirmação são iguais e, se forem, enviar a troca à API. Se forem diferentes, SHALL mostrar "As senhas não são iguais." sem fazer requisição. Se a API aceitar, SHALL mostrar "Senha alterada." e esvaziar os três campos. Se a API recusar com `422`, SHALL mostrar a mensagem recebida e esvaziar só os campos de senha. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos. A página MUST NOT oferecer seletor de avatar.

#### Scenario: Ver os campos
- **WHEN** a dona logada acessa `/dashboard/perfil`
- **THEN** os campos "Senha atual", "Nova Senha", "Confirme a nova senha" e o botão "Atualizar" aparecem, todos os campos de senha mascarados
- **AND** não há seletor de imagem

#### Scenario: Troca bem-sucedida
- **WHEN** a dona informa a senha atual certa, uma nova senha válida repetida na confirmação e aciona "Atualizar"
- **THEN** a página mostra "Senha alterada." e os três campos ficam vazios

#### Scenario: Senhas diferentes
- **WHEN** a nova senha e a confirmação não são iguais
- **THEN** a página mostra "As senhas não são iguais." e nenhuma requisição é feita

#### Scenario: Senha atual errada
- **WHEN** a API recusa a troca com `422` por senha atual incorreta
- **THEN** a página mostra "Senha atual incorreta." e continua em `/dashboard/perfil`, sem tratar isso como sessão expirada

#### Scenario: API fora do ar
- **WHEN** a dona aciona "Atualizar" e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e mantém os campos
