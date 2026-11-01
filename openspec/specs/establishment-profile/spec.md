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

### Requirement: Troca de senha recusada ou sem resposta
Se a API recusar a troca da senha com `422`, a página SHALL mostrar a mensagem recebida e esvaziar só os campos de senha. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos.

#### Scenario: Senha atual errada
- **WHEN** a API recusa a troca com `422` por senha atual incorreta
- **THEN** a página mostra "Senha atual incorreta." e continua em `/dashboard/perfil`, sem tratar isso como sessão expirada

#### Scenario: API fora do ar
- **WHEN** a dona aciona "Atualizar" e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e mantém os campos

### Requirement: Trocar o e-mail enquanto não confirmado
Com o e-mail não confirmado, a página SHALL oferecer os campos "Novo e-mail" e "Senha atual" (mascarado) e o botão "Trocar e-mail". Ao acioná-lo, SHALL enviar a troca à API. Se a API aceitar, SHALL mostrar "E-mail alterado. Enviamos um link de confirmação para o novo endereço.", mostrar o novo e-mail e esvaziar os campos. Com o e-mail confirmado, o formulário MUST NOT aparecer.

#### Scenario: Corrigir o e-mail
- **WHEN** a dona com e-mail não confirmado informa um novo e-mail e a senha certa e aciona "Trocar e-mail"
- **THEN** a tela mostra o novo e-mail, o aviso de que a confirmação foi enviada e esvazia os campos

#### Scenario: Conta confirmada
- **WHEN** a dona com e-mail confirmado abre o Perfil
- **THEN** o formulário de troca de e-mail não aparece

### Requirement: Troca de e-mail recusada ou sem resposta
Se a API recusar a troca do e-mail (`409`, `422` ou `429`), a página SHALL mostrar a mensagem recebida e esvaziar só a senha. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos.

#### Scenario: E-mail já usado
- **WHEN** a API responde `409` porque o e-mail é de outra conta
- **THEN** a tela mostra a mensagem recebida e o e-mail da conta não muda

#### Scenario: Senha errada
- **WHEN** a API responde `422` por senha atual errada
- **THEN** a tela mostra a mensagem recebida e esvazia só a senha

#### Scenario: API fora do ar
- **WHEN** a API não responde à troca
- **THEN** a tela mostra a mensagem fixa de falha de rede e mantém os campos

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

### Requirement: Excluir a conta no Perfil
O Perfil SHALL oferecer a seção "Excluir conta", com o aviso de que a conta, a loja e as entradas da fila serão apagadas sem volta, um campo de senha e o botão "Excluir minha conta", desabilitado sem a senha. Se a API aceitar, a página SHALL encerrar a sessão no navegador e voltar para `/`. Se a senha for recusada, SHALL mostrar a mensagem e manter a página.

#### Scenario: Excluir a conta pelo Perfil
- **WHEN** a dona digita a senha atual e aciona "Excluir minha conta"
- **THEN** a sessão é encerrada e a aplicação volta para `/`

#### Scenario: Senha errada no Perfil
- **WHEN** a API responde `422`
- **THEN** o Perfil mostra a mensagem recebida e a sessão continua válida

#### Scenario: API fora do ar
- **WHEN** a API não responde à exclusão
- **THEN** o Perfil mostra "Não foi possível falar com o servidor. Tente de novo." e mantém o formulário
