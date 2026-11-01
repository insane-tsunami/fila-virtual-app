## MODIFIED Requirements

### Requirement: Formulário de cadastro
A página `/cadastro` SHALL exibir os campos "E-mail", "CNPJ", "Nome do estabelecimento", "Escolha uma Senha" e "Confirme a senha", os dois últimos mascarados, o checkbox "Li e aceito o Aviso de Privacidade" com link para `/privacidade` e o botão "Cadastrar", que fica desabilitado enquanto o checkbox não estiver marcado.

#### Scenario: Visitante abre o cadastro
- **WHEN** um visitante acessa `/cadastro`
- **THEN** os cinco campos, o checkbox do aviso e o botão "Cadastrar" aparecem
- **AND** os campos de senha não mostram o texto digitado
- **AND** o botão "Cadastrar" está desabilitado

#### Scenario: Aceite obrigatório
- **WHEN** o visitante marca o checkbox
- **THEN** o botão "Cadastrar" fica habilitado
