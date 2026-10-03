# Spec Delta

## Purpose

Página pública de entrada do ZeraFilas, que apresenta a proposta de valor para estabelecimentos e leva ao cadastro ou ao login.

## ADDED Requirements

### Requirement: Apresentação do produto
A página inicial (`/`) SHALL exibir a marca ZeraFilas, o slogan "Atendimento seguro e sem fila!" e o texto que explica a plataforma de fila virtual para estabelecimentos.

#### Scenario: Visitante abre a página inicial
- **WHEN** um visitante acessa `/`
- **THEN** a marca ZeraFilas e o slogan aparecem
- **AND** o texto sobre a plataforma de gerenciamento de fila virtual aparece

### Requirement: Caminhos para cadastro e login
A página inicial SHALL oferecer um botão "Cadastre-se aqui" que leva a `/cadastro` e um link "Entrar" que leva a `/login`.

#### Scenario: Ir para o cadastro
- **WHEN** o visitante aciona "Cadastre-se aqui"
- **THEN** a aplicação navega para `/cadastro`

#### Scenario: Ir para o login
- **WHEN** o visitante aciona "Entrar"
- **THEN** a aplicação navega para `/login`
