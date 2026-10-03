# establishment-login Specification

## Purpose

Tela de login para o estabelecimento acessar o painel do ZeraFilas com e-mail e senha.

## Requirements

### Requirement: Formulário de login
A página `/login` SHALL exibir os campos "E-mail" e "Senha", este mascarado, e o botão "Entrar".

#### Scenario: Visitante abre o login
- **WHEN** um visitante acessa `/login`
- **THEN** os campos "E-mail" e "Senha" e o botão "Entrar" aparecem
- **AND** o campo de senha não mostra o texto digitado

### Requirement: Caminho para o cadastro
A página de login SHALL oferecer a pergunta "Ainda não tem cadastro?" com um botão "Cadastre-se aqui" que leva a `/cadastro`.

#### Scenario: Ainda não tem cadastro
- **WHEN** o visitante aciona "Cadastre-se aqui"
- **THEN** a aplicação navega para `/cadastro`

### Requirement: Login sem autenticação (estado atual)
Enquanto não houver autenticação, o botão "Entrar" MUST NOT enviar credenciais nem navegar, e as rotas `/dashboard/*` MUST permanecer acessíveis sem login.

#### Scenario: Acionar "Entrar"
- **WHEN** o visitante aciona "Entrar"
- **THEN** nenhuma requisição é feita e a página permanece em `/login`

#### Scenario: Acessar o dashboard sem login
- **WHEN** alguém acessa `/dashboard` diretamente, sem ter feito login
- **THEN** o dashboard é exibido normalmente
