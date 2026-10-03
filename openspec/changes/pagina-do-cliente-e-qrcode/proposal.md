# Proposal

## Why

A API da fila existe, mas ninguém a usa: o cliente não tem onde entrar na fila (não há página pública) e o QR code do dashboard é só um botão sem efeito. Como é **pelo QR code que o visitante entra na fila virtual da loja**, o QR precisa apontar para uma página pública daquela loja. O endereço dessa página muda de loja para loja, então ele passa a ser uma **configuração de cada loja**, e não uma constante do front.

## What Changes

**Suposição a confirmar antes do apply.** Entendi "endereço configurável por loja" assim: cada estabelecimento tem um **endereço público próprio guardado no banco** (a base da URL em que o front dele está publicado); o QR code codifica `<endereço da loja>/fila/<slug>`; se a loja ainda não configurou, o padrão é a origem do próprio front (`window.location.origin`), sem variável de ambiente global. Se a intenção era outra (por exemplo, só o `slug` variar por loja, sobre uma base global), o escopo do backend abaixo cai pela metade.

**Backend (`server/`)**
- Nova coluna opcional `endereco_publico` em `estabelecimentos` (migração).
- Nova chamada pública `GET /api/filas/{slug}` que devolve `nome`, `slug` e `endereco_publico` (`null` se a loja não configurou).
- Nova chamada `PUT /api/filas/{slug}/endereco`, protegida pela chave provisória `X-API-Key`, que define ou limpa o endereço. Só aceita URL absoluta `http` ou `https`, sem credenciais, e a grava sem a barra final; endereço inválido devolve `422`.
- O CORS passa a permitir também o método `PUT` (hoje só `GET`, `POST` e `OPTIONS`), senão o navegador não consegue chamar a nova rota.

**Front (React)**
- **Nova página pública `/fila/:slug`:** o visitante informa o telefone, entra na fila pela API, vê sua posição e seu status e a página se atualiza sozinha por consulta periódica até o atendimento ser finalizado. O código da entrada fica guardado no navegador para ele poder reabrir a página e continuar vendo a posição. Estados de erro tratados: telefone inválido, loja inexistente e API fora do ar.
- **A página `/dashboard/qrcode` passa a gerar o QR code de verdade:** ao acionar "Gerar QRCode", busca os dados da loja, monta a URL e desenha o QR code na tela junto com a URL em texto. O botão e o título continuam como estão.
- A URL base da API vem de uma variável de ambiente do front (`REACT_APP_API_URL`), documentada no README. Vazia significa a mesma origem.
- O `slug` da loja atual (`veste-bem`) entra em `src/Dashboard/estabelecimento.js`.

**Documentação e testes**
- Testes de ponta a ponta da API no padrão atual e testes de renderização do front (Jest + Testing Library, sem rede, com `fetch` simulado).
- README do front e do `server/` atualizados.

## Capabilities

### New Capabilities
- `store-address`: dados públicos da loja e seu endereço público configurável, com a chamada protegida para alterá-lo.
- `customer-queue-page`: página pública em que o visitante entra na fila de uma loja e acompanha a própria posição.

### Modified Capabilities
- `qrcode-generation`: o requisito "Geração sem efeito (estado atual)" deixa de valer, e o QR code passa a ser gerado de verdade.
- `queue-management`: a chave provisória `X-API-Key` passa a proteger também a chamada que define o endereço da loja.
- `api-platform`: o CORS passa a permitir o método `PUT`.

## Impact

- **Backend:** `server/database/migrations/` (nova migração), `server/app/controllers/`, `server/app/models/Estabelecimento.php`, `server/app/services/`, `server/app/support/Aplicacao.php` (rotas), `server/app/middleware/Cors.php`, `server/tests/`, `server/README.md`.
- **Front:** `src/App.js` (nova rota), nova pasta `src/Cliente/` (página pública), `src/Dashboard/Qrcode.js`, `src/Dashboard/estabelecimento.js`, `package.json` e `yarn.lock` (biblioteca de QR code), testes, `README.md`.
- **Dependências:** uma biblioteca de QR code para o front, escolhida e verificada no design (precisa compilar no webpack 4 do CRA 3.4.1 em Node 16).
- **CI:** os 4 jobs existentes (`App (Node 16)`, `Specs (OpenSpec)`, `API (PHP 8.3, SQLite)`, `API (PHP 8.3, MySQL)`) precisam continuar verdes; nenhum job novo.
- **Implantação:** o QR code abre uma URL direta como `/fila/veste-bem`; a hospedagem do front precisa servir o `index.html` para qualquer caminho (fallback de aplicação de página única), senão o visitante cai em um 404 do servidor.
- **Fora desta mudança:** tela para o dono **editar** o endereço (campo em Configurações, que é da mudança 3 junto com o dashboard consumindo a API; até lá o endereço é definido pela chamada `PUT`), dashboard consumindo a API, login real, notificações, hospedagem, domínio próprio por loja no DNS e retenção de dados (LGPD).
- **Riscos registrados:** a chave é provisória e, se vazar, quem a tiver pode apontar o QR de uma loja para qualquer site (risco de phishing); a página do cliente ainda não traz aviso de privacidade (o telefone é dado pessoal); não há limite de requisições.
