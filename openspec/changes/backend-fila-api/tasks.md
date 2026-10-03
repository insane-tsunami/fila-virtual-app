# Tasks

## 1. Dependências e esqueleto

- [x] 1.1 Atualizar `server/composer.json` (`php ^8.3`; `illuminate/database ^13`, `guzzlehttp/guzzle ^8`, `slim/slim ^4`, `slim/psr7 ^1`; `phpunit/phpunit ^12` em desenvolvimento; autoload PSR-4 `Controllers\`, `Models\`, `Services\`, `Middleware\` e `Support\` para `app/*`; scripts `test` e `migrate`) e regenerar o `composer.lock`; verificar com `composer validate --strict` e com `composer install` em um clone limpo (sem `vendor/`) no PHP 8.3, ambos passando
- [x] 1.2 Criar `server/phpunit.xml` e um teste de fumaça em `server/tests/`; verificar que `composer test` roda e passa, e que `server/vendor/` continua ignorado pelo Git
- [x] 1.3 Converter `server/api/config.php` para retornar um array lido de `getenv` (`DB_*`, `API_KEY`, `CORS_ORIGIN`, com `API_KEY` vazia por padrão) e atualizar `server/.env.example`; verificar com um teste de configuração que passa (variáveis lidas, padrões aplicados, nada de constantes)

## 2. Banco e migrações

- [x] 2.1 Criar `Support\Database`, que monta a conexão a partir do array de configuração (`sqlite` e `mysql`, com chaves estrangeiras ativas); verificar com um teste que conecta em SQLite em memória e executa uma consulta
- [x] 2.2 Criar o runner `server/bin/migrate` (tabela `migrations`, execução em ordem) e as migrações `0001` (`estabelecimentos`), `0002` (`entradas_fila`, com `codigo` único e índice por estabelecimento, status e id) e `0003` (estabelecimento de partida `Veste Bem`, slug `veste-bem`); verificar com testes: rodar duas vezes é idempotente, as tabelas e colunas existem, `slug` e `codigo` duplicados são recusados pelo banco
- [x] 2.3 Remover `server/_install/zf_line.sql`; verificar que `git grep zf_line` não retorna referências restantes fora de `openspec/`

## 3. Regras da fila

- [x] 3.1 Implementar a normalização e a máscara do telefone em `Support`; verificar com testes por tabela cobrindo os cenários de `queue-intake` (`(11) 97177-8203` e `+55 11 97177-8203` viram `5511971778203`, `123` e texto sem dígitos são recusados, 10, 11, 12 e 13 dígitos) e a máscara `*****8203`
- [x] 3.2 Criar os modelos `Estabelecimento` e `EntradaFila` e `Services\FilaService::entrar` (transação com `lockForUpdate` no estabelecimento, `em_atendimento` se a fila estiver vazia, telefone ativo repetido devolve a entrada existente, `codigo` com `random_bytes`); verificar com testes dos cenários de entrada, duplicidade, telefone de entrada finalizada e códigos de 16 caracteres ou mais
- [x] 3.3 Implementar `posicao`, `listar` e `finalizar` no `FilaService` (posição por `id` entre as ativas, promoção do `aguardando` mais antigo, `409` fora de atendimento, `404` inexistente); verificar com testes dos cenários de avanço de posição, entrada finalizada com posição `null`, finalizar o último, finalização repetida e a invariante de uma única `em_atendimento`

## 4. API HTTP

- [x] 4.1 Criar a fábrica da aplicação Slim, `server/public/index.php`, o tratador de erros em JSON (404, 405, 400 e 500 genérico com `error_log`) e o middleware de CORS; verificar com testes de `api-platform` via `$app->handle()` (rota inexistente em JSON, corpo inválido com `400`, preflight `204` com a origem configurada, nenhum cabeçalho CORS sem ela)
- [x] 4.2 Criar os controllers e rotas do cliente (`POST /api/filas/{slug}/entradas` e `GET /api/filas/{slug}/entradas/{codigo}`); verificar com testes ponta a ponta de todos os cenários de `queue-intake` (201 e 200, 404, 422, posição, resposta sem telefone)
- [x] 4.3 Criar o middleware da chave `X-API-Key` (`hash_equals`, fechado por padrão) e os controllers do dashboard (`GET /api/filas/{slug}/entradas` e `POST /api/filas/{slug}/entradas/{codigo}/finalizar`); verificar com testes ponta a ponta de todos os cenários de `queue-management` (401 com chave ausente, errada e `API_KEY` não configurada, listagem mascarada e vazia, `409` e `404`, finalização repetida)

## 5. CI e documentação

- [x] 5.1 Escrever `server/README.md` (requisitos de PHP 8.3, `composer install`, variáveis de ambiente, `bin/migrate`, `composer test`, como subir com `php -S localhost:8080 -t public`, exemplos de `curl` das quatro chamadas, aviso de que a chave é provisória e a nota sobre DDL não transacional no MySQL); verificar executando os comandos documentados, como escritos, em um clone limpo e percorrendo com `curl` o fluxo entrar, listar, finalizar e consultar
- [x] 5.2 Atualizar a seção "Configuração do servidor" do `README.md` da raiz para apontar para o `server/README.md` e citar o requisito de PHP 8.3 e as novas variáveis; verificar relendo o texto contra o `.env.example`
- [x] 5.3 Adicionar o job `api` a `.github/workflows/ci.yml` (`shivammathur/setup-php` com PHP 8.3, `working-directory: server`, `composer validate --strict`, `composer install`, `composer test`); verificar com `actionlint` e reproduzindo os passos do job em um clone limpo, todos passando
- [x] 5.4 Adicionar ao job `api` uma segunda execução contra um serviço MySQL 8 (matriz com `DB_DRIVER` `sqlite` e `mysql`, usando as variáveis `DB_*`); verificar com `actionlint` e confirmar que os testes escolhem o driver pelo ambiente. Esta etapa não pode ser comprovada localmente (não há MySQL no ambiente): a verificação real é o primeiro run no GitHub, na tarefa 6.2

## 6. Integração

- [x] 6.1 Rodar `composer test` e `openspec validate backend-fila-api --strict`, além de `yarn lint` e `yarn test` do front para confirmar que nada do app foi afetado; verificar que tudo passa
- [ ] 6.2 Publicar a branch e abrir o PR (quando o usuário pedir) e verificar nas check runs que `App (Node 16)`, `Specs (OpenSpec)` e `API (PHP)` (nas execuções com SQLite e com MySQL) terminam em sucesso no primeiro run real. Pré-requisito: a branch de trabalho não pode carregar o commit de teste `294fcb4` (`src/lintTeste.js`, que quebra o `yarn lint` de propósito); decidir com o usuário entre removê-lo com um commit normal ou partir de uma branch nova. O novo job só passa a bloquear o merge depois de ser adicionado aos checks exigidos nas settings do GitHub
