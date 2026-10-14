# Spec Delta

## MODIFIED Requirements

### Requirement: Só a loja da própria conta
As rotas do dashboard que levam `{slug}` SHALL só valer para a loja da conta logada: um `slug` de loja que existe mas pertence a outra conta, ou que não tem dono, SHALL ser recusado com `403` sem revelar nem alterar nada; um `slug` inexistente SHALL responder `404`.

#### Scenario: Loja de outra conta
- **WHEN** a conta A lista a fila, finaliza um atendimento ou define o endereço da loja da conta B
- **THEN** a resposta é `403` e nada muda na loja da conta B

#### Scenario: Loja sem dono
- **WHEN** uma conta logada chama uma rota do dashboard de uma loja que não tem dono (como a loja de demonstração `veste-bem`)
- **THEN** a resposta é `403`

#### Scenario: Loja inexistente
- **WHEN** uma conta logada chama uma rota do dashboard com um `slug` que não existe
- **THEN** a resposta é `404`
