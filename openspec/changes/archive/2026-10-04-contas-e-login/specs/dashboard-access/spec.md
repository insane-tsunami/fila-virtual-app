# Spec Delta

## REMOVED Requirements

### Requirement: Tela de chave nas páginas protegidas
**Reason**: a chave provisória `X-API-Key` foi removida; o dashboard passa a exigir login (conta e senha).
**Migration**: o comportamento novo está nos requisitos de `establishment-login` ("Dashboard exige login") e `account-session` ("Rotas do dashboard exigem sessão").

### Requirement: Validar a chave ao entrar
**Reason**: sem chave, não há o que validar na tela de chave.
**Migration**: a validação das credenciais está em `account-session` ("Login") e `establishment-login` ("Entrar com e-mail e senha").

### Requirement: A chave só vive na sessão do navegador
**Reason**: a chave deixa de existir.
**Migration**: o token de sessão fica em `sessionStorage`, conforme `establishment-login` ("Sessão no navegador").

### Requirement: Chave recusada durante o uso
**Reason**: a chave deixa de existir.
**Migration**: o tratamento de sessão recusada está em `establishment-login` ("Sessão expirada").
