# Proposal

## Why

Só o `queue-dashboard` tem spec. As demais telas (landing, cadastro, login, QR code, perfil e menu) existem só no código, e quase todas são apenas interface: nenhum formulário envia dados, o botão "Gerar QRCode" não gera nada e "Sair" só navega para a home. Sem uma base escrita, qualquer mudança futura (autenticação, QR code real, perfil persistido) não tem o que modificar, e ninguém consegue dizer o que já funciona e o que ainda é maquete.

## What Changes

- Registrar, em specs, o comportamento **observável hoje** de cada tela, sem mudar nenhum código de produção.
- Marcar explicitamente o que é só interface: cada capability traz um requisito "sem integração (estado atual)" que descreve que a ação não envia dados nem gera efeito. Esse requisito é o ponto de partida para os próximos `MODIFIED` quando a integração existir.
- Acrescentar um teste de renderização por tela (Jest + Testing Library) para que os cenários das specs sejam verificados, e não só descritos.
- Nenhuma mudança de comportamento, de rota ou de visual.

## Capabilities

### New Capabilities
- `landing-page`: página inicial pública com a marca, a proposta de valor e os caminhos para cadastro e login.
- `establishment-signup`: tela de cadastro do estabelecimento (e-mail, CNPJ, senha e confirmação), hoje sem envio.
- `establishment-login`: tela de login (e-mail e senha), hoje sem autenticação.
- `dashboard-navigation`: rotas do dashboard e menu lateral (Dashboard, Gerar QRCODE, Configurações, Sair).
- `qrcode-generation`: página de geração do QR code de entrada na fila, hoje só o botão.
- `establishment-profile`: página de configurações do estabelecimento (e-mail e CNPJ somente leitura, nova senha, avatar), hoje sem persistência.

### Modified Capabilities

<!-- Nenhuma. `queue-dashboard` já cobre a identificação do estabelecimento na barra lateral. -->

## Impact

- **Specs:** 6 novas capabilities em `openspec/specs/` após o arquivamento.
- **Código:** apenas testes novos (`src/Home`, `src/Register`, `src/Login`, `src/Dashboard`); nenhum arquivo de produção muda.
- **Dependências, API e backend:** nenhum impacto.
- **Fora do escopo:** implementar qualquer integração, tratar rotas inexistentes (a página fica em branco hoje, registrado como lacuna no design), acessibilidade além do que já existe e mudanças de texto ou visual.
