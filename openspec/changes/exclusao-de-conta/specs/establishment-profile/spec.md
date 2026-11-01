## ADDED Requirements

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
