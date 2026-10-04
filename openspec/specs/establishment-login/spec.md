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

### Requirement: Entrar com e-mail e senha
Ao acionar "Entrar", a página SHALL enviar o e-mail e a senha à API. Se forem aceitos, SHALL deixar a pessoa logada e levá-la à página do dashboard que ela tentava abrir, ou a `/dashboard` quando veio direto para o login. Se a API recusar com `401`, SHALL mostrar a mensagem recebida, manter o e-mail e limpar a senha. Se a API não responder, SHALL mostrar "Não foi possível falar com o servidor. Tente de novo." e manter os campos. Com o e-mail ou a senha em branco, SHALL mostrar "Informe o e-mail e a senha." sem fazer nenhuma requisição.

#### Scenario: Login correto
- **WHEN** a dona informa o e-mail e a senha certos e aciona "Entrar"
- **THEN** a aplicação navega para `/dashboard`

#### Scenario: Voltar à página pedida
- **WHEN** um visitante sem sessão abriu `/dashboard/qrcode`, foi levado ao login e entra com sucesso
- **THEN** a aplicação navega para `/dashboard/qrcode`

#### Scenario: Senha errada
- **WHEN** a API recusa o login com `401`
- **THEN** a página mostra "E-mail ou senha incorretos.", mantém o e-mail e esvazia a senha, e permanece em `/login`

#### Scenario: Campos em branco
- **WHEN** o visitante aciona "Entrar" sem preencher o e-mail ou a senha
- **THEN** a página mostra "Informe o e-mail e a senha." e nenhuma requisição é feita

#### Scenario: API fora do ar
- **WHEN** o visitante aciona "Entrar" e a API não responde
- **THEN** a página mostra "Não foi possível falar com o servidor. Tente de novo." e mantém os campos

### Requirement: Dashboard exige login
As páginas `/dashboard`, `/dashboard/qrcode` e `/dashboard/perfil` SHALL só ser exibidas para uma sessão válida; um visitante sem sessão SHALL ser levado a `/login`. Quem já tem sessão válida e abre `/login` ou `/cadastro` SHALL ser levado a `/dashboard`.

#### Scenario: Acessar o dashboard sem login
- **WHEN** alguém sem sessão acessa `/dashboard`, `/dashboard/qrcode` ou `/dashboard/perfil`
- **THEN** a aplicação navega para `/login` e não exibe nenhum dado da fila

#### Scenario: Acessar o dashboard logado
- **WHEN** a dona logada acessa `/dashboard`
- **THEN** o dashboard é exibido

#### Scenario: Abrir o login já logada
- **WHEN** a dona logada acessa `/login` ou `/cadastro`
- **THEN** a aplicação navega para `/dashboard`

### Requirement: Sessão no navegador
O token da sessão SHALL ser guardado apenas no armazenamento de sessão do navegador (fechar a aba ou a janela encerra o acesso) e MUST NOT ser gravado em armazenamento persistente nem fazer parte do pacote do front. Ao recarregar a página com um token guardado, a aplicação SHALL confirmar a sessão junto à API antes de exibir páginas protegidas. Se o armazenamento de sessão estiver bloqueado, o login SHALL continuar funcionando com o token só em memória, até a página ser recarregada.

#### Scenario: Recarregar a página logada
- **WHEN** a dona logada recarrega `/dashboard`
- **THEN** a aplicação confirma a sessão na API e exibe o dashboard sem pedir o login de novo

#### Scenario: Token guardado já inválido
- **WHEN** a página é recarregada com um token que a API recusa com `401`
- **THEN** o token é apagado e a aplicação navega para `/login`

#### Scenario: Armazenamento bloqueado
- **WHEN** o navegador não permite guardar dados da sessão e a dona faz login
- **THEN** o dashboard funciona; ao recarregar, o login é pedido de novo

### Requirement: Sessão expirada
Se uma chamada protegida da API for recusada com `401` depois de a sessão ter sido aceita, a aplicação SHALL apagar o token e levar a pessoa a `/login` com o aviso "Sua sessão expirou. Entre de novo.". O `401` da própria chamada de login MUST NOT ser tratado como sessão expirada.

#### Scenario: Sessão vencida durante o uso
- **WHEN** a consulta periódica da fila é recusada com `401`
- **THEN** o token é apagado e a aplicação navega para `/login` com "Sua sessão expirou. Entre de novo."

#### Scenario: Senha errada não é sessão expirada
- **WHEN** o login é recusado com `401`
- **THEN** o aviso é o de credenciais incorretas, e não o de sessão expirada
