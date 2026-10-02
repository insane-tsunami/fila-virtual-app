# Proposal

## Why

O front é pequeno (~1.000 linhas), mas carrega defeitos que atrapalham qualquer desenvolvimento novo: a pasta `Dashbboard` está com nome errado, o dashboard muda um array de módulo diretamente (o que impede ligar a fila a uma API), a fila vazia exibe um `0` solto na tela, o nome do estabelecimento está repetido em três arquivos e não há script de lint. Corrigir isso agora, antes de qualquer funcionalidade nova, custa pouco e evita que os defeitos sejam copiados.

## What Changes

- Renomear a pasta `src/Dashbboard/` para `src/Dashboard/` e ajustar os imports em `src/App.js`.
- Dashboard da fila: manter a fila apenas em estado do React (sem mutar o array `data` de módulo); "Finalizar Atendimento" passa a produzir uma nova lista.
- Corrigir `clients.length && ...` (renderiza `0` quando a fila esvazia) usando comparação booleana explícita.
- Corrigir o uso de `classes.large` em `Qrcode.js` e `Perfil.js`, que não existe no `makeStyles` desses arquivos (o avatar da lateral fica sem o tamanho pretendido).
- Extrair o nome do estabelecimento ("Veste Bem") e sua inicial, hoje fixos em `index.js`, `Qrcode.js` e `Perfil.js`, para um único módulo de constantes.
- Trocar `<html lang="en">` por `lang="pt-BR"` em `public/index.html`.
- Adicionar o script `lint` ao `package.json`, usando o `.eslintrc.js` existente.
- **Mudança de comportamento (consequência da imutabilidade):** o progresso da fila deixa de sobreviver à navegação entre as páginas do dashboard. Hoje ele "sobrevive" só porque o array de módulo é mutado; com estado local, voltar ao dashboard reinicia a lista de exemplo. Aceitável enquanto os dados são fictícios e será resolvido pela integração com API.

## Capabilities

### New Capabilities
- `queue-dashboard`: painel do estabelecimento que mostra a fila de clientes, o cliente em atendimento e permite finalizar o atendimento, incluindo o comportamento com a fila vazia.

### Modified Capabilities

<!-- Nenhuma: ainda não existem specs no projeto. -->

## Impact

- **Código:** `src/App.js`, todos os arquivos movidos de `src/Dashbboard/` para `src/Dashboard/` (`index.js`, `Nav.js`, `Perfil.js`, `Qrcode.js`, `styles.js`), novo módulo de constantes do estabelecimento, `public/index.html`, `package.json`.
- **Dependências:** nenhuma nova.
- **APIs/backend:** nenhum impacto.
- **Compatibilidade:** as rotas (`/dashboard`, `/dashboard/qrcode`, `/dashboard/perfil`) não mudam; só o caminho interno dos arquivos.
- **Fora do escopo:** autenticação, integração com API, geração real de QR code e qualquer mudança visual além da correção do avatar.
