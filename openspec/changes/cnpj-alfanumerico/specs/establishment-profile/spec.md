# Spec Delta

## MODIFIED Requirements

### Requirement: Dados de cadastro somente leitura
A página `/dashboard/perfil` SHALL exibir o e-mail e o CNPJ da conta logada em campos somente leitura, com o CNPJ na máscara `XX.XXX.XXX/XXXX-XX` (também quando tem letras), e com as mensagens "Não é possivel alterar o e-mail" e "Não é possivel alterar o CNPJ".

#### Scenario: Abrir as configurações
- **WHEN** a dona logada acessa `/dashboard/perfil`
- **THEN** o e-mail e o CNPJ da própria conta aparecem preenchidos e não podem ser editados
- **AND** as mensagens de que não é possível alterá-los aparecem

#### Scenario: CNPJ alfanumérico
- **WHEN** a dona cuja conta tem o CNPJ `12ABC34501DE35` acessa `/dashboard/perfil`
- **THEN** o campo "CNPJ" mostra `12.ABC.345/01DE-35`
