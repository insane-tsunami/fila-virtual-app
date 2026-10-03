# Design

## Context

Estado atual de `server/`: `composer.json` pede `illuminate/database ^7.9` e `guzzlehttp/guzzle ^6.5`; o `composer.lock` fixa o Laravel 7 e **não instala em PHP 8.3** (verificado); `api/config.php` define constantes de banco a partir de variáveis de ambiente; `_install/zf_line.sql` é um dump de 2020 de um banco que já não existe; não há rotas, controllers nem modelos (o autoload PSR-4 declara `Controllers\` e `Models\` para pastas inexistentes). O ambiente de desenvolvimento tem PHP 8.3.6, Composer, `pdo_sqlite` e `pdo_mysql`, mas não há hospedagem definida. Ver proposal.md - Why.

Versões que o resolvedor do Composer entrega em PHP 8.3 (verificado em dry-run e em um spike que instalou e executou o código): `illuminate/database` v13.34 (**exige PHP ^8.3**), `slim/slim` 4.15 (PHP 8.2 a 8.5), `slim/psr7` 1.8, `guzzlehttp/guzzle` 8.2, `phpunit/phpunit` 12.5 (a série 13 exige PHP >= 8.4.1).

## Goals / Non-Goals

**Goals:**
- Um backend que instala, migra e testa do zero, sem servidor de banco.
- Contrato HTTP estável (ver os specs `queue-intake`, `queue-management` e `api-platform`), que o front vai consumir nas mudanças 2 e 3.
- Regras da fila corretas sob concorrência (duplo clique, duas pessoas entrando ao mesmo tempo).
- Independência de hospedagem: nada no código assume um provedor.

**Non-Goals:**
- Login real, multi-loja, notificações, hospedagem e deploy.
- Rate limiting e verificação de posse do telefone (ver Riscos).
- Tempo real (WebSocket): o cliente consulta a posição periodicamente.

## Decisions

1. **PHP `^8.3` e `illuminate/database ^13`.** O piso de PHP vem do próprio `illuminate/database` atual, e 8.3 é o PHP do ambiente. *Alternativa:* `illuminate/database ^12` (PHP ^8.2) para aceitar hospedagens mais antigas; descartada, porque a hospedagem é uma decisão em aberto e fixar uma versão mais antiga só compra um piso mais baixo que um provedor atual já cumpre. O `composer.lock` é regenerado e versionado.

2. **Slim 4 em vez de roteador artesanal.** São quatro rotas, mas o projeto precisa de middleware (chave, CORS, erros em JSON) e de uma forma de testar sem servidor HTTP. O Slim dá isso com pouco código, e o spike confirmou que `$app->handle($request)` roda em PHPUnit sem web server. *Alternativa:* `nikic/fast-route` com PSR-7 montado à mão, que é a mesma peça sem a cola de middleware.

3. **Erros em JSON por um tratador próprio.** O Slim responde 404 e 405 como `text/html` por padrão (verificado). Um *error handler* registrado no `ErrorMiddleware` converte qualquer exceção HTTP e qualquer erro inesperado para `{"erro": "..."}` com o status certo. Erros 500 devolvem uma mensagem genérica e vão para o `error_log`, sem vazar detalhes.

4. **Configuração por função, não por constantes.** Hoje `api/config.php` usa `defined() or define()`, e constantes não podem ser redefinidas entre testes. Passa a retornar um array lido de `getenv`, e o bootstrap recebe esse array. Os testes montam a aplicação com `DB_DRIVER=sqlite` e `DB_NAME=:memory:` sem tocar no ambiente do processo. As variáveis são `DB_*` (já existem), `API_KEY` e `CORS_ORIGIN`.

5. **Migrações com um runner mínimo próprio (`bin/migrate`).** Arquivos numerados em `database/migrations/` com `up()`, executados em ordem, registrados em uma tabela `migrations`, usando o construtor de esquema do `illuminate/database`. São duas tabelas, e isso evita uma dependência de migração inteira. *Alternativa:* Phinx, madura e com rollback, mas com configuração e dependências próprias para pouco uso. Se o esquema crescer, trocar é barato.

6. **Esquema.** `estabelecimentos` (`id`, `nome`, `slug` único). `entradas_fila` (`id`, `estabelecimento_id` com chave estrangeira, `codigo` único, `telefone`, `status`, `entrou_em`, `iniciou_em`, `finalizou_em`), com índice em (`estabelecimento_id`, `status`, `id`). Valores de `status`: `aguardando`, `em_atendimento`, `finalizado` e `cancelado`; este último fica reservado no esquema, mas **nada o produz nesta mudança** (não há cancelamento). `status` é uma coluna de texto validada pela aplicação, e não `ENUM`, para ser igual em MySQL e SQLite. Horários em UTC. Uma migração de dados insere o estabelecimento de partida (`Veste Bem`, `veste-bem`).

7. **Ordem e posição.** A ordem de chegada é a do `id` da entrada, e não a do horário, para não haver empate. A posição de uma entrada ativa é a quantidade de entradas ativas (`aguardando` ou `em_atendimento`) do mesmo estabelecimento com `id` menor ou igual ao dela. Entrada finalizada tem posição `null`.

8. **Invariante e concorrência.** Em cada estabelecimento há no máximo uma entrada `em_atendimento`, e ela é sempre a primeira da fila ativa. Entrar e finalizar rodam dentro de uma transação que primeiro trava a linha do estabelecimento (`lockForUpdate`), o que serializa as operações por estabelecimento no MySQL/InnoDB e é inofensivo no SQLite, que já serializa escritas. Isso cobre o duplo clique, a corrida de duas finalizações e a de duas entradas em uma fila vazia. Unicidade de telefone ativo não pode ser uma restrição do banco (MySQL não tem índice parcial), então é verificada sob esse mesmo bloqueio.

9. **Entrar em fila vazia vira `em_atendimento` na hora.** O dashboard atual mostra o primeiro da fila como "Em Atendimento", então o estado no banco acompanha o que a tela já faz. Finalizar promove o `aguardando` mais antigo.

10. **Finalizar pelo código da entrada, não "o atual".** `POST .../entradas/{codigo}/finalizar` finaliza só a entrada nomeada e só se ela estiver em atendimento, senão `409`. Assim um duplo clique ou uma tela desatualizada não finaliza o cliente errado. *Alternativa:* `POST .../finalizar` sem corpo, que finalizaria sempre o atual; descartada porque duas requisições seguidas finalizariam dois clientes.

11. **Telefone.** Só dígitos; 10 ou 11 dígitos ganham o DDI `55`; 12 ou 13 precisam começar com `55`. Gravado completo; a máscara (`*****` e os quatro últimos dígitos) é aplicada **na resposta do dashboard**, nunca no banco. A consulta do cliente não devolve telefone algum.

12. **Código de acesso.** `codigo` é `bin2hex(random_bytes(12))` (24 caracteres hexadecimais), único no banco, e é o que aparece nas URLs do cliente. O `id` sequencial nunca é exposto, para uma entrada não poder ser descoberta por tentativa.

13. **Chave provisória.** Um middleware compara `X-API-Key` com `API_KEY` por `hash_equals`; chave ausente, diferente ou `API_KEY` vazia resultam em `401` (fechado por padrão). Aplica-se só às duas rotas do dashboard.

14. **CORS por middleware.** Com `CORS_ORIGIN` definida, as respostas levam `Access-Control-Allow-Origin` e o preflight `OPTIONS` responde `204`; sem ela, nenhum cabeçalho CORS. O valor é uma única origem exata, e não `*`, porque as rotas do dashboard usam cabeçalho de credencial.

15. **Estrutura e testes.** `public/index.php` (front controller), `bin/migrate`, `api/config.php`, `app/controllers`, `app/models`, `app/services` (regras da fila), `app/middleware`, `app/support`, `database/migrations`, `tests/`. As regras da fila ficam em um serviço testado direto e as rotas, de ponta a ponta com `$app->handle()`, ambos em SQLite em memória, recriado a cada teste.

16. **CI.** Novo job `api` no workflow existente, com `shivammathur/setup-php` (PHP 8.3), `composer validate --strict`, `composer install` e `composer test`. **Adição proposta além do combinado:** uma segunda execução da mesma suíte contra um serviço MySQL 8, porque a produção será MySQL e o SQLite não pega diferenças reais (ordenação, tipos, bloqueios). Não consigo reproduzir o MySQL localmente neste ambiente; a primeira execução real no GitHub é a verificação dessa parte.

## Risks / Trade-offs

- [A chave compartilhada não é autenticação de verdade] O dashboard roda no navegador, então uma chave embutida no pacote do front seria visível a qualquer um nas ferramentas do desenvolvedor. → Aceito como solução provisória declarada; para a mudança 3, a recomendação é **pedir a chave ao dono em tempo de execução** (campo digitado e guardado na sessão) em vez de embuti-la no build.
- [Qualquer pessoa pode entrar na fila com qualquer telefone] Não há verificação de posse do número nem limite de requisições, então é possível encher a fila ou inscrever o número de um terceiro. → Fora do escopo; vira pendência explícita antes de existirem notificações (aí o abuso passa a ter vítima). Mitigação possível depois: limite por IP na borda da hospedagem e confirmação do número.
- [Piso de PHP 8.3 limita a escolha de hospedagem] → A maioria dos provedores atuais oferece 8.3; a decisão de hospedagem ainda está em aberto e este requisito entra nela como restrição.
- [Versões novas e pouco conhecidas (Laravel 13, Guzzle 8, PHPUnit 12)] → O `composer.lock` fixa tudo e os testes protegem atualizações futuras. Não se usa nenhuma API além do construtor de esquema, do construtor de consultas e do Eloquent básico.
- [Migrações do MySQL não são transacionais para DDL] → Cada migração é pequena e idempotente na sua intenção; uma falha no meio exige conferir a tabela `migrations`. Documentado no README do `server`.
- [SQLite nos testes pode esconder diferenças de MySQL] → Mitigado pela segunda execução em MySQL no CI (decisão 16).
- [Guzzle fica atualizado e sem uso] → Só nos acompanha até as notificações; se a mudança de notificações demorar, removê-lo é trivial.

## Migration Plan

Não há dados a migrar: o banco antigo não existe e as duas linhas de `zf_line` eram de exemplo. Para subir um ambiente: instalar as dependências, definir as variáveis (`DB_*`, `API_KEY`, `CORS_ORIGIN`) e rodar `bin/migrate`. Rollback: reverter o commit; nada em produção depende desta mudança porque a hospedagem ainda não existe.

## Open Questions

- **Retenção do histórico de telefones (LGPD):** por quanto tempo guardar entradas finalizadas. Não muda specs nem tarefas, e a resposta pode esperar.
- **Hospedagem:** em aberto, e esta mudança deixa o código independente dela.
