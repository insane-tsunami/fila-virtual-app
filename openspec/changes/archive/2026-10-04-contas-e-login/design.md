# Design

## Context

Estado atual verificado. **Backend (`server/`):** `estabelecimentos(id, nome, slug unico, endereco_publico)` sem dono; a chave global `X-API-Key` (`Middleware\ChaveDeApi`, lida de `config['api_key']`/`API_KEY`) protege `listar`, `finalizar` e `PUT .../endereco`; as rotas levam `{slug}` e os serviços (`FilaService`, `LojaService`) resolvem a loja pelo slug; erros viram JSON por `Aplicacao::tratadorDeErros` (`DadosInvalidosException` 422, `ConflitoException` 409, `NaoEncontradoException` 404, `HttpUnauthorizedException` 401); `Cors` permite `Content-Type, X-API-Key`. Os testes usam `ApiTestCase` (SQLite em memória ou MySQL `*test*`), que migra, monta a app com `api_key` e expõe `comChave()`. **Front:** `/login` e `/cadastro` são formulários sem efeito; `ChaveGate` + `chave.js` (sessionStorage) pedem a chave em `/dashboard` e `/dashboard/qrcode`; `/dashboard/perfil` mostra e-mail e CNPJ fixos e tem seletor de avatar sem efeito; `estabelecimento.js` fixa `slug = 'veste-bem'`, e `useLoja` busca o nome na API com reserva fixa. Ver proposal.md - Why e Impact.

## Goals / Non-Goals

**Goals:**
- Cada loja tem uma conta dona; só ela lista a fila, finaliza atendimentos e define o endereço.
- Cadastro, login, sair e troca de senha funcionando de ponta a ponta, sem dependência nova.
- Nenhum nome de loja nem slug fixo no front.

**Non-Goals:**
- Avatar, confirmação de e-mail, "esqueci a senha", dígito verificador do CNPJ, limite de tentativas, várias lojas por conta, papéis e convites.
- Cookie de sessão (ver decisão 2), JWT, OAuth.

## Decisions

1. **Modelo de dados (migração `0005`).** `contas(id, email unico ate 254, cnpj unico char 14, senha_hash ate 255, criado_em)`; `sessoes(id, conta_id com chave estrangeira, token_hash char 64 unico, criado_em, expira_em)`; e `estabelecimentos.conta_id` nulo e **unico** (uma conta, uma loja). Em `estabelecimentos` a coluna é só um inteiro com índice único, **sem chave estrangeira**, porque o SQLite não acrescenta restrição de chave estrangeira a uma tabela existente; a integridade fica no código, dentro da transação do cadastro. As tabelas novas usam chave estrangeira normalmente. Lojas antigas (`veste-bem`) ficam com `conta_id` nulo. *Alternativa descartada:* recriar `estabelecimentos` para pôr a chave estrangeira (migração arriscada para ganho pequeno).

2. **Sessão por token opaco em `Authorization: Bearer`, e não cookie.** Front e API ficam em origens diferentes (`REACT_APP_API_URL`/`CORS_ORIGIN`) e a hospedagem não existe; cookie entre origens exigiria `SameSite=None; Secure`, CORS com credenciais e HTTPS desde o primeiro dia. O token é `bin2hex(random_bytes(32))` (256 bits); o banco guarda `hash('sha256')` dele (um hash rápido basta para um valor de alta entropia, ao contrário da senha). Validade absoluta de **7 dias**, sem renovação deslizante, para limitar o estrago de um token roubado; várias sessões por conta (cada aparelho um token). Sessões vencidas são apagadas de forma oportunista no login. *Alternativas descartadas:* JWT (não dá para revogar sem lista, sem vantagem para uma API só) e cookie (acima).

3. **Senha com `password_hash(PASSWORD_BCRYPT)`.** O bcrypt ignora tudo depois de 72 bytes, então a regra é de **8 a 72 bytes** e acima disso é `422` (nunca truncar em silêncio). `password_verify` confere o login. Para o e-mail desconhecido o login também roda `password_verify` contra um hash fixo, para não revelar pelo tempo de resposta se o e-mail existe; a mensagem é a mesma nos dois casos. *Alternativa:* Argon2id; descartada porque depende de como o PHP da hospedagem foi compilado e a hospedagem não está definida.

4. **Módulos do backend.** Classes puras e testadas por tabela, no estilo de `Telefone` e `EnderecoPublico`: `Support\Email::normalizar`, `Support\Cnpj::normalizar` (só os 14 dígitos, sem conferir o dígito verificador) e `Support\Slug::deNome` (translitera acentos com `iconv`/`Normalizer`, minúsculas, não alfanuméricos viram `-`, até 80 caracteres). Serviços: `ContaService` (cadastro, dados, trocar senha) e `SessaoService` (login, abrir, resolver token, sair), com `Models\Conta` e `Models\Sessao`. Controllers `ContaController` e `SessaoController`. O cadastro roda em **uma transação** (conta + loja + sessão) e, se o slug colidir de verdade no `INSERT` (corrida entre dois cadastros), repete com o próximo sufixo; o slug livre é escolhido consultando `slug` e `slug-N`.

5. **Autenticação e posse da loja como middlewares.** `Middleware\Autenticacao` (no lugar de `ChaveDeApi`) lê `Authorization`, exige o esquema `Bearer`, busca a sessão pelo hash com `expira_em` no futuro e põe `conta_id` e `sessao_id` como atributos da requisição; sem sessão válida lança `HttpUnauthorizedException` (`401`). `Middleware\LojaDaConta`, aplicado só às rotas com `{slug}`, resolve a loja pelo slug: inexistente é `404`, e de outro dono ou sem dono é `403`, com `NaoEncontradoException` e uma nova exceção de domínio mapeada para `403` no tratador de erros. A mensagem de `401` deixa de falar em "chave de API". Os serviços de fila e de loja continuam recebendo só o slug, já autorizado. *Alternativa descartada:* checar a posse dentro de cada serviço (espalharia a regra e é fácil esquecer em uma rota nova).

6. **Rotas.** Públicas: `POST /api/contas`, `POST /api/sessoes`, `GET /api/filas/{slug}` e as duas do cliente. Protegidas por `Autenticacao`: `GET /api/conta`, `PUT /api/conta/senha`, `DELETE /api/sessao` (`204`), e, com `LojaDaConta`, `GET .../entradas`, `POST .../finalizar` e `PUT .../endereco`. **Senha atual errada é `422`, nunca `401`**, porque o front trata `401` autenticado como sessão expirada; pelo mesmo motivo o `401` do `POST /api/sessoes` (credencial errada) não é "sessão expirada". Ao trocar a senha, as outras sessões da conta são apagadas e a atual continua.

7. **CORS.** `Access-Control-Allow-Methods` ganha `DELETE` e `Allow-Headers` troca `X-API-Key` por `Authorization` (`Content-Type` continua). `API_KEY` sai de `config.php`, do `.env.example` e de toda a documentação; `ConfigTest` e os testes que usavam a chave são reescritos.

8. **Testes do backend.** `ApiTestCase` deixa de montar `api_key` e ganha ajudantes: `criarConta()` (insere conta e vincula `conta_id` à loja semeada `veste-bem`, devolvendo um token real aberto pelo `SessaoService`) e `comSessao($token)`. Assim os testes existentes continuam usando o slug `veste-bem` com a mudança mínima. Os cenários de posse usam uma segunda conta com loja própria criada por `POST /api/contas`.

9. **Front: sessão em um contexto.** Substitui `ChaveGate`/`chave.js`/`useLoja`/`estabelecimento.js`. `SessaoProvider` (dentro do `Router`) guarda `{ estado: 'restaurando' | 'anonimo' | 'logado', token, conta, loja }` e expõe `entrar`, `cadastrar`, `sair`, `expirar` e `atualizarLoja`. O token vai para `sessionStorage` por um módulo `armazenamento` (reaproveita a lógica de `chave.js`: engole exceção e cai para memória). Ao montar com um token guardado, chama `GET /api/conta`: `200` vira `logado`, `401` apaga o token e vira `anonimo`. *Alternativa:* `localStorage` (manter logado por dias); descartada por ora para manter a postura da chave (fechar a aba encerra) e porque dá para mudar depois sem tocar na API.

10. **Front: rotas e redirecionamentos.** `RotaProtegida` envolve as três rotas do dashboard: `restaurando` mostra "Carregando...", `anonimo` redireciona para `/login` guardando a página pedida em `location.state.from` (o login volta para ela), `logado` renderiza. `RotaAnonima` leva `/login` e `/cadastro` a `/dashboard` quando já há sessão. `expirar()` apaga o token e leva a `/login` com o aviso "Sua sessão expirou. Entre de novo."; **só as chamadas autenticadas a invocam**, nunca o login. As páginas do dashboard leem `token` e `loja` do contexto no lugar de `useChave`, `useLoja` e do slug fixo; `useFila` recebe `token` e `expirar`.

11. **Front: formulários.** Login e cadastro viram formulários controlados (hoje são só `TextField` soltos). O cadastro valida a confirmação da senha no cliente e deixa o resto para a API; senhas são esvaziadas em erro e o resto fica. O Perfil lê e-mail e CNPJ de `conta`, ganha "Senha atual" e perde o seletor de avatar (decisão do proposal: um controle que não salva engana). Salvar o endereço na página do QR chama `atualizarLoja` para o contexto não ficar defasado, e o QR continua sendo gerado só ao acionar o botão.

12. **Testes do front.** `fetch` simulado, `sessionStorage` real do jsdom e as mesmas lições da mudança anterior: relógio simulado ligado antes de renderizar e sem `findBy*`/`wait` onde há `setTimeout`; `fireEvent.submit` no formulário (o `click` no botão não envia o formulário neste jsdom). A suíte do dashboard passa a envolver as páginas em um provedor de sessão de teste.

## Risks / Trade-offs

- [Sem limite de tentativas: força bruta no login e enumeração de e-mails/CNPJs no cadastro (`409`)] → Registrado e fora do escopo; a mensagem de login é igual para e-mail desconhecido e senha errada, mas o cadastro revela se um e-mail já existe. Resolver junto com a limitação de requisições.
- [Quem esquece a senha perde o acesso; sem confirmação de e-mail qualquer pessoa cadastra qualquer e-mail ou CNPJ] → Pendências explícitas; "esqueci a senha" e confirmação dependem de envio de e-mail, que não existe.
- [Token em `sessionStorage` é legível por XSS] → Igual à chave de hoje; sem cookie `HttpOnly` por causa do CORS entre origens. Mitigação: validade de 7 dias, revogação ao sair e ao trocar a senha.
- [A loja `veste-bem` original fica órfã] → Sem dono, inacessível pelo dashboard e ainda servida na página pública; sem dado real em produção. O slug dela não pode ser reaproveitado por um cadastro (continua ocupado, o novo ganha `-2`).
- [`conta_id` sem chave estrangeira em `estabelecimentos`] → Índice único e transação no cadastro; testes cobrem a ligação. O custo é uma restrição a menos no banco.
- [Corrida entre dois cadastros com o mesmo nome] → O índice único de `slug` barra o segundo `INSERT` e o cadastro tenta o próximo sufixo; coberto por teste.
- [Mudança incompatível: `API_KEY` deixa de existir] → Documentado no README e no proposal; nenhum deploy existe ainda.
- [Perder o seletor de avatar tira uma peça da tela] → Voltará com o armazenamento de imagens; a decisão está registrada para o usuário revisar.

## Migration Plan

Sem dados reais a migrar. Para subir: `php bin/migrate` (aplica a `0005`), remover `API_KEY` do ambiente do servidor (se existir, é ignorada), manter `CORS_ORIGIN` e o `REACT_APP_API_URL` do build. Quem usava o dashboard abre `/cadastro`, cria a conta e a loja novas e define o endereço público na página do QR. Rollback: reverter o commit; as tabelas e a coluna novas são inofensivas para a versão anterior (que, porém, volta a exigir `API_KEY`).
