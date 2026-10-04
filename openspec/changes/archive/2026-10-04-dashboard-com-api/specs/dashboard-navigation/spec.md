# Spec Delta

## REMOVED Requirements

### Requirement: Sair sem sessão (estado atual)
**Reason**: o dashboard passa a ter uma chave de acesso guardada na sessão do navegador, então "Sair" deixa de ser só uma navegação.
**Migration**: o comportamento novo está no requisito "Sair encerra o acesso ao dashboard".

## ADDED Requirements

### Requirement: Sair encerra o acesso ao dashboard
Ao acionar "Sair", o dashboard SHALL apagar a chave de acesso guardada e navegar para a página inicial `/`. Voltar ao dashboard depois disso SHALL pedir a chave de novo.

#### Scenario: Acionar "Sair"
- **WHEN** o estabelecimento aciona "Sair"
- **THEN** a chave guardada é apagada e a aplicação navega para `/`

#### Scenario: Voltar depois de sair
- **WHEN** o estabelecimento acessa `/dashboard` depois de acionar "Sair"
- **THEN** a tela de chave é exibida
