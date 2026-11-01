# Tasks

## 1. API

- [x] 1.1 Em `server/app/services/ContaService.php`, criar `excluir(int $contaId, array $corpo): void`: exige `senha`, confere com `Senha::confere`, usa o limite `SENHA_CONTA` (registrar no erro, zerar no acerto), roda `ExclusaoDeConta` em transação. Testes em `server/tests/ContaServiceTest.php`/`ExclusaoDeContaTest.php`: apaga conta, loja, entradas, sessões e tokens; senha errada `422` sem apagar; conta sem sessão.
- [x] 1.2 Expor `DELETE /api/conta` em `server/app/support/Aplicacao.php` com `ContaController::excluir` (sessão obrigatória). Testes em `server/tests/AccountApiTest.php`: `200`, conta e loja somem, sessão deixa de valer, senha errada `422` e sem sessão `401`. Verificar com `composer test`.

## 2. Front

- [x] 2.1 Em `src/api.js`, criar `excluirConta(token, senha)`. Em `src/Dashboard/Perfil.js`, adicionar a seção "Excluir conta" com aviso, campo de senha e botão; no sucesso, limpar a sessão e navegar para `/`; erro de rede mantém o formulário e `401` expira a sessão. Testes em `src/Dashboard/Perfil.test.js`. Verificar com `CI=true yarn test --watchAll=false`, `yarn lint` e `CI=true yarn build`.

## 3. Documentação

- [x] 3.1 `server/README.md`: endpoint, o que é apagado e o que fica (contadores por e-mail/IP, backups) e a pendência jurídica de dados fiscais. `README.md`: exclusão pelo Perfil e o caminho do cliente (sair da fila + contato do aviso). `openspec/config.yaml`: contexto.
- [x] 3.2 Verificar com `openspec validate --all --strict`.
