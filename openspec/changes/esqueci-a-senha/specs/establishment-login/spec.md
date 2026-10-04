# Spec Delta

## ADDED Requirements

### Requirement: Caminho para recuperar a senha
A página `/login` SHALL exibir o link "Esqueci a senha", que leva a `/esqueci-senha`.

#### Scenario: Esqueci a senha
- **WHEN** a pessoa abre `/login`
- **THEN** vê o link "Esqueci a senha" apontando para `/esqueci-senha`

### Requirement: Aviso depois de redefinir a senha
Quando a redefinição de senha termina, o login SHALL mostrar o aviso "Senha alterada. Entre com a nova senha." no mesmo lugar das outras mensagens, e esse aviso MUST NOT reaparecer ao recarregar ou reabrir o login.

#### Scenario: Aviso após redefinir
- **WHEN** a pessoa conclui a redefinição e é levada ao login
- **THEN** o login mostra "Senha alterada. Entre com a nova senha."

#### Scenario: Aviso não persiste
- **WHEN** a pessoa recarrega o login depois de ver o aviso
- **THEN** o aviso não aparece mais
