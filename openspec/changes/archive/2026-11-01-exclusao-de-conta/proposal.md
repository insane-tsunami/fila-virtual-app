# Proposal

## Why

A dona da loja não tem como excluir a conta: não existe rota nem tela, e os dados de e-mail, CNPJ, senha, sessões e loja ficam para sempre. A LGPD (art. 18) garante ao titular o direito à eliminação, e a #26 pede um caminho para o titular pedir a exclusão dos dados. A exclusão sob pedido da própria dona é esse caminho para os dados da conta; para o telefone da fila, o cliente já sai pela própria página (`sair-da-fila`).

## What Changes

- **API:** `DELETE /api/conta` com `{ "senha": "..." }` na sessão da conta. Confere a senha atual, apaga a conta com a loja e tudo o que é dela (entradas da fila, sessões, tokens de confirmação e de redefinição, contadores presos à conta) e responde `200`. Senha errada é `422` e conta o limite de senha por conta.
- **Front:** no Perfil, uma seção "Excluir conta" com o aviso do que será apagado, o campo de senha e a confirmação; ao concluir, encerra a sessão no navegador e volta para a página inicial.
- **Documentação:** o caminho de exclusão (cliente e dona) entra no aviso de privacidade e nos READMEs.

## Capabilities

### New Capabilities
Nenhuma.

### Modified Capabilities
- `establishment-account`: nova operação de exclusão da conta e o que ela apaga.
- `establishment-profile`: a seção de exclusão da conta no Perfil.

## Impact

- **API:** `ContaService::excluir`, `ContaController::excluir`, rota em `Support\Aplicacao`, reaproveita `Services\ExclusaoDeConta` (já usado na substituição de conta abandonada), testes de serviço e de API.
- **Front:** `src/api.js`, `src/Dashboard/Perfil.js` e testes.
- **Documentação:** `server/README.md` e `README.md`.
- Sem migração e sem dependências novas.
