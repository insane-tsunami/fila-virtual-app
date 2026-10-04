# Proposal

## Why

O cliente já entra na fila pela página pública e o QR code já funciona, mas o **dashboard do estabelecimento ainda é de mentira**: a fila mostrada são 13 telefones fixos no código e "Finalizar Atendimento" só tira o primeiro da lista local. Quem usa o ZeraFilas na loja não vê quem realmente entrou na fila e não consegue chamar o próximo de verdade. Fechar esse laço (cliente entra, dono vê e atende) é o que falta para o produto funcionar ponta a ponta.

## What Changes

**Decisões confirmadas pelo usuário na exploração:**
- A chave provisória `X-API-Key` é informada pelo dono **em tempo de execução**, em uma tela de chave, e guardada só durante a sessão do navegador (`sessionStorage`). Ela nunca entra no build do front.
- O **Perfil fica de fora** (continua com dados fixos e sem persistência): não existe backend de contas.
- O **campo do endereço público da loja entra** nesta mudança, na página "Gerar QRCode".
- Atualização automática da fila a cada 5 s, nome da loja vindo da API, e tratamento de `401`, `409` e falha de rede.

**Front (React); nenhuma mudança no backend**
- **Tela de chave:** `/dashboard` e `/dashboard/qrcode` pedem a chave antes de mostrar a página. A chave é validada ao ser informada (a listagem da fila a aceita ou recusa). Chave recusada pela API em qualquer chamada volta para a tela de chave.
- **Dashboard com fila real:** lista as entradas da API (posição e telefone mascarado), mostra a primeira em atendimento e a segunda como próxima, e atualiza sozinho a cada 5 s sem sobrepor chamadas.
- **Finalizar Atendimento pela API:** usa o código da entrada em atendimento; em `409` (já finalizada em outro lugar) apenas recarrega a fila; falha de rede mantém a última fila e avisa.
- **Nome da loja pela API** (`GET /api/filas/{slug}`) na barra lateral das três páginas do dashboard, com o nome fixo atual como reserva enquanto carrega ou se a API falhar.
- **Endereço público na página "Gerar QRCode":** campo preenchido com o valor atual da loja e botão para salvar (`PUT .../endereco`); `422` mostra a mensagem da API; campo vazio apaga o endereço. Salvar descarta o QR já desenhado, para ele nunca ficar diferente do endereço salvo.
- **"Sair"** passa a apagar a chave guardada, além de navegar para a página inicial.
- `src/api.js` ganha as chamadas protegidas (listar a fila, finalizar, definir o endereço).

## Capabilities

### New Capabilities
- `dashboard-access`: tela de chave do dashboard, validação da chave, guarda só na sessão do navegador e retorno à tela de chave quando a API recusa a chave.

### Modified Capabilities
- `queue-dashboard`: a fila e o "Finalizar Atendimento" passam a vir da API (deixam de ser dados fixos), com atualização automática, tratamento de `409` e falhas de comunicação; a barra lateral usa o nome da loja vindo da API.
- `qrcode-generation`: acrescenta o campo para ver e definir o endereço público da loja.
- `dashboard-navigation`: "Sair" passa a apagar a chave guardada (o estado "sem sessão" deixa de valer).

## Impact

- **Front:** `src/api.js`, `src/Dashboard/index.js`, `src/Dashboard/Qrcode.js`, `src/Dashboard/Perfil.js` (só a barra lateral), `src/Dashboard/Nav.js`, `src/App.js`, novos módulos e a tela de chave em `src/Dashboard/`, os testes correspondentes, `README.md` e o contexto de `openspec/config.yaml`.
- **Backend e dependências:** nenhuma mudança. As rotas, o CORS (já permite `PUT` e o cabeçalho `X-API-Key`) e a validação do endereço já existem.
- **CI:** os 4 jobs existentes continuam sendo a barreira; nenhum job novo.
- **Fora desta mudança:** Perfil (senha, avatar, e-mail, CNPJ), login real, várias lojas, notificações, limite de requisições, aviso de privacidade e retenção de dados (LGPD), hospedagem.
- **Riscos registrados:** a chave segue provisória: quem a tiver lista telefones mascarados e pode apontar o QR para outro site (phishing); `sessionStorage` a deixa legível por qualquer script da própria página (sem XSS conhecido, mas sem defesa extra); a tela de chave é o ponto que o login substituirá.
