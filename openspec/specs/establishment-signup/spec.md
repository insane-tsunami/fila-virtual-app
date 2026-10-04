# establishment-signup Specification

## Purpose

Tela de cadastro para o estabelecimento criar sua conta no ZeraFilas com e-mail, CNPJ e senha.

## Requirements

### Requirement: Formulário de cadastro
A página `/cadastro` SHALL exibir os campos "E-mail", "CNPJ", "Nome do estabelecimento", "Escolha uma Senha" e "Confirme a senha", os dois últimos mascarados, e o botão "Cadastrar".

#### Scenario: Visitante abre o cadastro
- **WHEN** um visitante acessa `/cadastro`
- **THEN** os cinco campos e o botão "Cadastrar" aparecem
- **AND** os campos de senha não mostram o texto digitado

### Requirement: Caminho para o login
A página de cadastro SHALL oferecer um botão "Faça o Login aqui" que leva a `/login`.

#### Scenario: Já possui cadastro
- **WHEN** o visitante aciona "Faça o Login aqui"
- **THEN** a aplicação navega para `/login`

### Requirement: Criar a conta
Ao acionar "Cadastrar", a página SHALL conferir se "Escolha uma Senha" e "Confirme a senha" são iguais e, se forem, enviar o cadastro à API. Se as senhas forem diferentes, SHALL mostrar "As senhas não são iguais." sem fazer nenhuma requisição. Se a API aceitar, a página SHALL deixar a pessoa já logada e levá-la a `/dashboard`. Se a API recusar (`409` ou `422`), SHALL mostrar a mensagem recebida e manter o que foi digitado, exceto as senhas. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos.

#### Scenario: Cadastro bem-sucedido
- **WHEN** o visitante preenche os cinco campos com dados válidos e aciona "Cadastrar"
- **THEN** a conta e a loja são criadas, a pessoa fica logada e a aplicação navega para `/dashboard`

#### Scenario: Senhas diferentes
- **WHEN** as duas senhas não são iguais
- **THEN** a página mostra "As senhas não são iguais." e nenhuma requisição é feita

#### Scenario: E-mail ou CNPJ já cadastrado
- **WHEN** a API recusa o cadastro com `409`
- **THEN** a página mostra a mensagem recebida e mantém e-mail, CNPJ e nome digitados

#### Scenario: Dado inválido
- **WHEN** a API recusa o cadastro com `422`
- **THEN** a página mostra a mensagem recebida e permanece em `/cadastro`

#### Scenario: API fora do ar
- **WHEN** o visitante aciona "Cadastrar" e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e mantém os campos
