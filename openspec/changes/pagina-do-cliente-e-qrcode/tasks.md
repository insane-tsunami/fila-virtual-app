# Tasks

## 1. Backend: endereço público da loja

- [x] 1.1 Criar a migração `server/database/migrations/0004_add_endereco_publico_to_estabelecimentos.php` (coluna `endereco_publico`, texto de até 255, nula) e declarar o campo em `Models\Estabelecimento`; verificar com testes de migração: a coluna existe, a segunda execução não faz nada e o estabelecimento `veste-bem` continua com o campo nulo
- [x] 1.2 Criar `Support\EnderecoPublico::normalizar()` (somente origem `http` ou `https`, host sem IDN, porta opcional, sem credenciais, caminho, query ou fragmento, até 255 caracteres, esquema e host em minúsculas, sem barra final); verificar com testes por tabela cobrindo os cenários de `store-address` (`http://localhost:3000` e `https://loja.exemplo.com:8443` aceitos, a barra final removida, `ftp://`, sem esquema, `javascript:alert(1)`, credenciais, caminho, query, 256 caracteres e valor que não é texto recusados)
- [x] 1.3 Criar `Services\LojaService` (`dados` e `definirEndereco`), `Controllers\LojaController` e as rotas `GET /api/filas/{slug}` e `PUT /api/filas/{slug}/endereco` (esta com o middleware `ChaveDeApi`); verificar com testes ponta a ponta de todos os cenários de `store-address` (loja com e sem endereço, `404`, consulta sem chave, definir, barra final, limpar com `null` e com texto vazio, valores recusados mantendo o endereço anterior, corpo sem o campo) e os cenários novos de `queue-management` (definir o endereço sem chave, com chave errada e com `API_KEY` não configurada devolvem `401` sem alterar o endereço; a consulta dos dados da loja funciona sem chave)
- [x] 1.4 Fazer `Cors` permitir também `PUT` e atualizar o teste de preflight do `ApiPlatformTest` (hoje espera `GET, POST, OPTIONS`); verificar que o preflight devolve `GET, POST, PUT, OPTIONS` e que um preflight para a rota `PUT` do endereço responde `204`
- [x] 1.5 Documentar no `server/README.md` as duas chamadas novas, com exemplos de `curl`, e o campo `endereco_publico`; verificar executando os comandos documentados, como escritos, em um clone limpo e conferindo que o `PUT` e o `GET` devolvem o que o README mostra

## 2. Front: base de comunicação

- [x] 2.1 Criar `src/api.js` (base por `REACT_APP_API_URL`, `ApiError` com `status` e mensagem, `buscarLoja`, `entrarNaFila` e `consultarEntrada`) e acrescentar `slug` a `src/Dashboard/estabelecimento.js`; verificar com testes de `fetch` simulado: sucesso, `422` e `404` com a mensagem do JSON, falha de rede com `status` 0, corpo que não é JSON, e que a base vazia usa a mesma origem
- [x] 2.2 Criar o `.env.example` do front e documentar no `README.md` a variável `REACT_APP_API_URL`, o desenvolvimento local (API na porta 8080 com `CORS_ORIGIN=http://localhost:3000`), o aviso de HTTPS em produção e o requisito de a hospedagem servir o `index.html` para qualquer caminho; verificar com `yarn build` no Node 16, conferindo no pacote gerado que o valor de `REACT_APP_API_URL` foi gravado

## 3. Front: página pública do cliente

- [x] 3.1 Criar `src/Cliente/armazenamento.js` (guardar, ler e apagar o código da entrada por loja, sem estourar se o armazenamento estiver bloqueado); verificar com testes: ida e volta por loja, lojas independentes e armazenamento bloqueado (`setItem` e `getItem` lançando erro) sem exceção
- [x] 3.2 Criar `src/Cliente/` com o formulário e a rota `/fila/:slug` em `src/App.js`: nome da loja, campo "Seu telefone (com DDD)", botão "Entrar na fila", "Loja não encontrada.", entrada na fila, erro de validação mantendo o telefone e a falha de comunicação ao entrar; verificar com testes de renderização dos cenários de "Página pública da fila da loja", "Entrar na fila" e da falha ao entrar, e que a rota nova convive com as rotas atuais
- [x] 3.3 Implementar o acompanhamento (consulta a cada 5 segundos sem sobrepor chamadas, limpeza ao desmontar, "Atendimento finalizado" e "Entrar na fila de novo"), a retomada pelo código guardado (inclusive `404` e armazenamento bloqueado), a falha durante o acompanhamento e a ausência de telefones na tela; verificar com testes e relógio simulado dos cenários de "Acompanhamento automático", "Continuar depois de fechar a página", "Falhas de comunicação" e "Sem telefones na tela de acompanhamento"

## 4. Front: QR code da loja

- [x] 4.1 Adicionar `qrcode.react@^4.2.0` ao `package.json` e ao `yarn.lock`; verificar com `yarn install --frozen-lockfile`, `yarn build` e os testes no Node 16, e que o workflow de CI continua compatível (sem mudanças nele)
- [x] 4.2 Reescrever `src/Dashboard/Qrcode.js`: o botão "Gerar QRCode" busca os dados da loja, monta `<endereço ou origem do front>/fila/<slug>`, desenha o QR e mostra a URL, com a mensagem de falha e nova tentativa; trocar em `src/Dashboard/Qrcode.test.js` o teste do estado antigo pelos cenários novos de `qrcode-generation` (com e sem endereço configurado, conteúdo do QR igual à URL mostrada usando a biblioteca simulada, falha da API e nova tentativa); verificar com os testes passando e o teste de título e botão ainda verde

## 5. Contexto e documentação

- [ ] 5.1 Atualizar o contexto de `openspec/config.yaml` (rota pública `/fila/:slug`, as chamadas novas da API e o front que agora consome a API na página do cliente e no QR); verificar com `openspec validate --all --strict`

## 6. Integração

- [ ] 6.1 Rodar `composer test` (SQLite), `yarn lint`, `yarn test` e `yarn build` no Node 16 e `openspec validate pagina-do-cliente-e-qrcode --strict`; verificar que tudo passa e que os 99 testes da API e os 27 do front continuam verdes, com os testes novos somados
- [ ] 6.2 Verificar de ponta a ponta no Chromium: API em SQLite com `API_KEY` e `CORS_ORIGIN`, front construído com `REACT_APP_API_URL` e servido com fallback de página única; definir o endereço com `curl`, acionar "Gerar QRCode" em `/dashboard/qrcode`, **decodificar o QR da captura** com um decodificador fora do projeto e comparar com a URL em texto; abrir `/fila/veste-bem`, entrar na fila e ver a posição; finalizar pela API e ver a página mudar; recarregar e continuar; abrir uma loja inexistente; verificar que cada passo se comporta como as specs descrevem
- [ ] 6.3 Publicar a branch e abrir o PR (quando o usuário pedir) e verificar nas check runs que `App (Node 16)`, `Specs (OpenSpec)`, `API (PHP 8.3, SQLite)` e `API (PHP 8.3, MySQL)` terminam em sucesso no primeiro run real
