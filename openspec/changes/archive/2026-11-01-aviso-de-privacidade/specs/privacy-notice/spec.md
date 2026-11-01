## Purpose

Informa, nos pontos em que os dados são coletados, o que o ZeraFilas faz com eles: controlador e contato, finalidade, base legal, prazos de retenção e direitos do titular, com os prazos reais da configuração.

## ADDED Requirements

### Requirement: Aviso público completo
A rota pública `/privacidade` SHALL exibir o aviso de privacidade com controlador, contato, finalidade do telefone da fila e dos dados da conta, base legal, prazos de retenção, compartilhamento e direitos do titular, sem exigir login.

#### Scenario: Abrir o aviso
- **WHEN** um visitante acessa `/privacidade`
- **THEN** a página mostra o aviso completo, incluindo o contato do controlador e os prazos de retenção

#### Scenario: Prazos iguais aos da retenção
- **WHEN** `RETENCAO_TELEFONE_DIAS` é `7`
- **THEN** o aviso mostra 7 dias para o apagamento do telefone

### Requirement: Configuração do aviso
O aviso SHALL usar `PRIVACIDADE_CONTROLADOR`, `PRIVACIDADE_EMAIL` e `PRIVACIDADE_ENCARREGADO` da API, expostos por `GET /api/privacidade` junto de `retencao_telefone_dias`, `retencao_entrada_ativa_horas` e `base_legal`. O endpoint SHALL ser público e MUST NOT devolver dados de contas ou de clientes.

#### Scenario: Consultar a configuração
- **WHEN** qualquer pessoa chama `GET /api/privacidade`
- **THEN** a resposta traz o controlador, o contato e os prazos configurados, sem exigir sessão

#### Scenario: Configuração ausente
- **WHEN** as variáveis não estão definidas
- **THEN** o endpoint devolve os padrões provisórios documentados

### Requirement: Aviso curto na coleta do telefone
O formulário de entrada na fila SHALL mostrar, junto do campo de telefone, que o número é usado para organizar a fila daquela loja, que é apagado depois do prazo de retenção e o link para `/privacidade`.

#### Scenario: Aviso ao entrar na fila
- **WHEN** o visitante abre a página pública da fila
- **THEN** vê a finalidade do telefone, o prazo de apagamento e o link para o aviso completo

### Requirement: Aceite no cadastro da loja
A página `/cadastro` SHALL exigir o checkbox "Li e aceito o Aviso de Privacidade", com link para `/privacidade`, e MUST NOT enviar o cadastro sem ele.

#### Scenario: Cadastro sem aceite
- **WHEN** o visitante preenche os campos e não marca o checkbox
- **THEN** o botão "Cadastrar" fica desabilitado e nenhuma requisição é feita

#### Scenario: Cadastro com aceite
- **WHEN** o visitante marca o checkbox e envia o cadastro
- **THEN** o cadastro segue normalmente para a API
