# establishment-profile Specification

## Purpose

Página de configurações em que o estabelecimento consulta seus dados de cadastro e prepara a troca de senha e do avatar.

## Requirements

### Requirement: Dados de cadastro somente leitura
A página `/dashboard/perfil` SHALL exibir o e-mail e o CNPJ da conta logada em campos, com o CNPJ na máscara `XX.XXX.XXX/XXXX-XX` (também quando tem letras) e sempre somente leitura, com a mensagem "Não é possivel alterar o CNPJ". O e-mail SHALL aparecer com o estado "E-mail confirmado" ou "E-mail ainda não confirmado"; com o e-mail confirmado, o campo SHALL ser somente leitura, com a mensagem "Não é possivel alterar o e-mail".

#### Scenario: Abrir as configurações
- **WHEN** a dona logada, com e-mail confirmado, acessa `/dashboard/perfil`
- **THEN** o e-mail e o CNPJ da própria conta aparecem preenchidos e não podem ser editados
- **AND** as mensagens de que não é possível alterá-los aparecem
- **AND** o estado "E-mail confirmado" aparece

#### Scenario: CNPJ alfanumérico
- **WHEN** a dona cuja conta tem o CNPJ `12ABC34501DE35` acessa `/dashboard/perfil`
- **THEN** o campo "CNPJ" mostra `12.ABC.345/01DE-35`

#### Scenario: E-mail não confirmado
- **WHEN** a dona com e-mail não confirmado acessa `/dashboard/perfil`
- **THEN** o estado "E-mail ainda não confirmado" aparece e o CNPJ continua somente leitura

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

### Requirement: Trocar o e-mail enquanto não confirmado
Com o e-mail não confirmado, a página SHALL oferecer os campos "Novo e-mail" e "Senha atual" (mascarado) e o botão "Trocar e-mail". Ao acioná-lo, SHALL enviar a troca à API. Se a API aceitar, SHALL mostrar "E-mail alterado. Enviamos um link de confirmação para o novo endereço.", mostrar o novo e-mail e esvaziar os campos. Se a API recusar (`409`, `422` ou `429`), SHALL mostrar a mensagem recebida e esvaziar só a senha. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos. Com o e-mail confirmado, o formulário MUST NOT aparecer.

#### Scenario: Corrigir o e-mail
- **WHEN** a dona com e-mail não confirmado informa um novo e-mail e a senha certa e aciona "Trocar e-mail"
- **THEN** a tela mostra o novo e-mail, o aviso de que a confirmação foi enviada e esvazia os campos

#### Scenario: E-mail já usado
- **WHEN** a API responde `409` porque o e-mail é de outra conta
- **THEN** a tela mostra a mensagem recebida e o e-mail da conta não muda

#### Scenario: Senha errada
- **WHEN** a API responde `422` por senha atual errada
- **THEN** a tela mostra a mensagem recebida e esvazia só a senha

#### Scenario: Conta confirmada
- **WHEN** a dona com e-mail confirmado abre o Perfil
- **THEN** o formulário de troca de e-mail não aparece

#### Scenario: API fora do ar
- **WHEN** a API não responde à troca
- **THEN** a tela mostra a mensagem fixa de falha de rede e mantém os campos
