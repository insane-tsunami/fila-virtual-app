# Proposal

## Why

A barra lateral do dashboard mostra só a inicial da loja, num círculo cinza, e a spec do Perfil proíbe o seletor de avatar até a funcionalidade ser decidida (issue #24). A decisão foi tomada: a dona escolhe um avatar pronto (cor e ícone de uma lista fixa), sem upload, que fica para depois da hospedagem (#27) e das regras de dados pessoais (#26).

## What Changes

- **Avatar da loja:** a loja passa a ter um avatar opcional, uma cor e um ícone escolhidos de uma lista fixa de 6 cores e 6 ícones. Sem escolha, a barra lateral continua a mostrar a inicial do nome da loja (o padrão de hoje).
- **API:** `PUT /api/filas/{slug}/avatar` com `{"cor", "icone"}` grava o avatar; `{"cor": null, "icone": null}` volta à inicial. Valores fora da lista, só um dos dois ou corpo malformado dão `422` "Escolha uma cor e um ícone da lista.". A rota segue as regras de sessão e de posse da loja (`401`, `403`, `404`).
- **Respostas da loja:** todas as respostas que trazem a loja (cadastro, login, `GET /api/conta`, `GET /api/filas/{slug}`, endereço e avatar) passam a incluir `avatar`: `null` ou `{"cor", "icone"}`.
- **Perfil:** nova seção "Avatar da loja", com a pré-visualização, a escolha de cor e de ícone, "Salvar avatar" e "Usar a inicial". O requisito de trocar a senha deixa de proibir o seletor de avatar.
- **Barra lateral:** mostra o ícone sobre a cor escolhida ou, sem avatar, a inicial como hoje.
- **Fora do escopo:** upload de imagem; mostrar o avatar na página pública da fila ou no QR code; avatar da conta separado do da loja.

## Capabilities

### New Capabilities
- `store-avatar`: o avatar da loja na API, ou seja, a lista fixa de cores e ícones, a rota para definir e limpar, a validação e o campo `avatar` nas respostas da loja.

### Modified Capabilities
- `establishment-profile`: "Trocar a senha" deixa de proibir o seletor de avatar; entram os requisitos da seção "Avatar da loja" no Perfil e do que acontece quando a API recusa ou não responde.
- `queue-dashboard`: "Identificação da loja da conta" passa a mostrar o avatar escolhido, com a inicial como padrão.

## Impact

- **Banco:** migração `0011` com `estabelecimentos.avatar_cor` e `estabelecimentos.avatar_icone` (nulas); `MigrationTest`.
- **API:** `Support\Avatar` (lista fixa), `Models\Estabelecimento` (resposta única da loja), `LojaService` (avatar e resumo), `ContaService::dados`, `SessaoService::abrir`, `LojaController` e a rota em `Aplicacao.php`; testes novos de API e de serviço; testes de resposta que comparam a loja inteira.
- **Front:** `src/api.js`, `src/Dashboard/avatar.js` (cores e ícones de `@material-ui/icons`, já instalado), `BarraLateral.js`, `Perfil.js` e testes (o de `Perfil.test.js` que confere a falta do seletor é invertido), `SessaoDeTeste.js`.
- **Documentação:** `README.md`, `server/README.md` e a pendência "avatar" no contexto de `openspec/config.yaml`.
- Sem dependências novas e sem mudanças de hospedagem.
