# Proposal

## Why

Hoje qualquer pessoa que souber a chave provisória `X-API-Key` lista os telefones de **todas** as lojas e pode apontar o QR de qualquer uma para outro site, e as telas de login e de cadastro são formulários mortos. Para o ZeraFilas atender mais de uma loja, cada estabelecimento precisa ter a sua conta, entrar com e-mail e senha e só enxergar e mexer na própria loja. É também o que destrava o Perfil, que hoje mostra e-mail e CNPJ fixos.

## What Changes

**Decisões confirmadas pelo usuário na exploração:**
- **Uma conta é dona de uma loja, criada no cadastro.** O cadastro ganha o campo "Nome do estabelecimento", e o slug da loja (que vai no link do QR) é gerado a partir dele e é único. O `veste-bem` fixo some do front.
- **A chave `X-API-Key` é removida por completo** (variável `API_KEY`, middleware e tela de chave). **BREAKING:** quem chamava a API com a chave passa a usar login e token.
- A loja `veste-bem` que já existe no banco fica **sem dono**: continua funcionando na página pública e nos testes, e ninguém a acessa pelo dashboard. Não há dado real em produção.
- Padrões propostos e confirmados ("tudo ok"): sessão por **token opaco** em `Authorization`, guardado no servidor só como hash e com validade; senha com `password_hash` (sem dependência nova); **fora do escopo:** avatar, confirmação de e-mail, "esqueci a senha", validação do dígito do CNPJ e limite de tentativas.

**Backend (`server/`)**
- Migração `0005`: tabelas `contas` (e-mail, CNPJ, hash da senha) e `sessoes` (hash do token e validade) e a coluna `estabelecimentos.conta_id` (um para um, nula para lojas sem dono).
- `POST /api/contas` (cadastro): cria conta e loja juntas, já abre a sessão e devolve o token.
- `POST /api/sessoes` (login), `DELETE /api/sessao` (sair), `GET /api/conta` (conta e loja de quem está logado) e `PUT /api/conta/senha` (trocar a senha, pedindo a senha atual).
- As rotas do dashboard (listar a fila, finalizar, definir o endereço) passam a exigir o token **e** que o `slug` da URL seja da loja da conta (`401` sem token válido, `403` para a loja de outra conta).
- CORS: passa a permitir o cabeçalho `Authorization` e o método `DELETE` (e deixa de citar `X-API-Key`).
- `API_KEY` deixa de existir na configuração, no `.env.example`, nos testes e na documentação.

**Front (React)**
- `/cadastro` e `/login` passam a funcionar; o cadastro pede também o nome do estabelecimento e confere a confirmação da senha.
- `/dashboard`, `/dashboard/qrcode` e `/dashboard/perfil` ficam atrás do login (visitante sem sessão vai para `/login`); a loja (nome e slug) vem da conta, e não mais de constantes.
- A tela de chave (`ChaveGate`) e o guarda da chave saem; entra uma sessão (token em `sessionStorage`) com "Sair" que encerra a sessão no servidor e sessão expirada que volta ao login.
- **Perfil** passa a mostrar o e-mail e o CNPJ da conta e a permitir **trocar a senha** (senha atual, nova e confirmação). O seletor de **avatar sai** da página até existir armazenamento de imagem.

## Capabilities

### New Capabilities
- `establishment-account`: cadastro da conta com a loja, dados da conta e troca de senha (API).
- `account-session`: login, sair, token de sessão com validade e a regra de que as rotas do dashboard exigem login e só valem para a loja da própria conta.

### Modified Capabilities
- `establishment-signup`: o cadastro deixa de ser "sem integração" e passa a criar a conta, com o nome do estabelecimento.
- `establishment-login`: o login deixa de ser "sem autenticação" e as rotas do dashboard passam a exigir sessão.
- `establishment-profile`: e-mail e CNPJ vêm da conta, a senha pode ser trocada e o avatar sai.
- `dashboard-navigation`: rotas do dashboard protegidas por login e "Sair" encerra a sessão (no lugar de apagar a chave).
- `queue-dashboard`: nome e inicial da barra lateral passam a vir da conta.
- `queue-management`: sai o requisito da chave provisória; as rotas do dashboard seguem o login e a posse da loja (descritos em `account-session`).
- `store-address`: definir o endereço passa a exigir login e posse da loja, em vez da chave.
- `api-platform`: o acesso entre origens passa a permitir `Authorization` e `DELETE`.
- `dashboard-access` é **aposentada** (todos os requisitos removidos): a tela de chave deixa de existir.

## Impact

- **Backend:** `server/database/migrations/` (nova migração), `server/app/` (novos `controllers`, `services`, `models` e o middleware de autenticação no lugar de `ChaveDeApi`; `Aplicacao.php`, `Cors.php`, `LojaService`/`FilaService` para a posse da loja), `server/api/config.php`, `server/.env.example`, `server/tests/` (inclusive a base `ApiTestCase`, que hoje usa a chave), `server/README.md`.
- **Front:** `src/App.js`, `src/api.js`, `src/Login/`, `src/Register/`, `src/Dashboard/` (Perfil, Nav, QR, fila; saem `ChaveGate.js`, `chave.js`, `useLoja.js` e `estabelecimento.js`), novos módulos de sessão, os testes correspondentes, `README.md` e o contexto de `openspec/config.yaml`.
- **Dependências:** nenhuma nova (`password_hash` e `random_bytes` são do PHP).
- **Configuração e CI:** a variável `API_KEY` deixa de ser lida; o CI (`.github/workflows/ci.yml`) não a define, então os 4 jobs continuam sendo a barreira, sem job novo.
- **Dados:** a migração só acrescenta tabelas e uma coluna nula; a loja `veste-bem` existente fica com `conta_id` nulo.
- **Fora desta mudança:** avatar, confirmação de e-mail, "esqueci a senha", validação do dígito do CNPJ, limite de tentativas de login e de cadastro, mais de uma loja por conta, convites e papéis, aviso de privacidade e retenção de dados (LGPD), hospedagem.
- **Riscos registrados:** sem limite de tentativas, o login e o cadastro aceitam força bruta e enumeração de e-mails e CNPJs; sem recuperação de senha, quem esquece a senha perde o acesso; sem confirmação de e-mail, qualquer pessoa pode cadastrar qualquer e-mail ou CNPJ; o token em `sessionStorage` é legível por XSS (como a chave era); a loja `veste-bem` fica órfã.
