# Design

## Context

Estado atual verificado: a API da fila (`server/`, PHP 8.3, Slim 4, Illuminate, 99 testes em SQLite e MySQL) tem `estabelecimentos(id, nome, slug)` e quatro chamadas em `/api/filas/{slug}/entradas`; o CORS permite `GET, POST, OPTIONS`; a chave provisória `X-API-Key` protege só a listagem e a finalização. O front (React 16, CRA 3.4.1, Material-UI v4, Node 16) **não consome a API**: `src/Dashboard/Qrcode.js` é um botão sem efeito, `src/Dashboard/estabelecimento.js` só tem `nome` e `inicial`, e não existe página pública. Há 10 specs principais; esta mudança toca `qrcode-generation`, `queue-management` e `api-platform` e cria `store-address` e `customer-queue-page`. Ver proposal.md - Why.

## Goals / Non-Goals

**Goals:**
- O visitante entra na fila de uma loja abrindo o QR code e acompanha a própria posição.
- Cada loja tem o seu endereço público, e o QR code aponta para ele.
- Nada na página pública depende de login ou da chave.

**Non-Goals:**
- Tela para o dono editar o endereço (mudança 3), dashboard consumindo a API, login, notificações.
- Domínio próprio por loja no DNS, hospedagem, retenção de dados (LGPD).
- Tempo real por WebSocket: a página consulta periodicamente.

## Decisions

1. **Interpretação adotada para "endereço configurável por loja" (a confirmar).** Cada estabelecimento tem `endereco_publico` no banco, opcional; o QR codifica `<endereco_publico>/fila/<slug>`, e sem endereço configurado usa `window.location.origin`. *Alternativas:* uma variável de ambiente global do front (já descartada pelo usuário) ou só o `slug` variando por loja sobre uma base global; esta última reduziria o backend a nada. Se a interpretação estiver errada, corrigir aqui e no proposal antes do apply.

2. **O endereço é só uma origem.** Validação estrita: esquema `http` ou `https`, host (nome ou IPv4, sem IDN: só ASCII/punycode) e porta opcional, sem usuário, senha, caminho, query ou fragmento, até 255 caracteres; o valor é gravado com esquema e host em minúsculas e sem a barra final. Motivos: o front roda na raiz do site (`BrowserRouter` sem `basename`), então um prefixo de caminho quebraria as rotas; e a lista fechada de formatos barra `javascript:`, `ftp:` e credenciais embutidas na URL. *Alternativa:* aceitar caminho; descartada até haver necessidade real de hospedar sob um subcaminho.

3. **Backend em três peças pequenas.** `Support\EnderecoPublico::normalizar()` (puro, testado por tabela como o `Telefone`), `Services\LojaService` (`dados`, `definirEndereco`, reaproveitando as exceções de domínio já mapeadas para 404 e 422) e `Controllers\LojaController`. Rotas: `GET /api/filas/{slug}` (pública) e `PUT /api/filas/{slug}/endereco` com o middleware `ChaveDeApi` existente. Campo `endereco_publico` ausente no corpo é `422`, para que um corpo malformado nunca limpe o endereço sem querer; `null` ou texto vazio limpam de propósito.

4. **Migração `0004`** acrescenta `endereco_publico` (texto de até 255, nulo) a `estabelecimentos`. Sem valor de partida: `veste-bem` fica sem endereço e usa o fallback. O `ALTER TABLE ... ADD COLUMN` é suportado igualmente por SQLite e MySQL.

5. **CORS passa a permitir `PUT`.** Sem isso o navegador recusa o preflight da nova rota. A mudança é de uma linha em `Cors.php`, e o teste que hoje espera `GET, POST, OPTIONS` é atualizado.

6. **Cliente HTTP do front em um arquivo só (`src/api.js`).** A base vem de `REACT_APP_API_URL` (lida no build; vazia significa a mesma origem, ou seja, `/api/...`). Expõe `buscarLoja`, `entrarNaFila` e `consultarEntrada`; erros viram um `ApiError` com `status` (0 quando a rede falha) e a mensagem do JSON `{"erro": ...}` quando existe. Os testes simulam `window.fetch`. *Alternativa:* o `proxy` do CRA no desenvolvimento; descartada porque o caminho com CORS é o mesmo da produção.

7. **Página pública `src/Cliente/`** como uma máquina de estados simples: `carregando`, `naoEncontrada`, `formulario`, `acompanhando` e `erro`. Rota `/fila/:slug` em `App.js`. O código da entrada fica em `localStorage` sob a chave `zerafilas:entrada:<slug>`, por um módulo (`armazenamento.js`) que engole exceções de armazenamento bloqueado. Quem perder o código (outro aparelho, dados apagados) se recupera entrando de novo com o mesmo telefone, porque a API devolve a entrada existente (`200`).

8. **Acompanhamento por consulta a cada 5 segundos** com `setInterval`, sem sobrepor chamadas (uma consulta em andamento impede a próxima), limpo ao desmontar e ao finalizar. Falha momentânea mantém a última posição na tela e tenta de novo no ciclo seguinte. A tela de acompanhamento mostra só posição e situação, nunca telefone.

9. **QR code com `qrcode.react` 4.2.0 (`QRCodeSVG`).** Verificado em um clone do projeto, no Node 16: instala, renderiza no Jest e o `yarn build` do CRA 3.4.1 compila. *Alternativas também verificadas no build:* `qrcode.react` 1.0.1 (antiga) e `qrcode` 1.5.4 (gera imagem de forma assíncrona, o que complica os testes). **Nota sobre o método:** o primeiro experimento reportou falha de build nas duas bibliotecas; era defeito do meu roteiro (um `import` fora do topo do arquivo vira erro quando `CI=true`), comprovado por um controle sem biblioteca e por repetir com o `import` no topo. O QR continua acionado pelo botão "Gerar QRCode", o que mantém intacto o requisito atual de título e botão.

10. **Como testar que o QR codifica a URL mostrada.** No Jest, a biblioteca é simulada para capturar a propriedade `value` e compará-la ao texto na tela. De ponta a ponta, o QR real é fotografado no Chromium e **decodificado** com um decodificador fora do projeto, para provar que o código desenhado contém a URL.

11. **`slug` no front:** `src/Dashboard/estabelecimento.js` ganha `slug = 'veste-bem'`. Continua a suposição de uma única loja fixa até existir login.

12. **Hospedagem e QR.** O QR abre uma URL direta como `/fila/veste-bem`, então a hospedagem do front precisa servir o `index.html` para qualquer caminho. Como a hospedagem ainda não existe, isso vira requisito documentado no README, e nenhum arquivo específico de provedor é adicionado.

## Risks / Trade-offs

- [A interpretação de "por loja" pode estar errada] → Registrada como suposição no proposal e na decisão 1; corrigir antes do apply custa só reescrever artefatos.
- [Phishing: com a chave, o endereço pode apontar o QR para qualquer site] → A chave é provisória e a validação só aceita origem, mas quem a tiver controla o destino do QR. Resolve-se com login real; até lá, a chave não deve ficar embutida no build do front.
- [Qualquer pessoa pode entrar na fila com qualquer telefone, e não há limite de requisições] → Já registrado na mudança anterior; a página pública deixa o risco mais visível. Precisa ser resolvido antes de existirem notificações.
- [Telefone é dado pessoal e a página não traz aviso de privacidade] → Fica como pendência explícita junto com a política de retenção (LGPD).
- [Sem fallback de página única na hospedagem, o QR leva a um 404 do servidor] → Requisito documentado; vale conferir no primeiro deploy.
- [Front em HTTPS chamando API em HTTP é bloqueado pelo navegador] → Documentado: em produção, `REACT_APP_API_URL` deve ser HTTPS.
- [`REACT_APP_*` é gravada no build] → Trocar a URL da API exige novo build; aceitável enquanto a hospedagem não está definida.
- [Consulta a cada 5 s por visitante gera carga] → Aceitável para uma loja; se crescer, aumentar o intervalo ou usar recuo progressivo.
- [O fallback `window.location.origin` aponta para onde o dono abriu o dashboard] → Pode não ser a origem pública (por exemplo, `localhost`); por isso o endereço da loja existe, e o README avisa.

## Migration Plan

Sem dados a migrar. Para subir: rodar `php bin/migrate` (aplica a `0004`), definir `REACT_APP_API_URL` no build do front e `CORS_ORIGIN` na API com a origem do front. Rollback: reverter o commit; a coluna extra é inofensiva para a versão anterior da API.

## Open Questions

- O QR deve ser desenhado ao abrir a página, em vez de só ao acionar o botão? Mantido o botão por ora; trocar exigiria revisar o requisito atual de título e botão.
- Aviso de privacidade e política de retenção na página do cliente: pendentes, sem efeito nas specs desta mudança.
