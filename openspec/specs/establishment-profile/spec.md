# establishment-profile Specification

## Purpose

Página de configurações em que o estabelecimento consulta seus dados de cadastro e prepara a troca de senha e do avatar.

## Requirements

### Requirement: Dados de cadastro somente leitura
A página `/dashboard/perfil` SHALL exibir o e-mail e o CNPJ do estabelecimento em campos somente leitura, com as mensagens "Não é possivel alterar o e-mail" e "Não é possivel alterar o CNPJ".

#### Scenario: Abrir as configurações
- **WHEN** o estabelecimento acessa `/dashboard/perfil`
- **THEN** o e-mail e o CNPJ aparecem preenchidos e não podem ser editados
- **AND** as mensagens de que não é possível alterá-los aparecem

### Requirement: Campos de alteração
A página SHALL oferecer os campos "Nova Senha" e "Confirme a nova senha", ambos mascarados, um seletor de imagem "Imagem de Avatar" que aceita apenas imagens, e o botão "Atualizar".

#### Scenario: Ver os campos editáveis
- **WHEN** o estabelecimento acessa `/dashboard/perfil`
- **THEN** os campos de nova senha, o seletor de imagem e o botão "Atualizar" aparecem

### Requirement: Atualização sem persistência (estado atual)
Enquanto não houver backend de contas, o botão "Atualizar" MUST NOT salvar senha ou avatar, e os dados exibidos MUST ser valores fixos de exemplo.

#### Scenario: Acionar "Atualizar"
- **WHEN** o estabelecimento aciona "Atualizar"
- **THEN** nenhuma requisição é feita e nenhum dado muda
