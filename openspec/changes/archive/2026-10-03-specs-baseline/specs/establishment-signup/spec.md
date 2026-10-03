# Spec Delta

## Purpose

Tela de cadastro para o estabelecimento criar sua conta no ZeraFilas com e-mail, CNPJ e senha.

## ADDED Requirements

### Requirement: Formulário de cadastro
A página `/cadastro` SHALL exibir os campos "E-mail", "CNPJ", "Escolha uma Senha" e "Confirme a senha", os dois últimos mascarados, e o botão "Cadastrar".

#### Scenario: Visitante abre o cadastro
- **WHEN** um visitante acessa `/cadastro`
- **THEN** os quatro campos e o botão "Cadastrar" aparecem
- **AND** os campos de senha não mostram o texto digitado

### Requirement: Caminho para o login
A página de cadastro SHALL oferecer um botão "Faça o Login aqui" que leva a `/login`.

#### Scenario: Já possui cadastro
- **WHEN** o visitante aciona "Faça o Login aqui"
- **THEN** a aplicação navega para `/login`

### Requirement: Cadastro sem integração (estado atual)
Enquanto não houver backend de contas, o botão "Cadastrar" MUST NOT enviar dados, validar campos nem navegar para outra página.

#### Scenario: Acionar "Cadastrar"
- **WHEN** o visitante preenche os campos e aciona "Cadastrar"
- **THEN** nenhuma requisição é feita e a página permanece em `/cadastro`
