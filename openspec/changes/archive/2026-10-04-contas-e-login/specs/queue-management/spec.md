# Spec Delta

## REMOVED Requirements

### Requirement: Chave de acesso provisória
**Reason**: a chave compartilhada `X-API-Key` foi substituída por login; ela abria todas as lojas e deixaria de fazer sentido com várias lojas.
**Migration**: as rotas do dashboard exigem sessão e só valem para a loja da própria conta, conforme `account-session` ("Rotas do dashboard exigem sessão" e "Só a loja da própria conta").
