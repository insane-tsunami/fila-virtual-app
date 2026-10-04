# Design

## Context

Estado atual verificado: a API (`server/`) já tem tudo o que o dashboard precisa e **não muda nesta mudança**: `GET /api/filas/{slug}/entradas` e `POST .../entradas/{codigo}/finalizar` (protegidas por `X-API-Key`), `GET /api/filas/{slug}` (público) e `PUT .../endereco` (protegida); o CORS já permite `PUT` e o cabeçalho `X-API-Key`. No front, `src/api.js` tem só as chamadas públicas (`buscarLoja`, `entrarNaFila`, `consultarEntrada`). `src/Dashboard/index.js` usa 13 clientes fixos (`initialClients`) e `slice(1)` para finalizar. A barra lateral das três páginas (`index.js`, `Qrcode.js`, `Perfil.js`) repete `nome` e `inicial` importados de `estabelecimento.js`. "Sair" no `Nav.js` é um `Link` para `/`. Ver proposal.md - Why e Impact.

## Goals / Non-Goals

**Goals:**
- O dono vê a fila real, finaliza atendimentos de verdade e acompanha a chegada de clientes sem recarregar a página.
- A chave provisória nunca entra no pacote do front e só dura a sessão do navegador.
- O endereço público da loja passa a poder ser definido pela interface.

**Non-Goals:**
- Perfil (continua fixo), login real, várias lojas, notificações, limite de requisições, aviso de privacidade.
- Qualquer mudança na API ou no CORS.
- Tempo real por WebSocket: a fila é consultada periodicamente.

## Decisions

1. **Chave informada em tempo de execução e guardada em `sessionStorage` (confirmado).** Módulo `src/Dashboard/chave.js` com `lerChave`, `guardarChave` e `apagarChave`; engole exceções de armazenamento bloqueado e, nesse caso, mantém a chave em uma variável de módulo (vale até recarregar). *Alternativas descartadas:* `localStorage` (a chave ficaria no aparelho depois de fechar o navegador) e variável `REACT_APP_*` (vazaria a chave para todos os visitantes).

2. **Porta de entrada como componente envoltório (`ChaveGate`).** `/dashboard` e `/dashboard/qrcode` renderizam a página dentro do `ChaveGate`; sem chave, o gate mostra o formulário (campo "Chave de acesso", botão "Entrar"). Ao entrar, valida a chave chamando a listagem da fila (a única chamada protegida somente de leitura): `200` guarda a chave e libera a página, `401` mostra "Chave inválida.", falha de rede mostra a mensagem de rede. O gate também vira o ponto de retorno quando uma chamada posterior recebe `401`: a página chama `apagarChave()` e o gate volta ao formulário com "Chave inválida.". `/dashboard/perfil` fica fora do gate (não faz chamadas protegidas). *Alternativa:* um endpoint dedicado de verificação de chave; descartada para não mexer na API, já que a listagem cumpre o papel.

3. **Cliente HTTP.** `requisitar` de `src/api.js` passa a aceitar a chave opcional (cabeçalho `X-API-Key`) e o método `PUT`. Novas funções: `listarFila(slug, chave)`, `finalizarEntrada(slug, codigo, chave)` e `definirEndereco(slug, endereco, chave)`. O `ApiError` já carrega o `status`, o que permite tratar `401`, `409` e `422` por código, não por texto.

4. **Estado da fila em um hook (`useFila`).** Recebe `slug` e `chave`; devolve `{ clientes, carregando, falha, finalizar, finalizando }`. Faz a consulta imediata ao montar e depois a cada 5 s com `setTimeout` encadeado: o próximo agendamento só acontece depois que a consulta atual termina (sem sobreposição) e é cancelado ao desmontar. O padrão repete o da página do cliente. `401` chama um `aoRecusarChave` fornecido pela página; falha de rede liga `falha` e preserva `clientes`. A consulta confere o cancelamento antes de chamar a API (lição da página do cliente: um timer pendente não pode falar com a API depois do desmonte).

5. **Finalizar sempre com a fila do servidor como verdade.** O botão chama `finalizarEntrada` com o `codigo` do primeiro cliente e, em `200`, **refaz a consulta** da fila em vez de montar o estado localmente (assim a posição de todos vem da API). `409` também refaz a consulta, sem mensagem de erro (o estado atual já mostra o que aconteceu). Enquanto `finalizando` é verdadeiro, o botão ignora cliques (evita finalizar dois clientes seguidos por clique duplo). Falha de rede mantém a fila e mostra a mensagem de rede.

6. **Mapeamento da API para a tela.** O dashboard passa a usar `codigo` como chave de lista, `posicao` como número e `telefone` (já mascarado pela API) como texto; o primeiro item da resposta é o em atendimento (`status` `em_atendimento`), o segundo é o próximo. Os estados vazios existentes ("Fila vazia", mensagem final, sem `0` solto) continuam valendo e passam a derivar de `clientes.length`.

7. **Nome da loja: hook `useLoja(slug)` compartilhado.** Chama `buscarLoja` uma vez ao montar e devolve `{ nome, inicial }`; enquanto carrega ou se falhar, devolve os valores fixos de reserva de `estabelecimento.js`. As três páginas usam o hook na barra lateral (inclusive o Perfil, só para a barra; o formulário dele não muda). A inicial é a primeira letra do nome. *Alternativa:* só Dashboard e QR usarem a API; descartada porque o requisito "mesmo nome em todas as páginas" quebraria se o nome real diferisse do fixo.

8. **Endereço público na página do QR.** Ao montar, a página lê `endereco_publico` de `buscarLoja` (a mesma chamada que já usa para o QR) e preenche o campo. "Salvar endereço" chama `definirEndereco`: `200` mostra "Endereço salvo."; `422` mostra a mensagem da API sem tocar no campo; `401` volta ao gate; rede mostra a mensagem de rede. Campo vazio envia `""` (a API apaga). Depois de salvar, o QR e a URL em texto desenhados são **descartados**, para nunca mostrarem um endereço diferente do salvo; o dono aciona "Gerar QRCode" de novo. O QR continua sendo gerado só ao acionar o botão.

9. **"Sair" apaga a chave.** O item "Sair" do `Nav.js` mantém o `Link` para `/` e acrescenta um `onClick` que chama `apagarChave()`. Fica fora do escopo avisar outras abas abertas (elas só descobrem a mudança no próximo `401`).

10. **Testes.** `fetch` simulado (como em `src/api.test.js`), relógio simulado para os 5 s (ligado **antes** de renderizar e sem `findBy*`/`wait`, que avançam timers; já documentado no teste da página do cliente) e `sessionStorage` real do jsdom, com variantes que o bloqueiam. Os testes atuais do dashboard, que dependem dos 13 clientes fixos, são reescritos com a API simulada.

## Risks / Trade-offs

- [Quem tiver a chave lista telefones mascarados e pode apontar o QR para outro site] → Já é risco registrado; a chave é provisória e o login a substitui. A tela de chave é o ponto de troca.
- [`sessionStorage` é legível por qualquer script da página] → Não há dependência externa que injete scripts além do pacote; aceitável enquanto a chave é provisória. Não colocar a chave em `localStorage` nem em URL.
- [Dois aparelhos com o dashboard aberto: um finaliza, o outro clica no mesmo cliente] → O `409` recarrega a fila; nenhum cliente a mais é finalizado.
- [Consulta a cada 5 s por dashboard aberto] → Uma loja tem poucos dashboards abertos; se crescer, recuo progressivo ou intervalo maior. Aba em segundo plano continua consultando (aceitável).
- [Salvar o endereço apaga o QR já mostrado] → Pequeno incômodo (um clique) em troca de nunca exibir QR e endereço salvo diferentes.
- [Perfil mostra dados fixos enquanto a barra mostra o nome real] → Já combinado: Perfil fica fora; só a barra lateral usa a API.
- [Chave errada digitada várias vezes] → Sem limite de tentativas (não há limite de requisições na API); registrado como pendência de segurança.

## Migration Plan

Sem migração de dados e sem mudança de backend. Para subir: o dono abre `/dashboard`, informa a chave configurada em `API_KEY` e, se quiser, define o endereço público na página do QR. Rollback: reverter o commit; a API antiga continua compatível.
