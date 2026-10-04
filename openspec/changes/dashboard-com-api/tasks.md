# Tasks

## 1. Cliente HTTP e guarda da chave

- [ ] 1.1 Estender `src/api.js`: `requisitar` aceita a chave (`X-API-Key`) e `PUT`; acrescentar `listarFila`, `finalizarEntrada` e `definirEndereco`; verificar em `src/api.test.js` (`fetch` simulado) o método, a URL e o cabeçalho de cada chamada, a ausência do cabeçalho nas chamadas públicas e os erros `401`, `409` e `422` com `status` e mensagem
- [ ] 1.2 Criar `src/Dashboard/chave.js` (`lerChave`, `guardarChave`, `apagarChave` em `sessionStorage`, com reserva em memória se o armazenamento estiver bloqueado); verificar com testes: ida e volta, apagar, que nada vai para `localStorage` e armazenamento bloqueado sem exceção, mantendo a chave em memória

## 2. Tela de chave e "Sair"

- [ ] 2.1 Criar o `ChaveGate` (formulário "Chave de acesso" e "Entrar", validação pela listagem da fila, "Chave inválida.", mensagem de rede, retorno ao formulário quando a página avisa `401`) e envolver `/dashboard` e `/dashboard/qrcode` em `src/App.js`, deixando `/dashboard/perfil` de fora; verificar com testes dos cenários de `dashboard-access` (sem chave, chave correta, errada, API fora do ar, recarregar com chave, armazenamento bloqueado, perfil sem chave) e que `src/App.test.js` continua verde
- [ ] 2.2 "Sair" em `src/Dashboard/Nav.js` apaga a chave e navega para `/`; verificar com teste de `Nav.test.js` (chave apagada e rota `/`) e com o cenário de voltar ao dashboard e ver a tela de chave; documentar no `README.md` a chave provisória do dashboard (de onde vem, que fica só na sessão e que nunca vai no build)

## 3. Nome da loja na barra lateral

- [ ] 3.1 Criar o hook `useLoja` (nome e inicial vindos de `buscarLoja`, com o nome fixo de reserva) e usá-lo na barra lateral de `index.js`, `Qrcode.js` e `Perfil.js`; verificar com testes: nome da API nas três páginas, reserva enquanto carrega e quando a API falha, e que os testes de `Perfil.test.js` e `Nav.test.js` seguem verdes

## 4. Dashboard com fila real

- [ ] 4.1 Criar o hook `useFila` (consulta imediata e a cada 5 s encadeada, sem sobreposição, cancelada ao desmontar, `401` repassado, falha mantendo a última fila) e ligá-lo a `src/Dashboard/index.js`, removendo `initialClients`; verificar com testes e relógio simulado dos cenários de "Exibição da fila", "Atualização automática da fila" (entrada nova em até 5 s, consulta lenta, sair da página) e "Falhas de comunicação do dashboard", além dos estados vazios existentes
- [ ] 4.2 Ligar "Finalizar Atendimento" a `finalizarEntrada` (usa o `codigo` do primeiro, refaz a consulta em `200` e em `409`, ignora cliques durante a chamada, mantém a fila e avisa em falha de rede); verificar com testes dos cenários de "Finalizar atendimento" (com aguardando, último cliente, `409`, falha, clique duplo chamando a API uma vez) e reescrever `src/Dashboard/index.test.js` para a API simulada; atualizar o contexto de `openspec/config.yaml` (dashboard e QR consomem a API; só o Perfil segue com dados fictícios) e validar com `openspec validate --all --strict`

## 5. Endereço público na página do QR code

- [ ] 5.1 Acrescentar a `src/Dashboard/Qrcode.js` o campo "Endereço público da loja" e o botão "Salvar endereço" (valor atual vindo de `buscarLoja`, `definirEndereco`, "Endereço salvo.", `422` com a mensagem da API, vazio apaga, `401` volta ao gate, rede, e salvar descarta o QR já mostrado); verificar com testes de `Qrcode.test.js` dos cenários novos de `qrcode-generation` e que os cenários atuais do QR continuam verdes; documentar no `README.md` que o endereço agora se define na página "Gerar QRCode"

## 6. Integração

- [ ] 6.1 Rodar `composer test`, `yarn lint`, `yarn test` e `yarn build` no Node 16 e `openspec validate dashboard-com-api --strict`; verificar que tudo passa, que nenhum arquivo de `server/` mudou (`git diff --stat`) e que a chave não aparece no pacote gerado (procurar o valor usado no teste em `build/`)
- [ ] 6.2 Verificar de ponta a ponta no Chromium: API em SQLite com `API_KEY` e `CORS_ORIGIN`, front construído com `REACT_APP_API_URL` e servido com fallback de página única; chave errada e depois certa; fila vazia; cliente entrando pela página pública e aparecendo no dashboard em até 5 s; finalizar pelo dashboard e o cliente ver "Atendimento finalizado"; finalizar o mesmo atendimento pela API e clicar no dashboard (`409` sem erro); definir o endereço pela página do QR, gerar o QR e **decodificá-lo da captura**; "Sair" voltando a pedir a chave; chave trocada no servidor voltando à tela de chave
- [ ] 6.3 Publicar a branch e abrir o PR (quando o usuário pedir) e verificar nas check runs que `App (Node 16)`, `Specs (OpenSpec)`, `API (PHP 8.3, SQLite)` e `API (PHP 8.3, MySQL)` terminam em sucesso no primeiro run real
