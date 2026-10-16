# Design

## Context

Ver proposal.md (Why). Estado atual relevante:

- `ContaService::cadastrar` faz, dentro de uma transação, `exists()` por e-mail e depois por CNPJ e lança `ConflitoException` (409), depois cria conta, loja e sessão. A confirmação é enviada depois do commit. Só a colisão de slug é tratada (`criarLoja` engole qualquer `QueryException` e tenta o próximo slug); a violação do `UNIQUE` de e-mail ou CNPJ numa corrida sobe como `QueryException` e vira `500`.
- `contas.email_confirmado_em` nulo = não confirmado (migração `0008`). As contas que já existiam ficaram não confirmadas, com `criado_em` antigo.
- Não há `ultimo_acesso`: as sessões vencidas são apagadas no login, então "login recente" não é observável depois de 7 dias.
- FKs sem cascade: `sessoes`, `redefinicoes_senha` e `confirmacoes_email` apontam para `contas`; `entradas_fila` aponta para `estabelecimentos`; `estabelecimentos.conta_id` não tem FK. Não existe exclusão de conta.
- Não há tarefa agendada nem comando de limpeza (`bin/` só tem `migrate` e `seed-demo`), e a hospedagem ainda não foi escolhida (#27).

## Goals / Non-Goals

**Goals:**
- Aplicar a regra só quando alguém precisa do e-mail ou do CNPJ, sem infraestrutura nova.
- Criar a exclusão de conta em cascata como um serviço próprio, que a exclusão de conta pedida pela própria dona (#26) vai reaproveitar.
- Fechar a corrida do cadastro (409 em vez de 500).

**Non-Goals:**
- Varredura periódica ou comando `bin/` de limpeza (pode vir depois, com a #26, usando a mesma regra e o mesmo serviço).
- Tornar o prazo de 14 dias configurável por variável de ambiente.
- Rota de exclusão de conta (é da #26).

## Decisions

### 1. Prazo guardado na coluna `contas.confirmar_ate`

Migração `0010`: `contas.confirmar_ate` (`dateTime`, nula). O cadastro grava `criado_em + 14 dias`. A migração preenche, para as contas com `email_confirmado_em` nulo, `agora + 14 dias` (o relógio das contas antigas começa no deploy); as confirmadas ficam com nulo.

- `Conta::paraResposta()` devolve `confirmar_ate` só quando o e-mail não está confirmado (senão `null`). A coluna não é limpa na confirmação: o estado de confirmação já decide.
- Uma conta não confirmada com `confirmar_ate` nulo (não deveria existir depois da migração) **nunca** é substituível: o padrão inseguro seria apagar.
- Trocar o e-mail e reenviar a confirmação não tocam na coluna.
- Os 14 dias ficam numa constante (`ContaService::PRAZO_CONFIRMACAO_DIAS`), usada pelo prazo e pela janela de "sem entradas". A migração repete o número, porque migração é um retrato fixo e não deve depender de código que muda.

Alternativa descartada: calcular o prazo como `max(criado_em, data de corte fixa) + 14 dias`. Evita a coluna, mas espalha a data de corte pelo código para sempre e não serve à resposta da API de forma tão direta.

### 2. Substituição decidida dentro da transação do cadastro

Dentro da transação, antes de criar a conta:

1. Buscar as contas que bloqueiam: `email = :email OR cnpj = :cnpj`, com `lockForUpdate()` (podem ser duas contas diferentes).
2. Uma conta é **substituível** quando: `email_confirmado_em` é nulo, `confirmar_ate` não é nulo e já passou, e não existe entrada em `entradas_fila` da loja dela com `entrou_em` nos últimos 14 dias (qualquer status).
3. Se alguma bloqueadora não é substituível, lança o 409 com a mensagem de hoje: a do e-mail se a conta do e-mail não é substituível, senão a do CNPJ. Assim, o caso comum (uma conta só, protegida) mantém exatamente a mensagem e a ordem de hoje.
4. Se todas são substituíveis, apaga cada uma com o serviço de exclusão (decisão 3) e segue o cadastro normal.

Tudo na mesma transação: se o cadastro novo falhar depois de apagar, a conta antiga volta (rollback). A substituição continua gastando uma tentativa do limite de cadastro por IP, porque o `consumir` acontece antes de tudo.

### 3. Serviço `Services\ExclusaoDeConta`

`excluir(int $contaId): void`, sem abrir transação própria (roda dentro da do chamador). Ordem imposta pelas FKs: `entradas_fila` da loja, `estabelecimentos` da conta, `confirmacoes_email`, `redefinicoes_senha`, `sessoes`, os contadores de `limites_tentativas` com chave da conta (`senha_conta` e `confirmacao_conta`), e por fim `contas`. Nada é apagado por `conta_id` nulo, o que protege a loja de demonstração.

Os contadores por e-mail e por IP ficam: são hashes, vencem sozinhos e apagá-los permitiria a um atacante zerar o limite de login de um e-mail.

### 4. Corrida e deadlock

- `transaction($fn, 3)`: o Illuminate refaz a transação quando o banco a aborta por deadlock, o que pode acontecer no MySQL com o `SELECT ... FOR UPDATE` e os índices únicos.
- Uma `QueryException` que escape da transação: consultar de novo, fora dela, se o e-mail ou o CNPJ já existe; se existe, lançar o 409 correspondente; se não, relançar (continua sendo um erro de verdade).
- `criarLoja` passa a engolir só a violação de unicidade (SQLSTATE `23000`) e relança as outras. Hoje ela engole qualquer `QueryException`, o que dentro de uma transação abortada por deadlock seguiria tentando slugs numa transação que já não existe.

### 5. Front: prazo na faixa

`AvisoEmail` lê `conta.confirmar_ate`, interpreta como UTC (`'YYYY-MM-DD HH:MM:SS'` → `Date` com `Z`) e formata com `toLocaleDateString('pt-BR')`. Texto: "Confirme até DD/MM/AAAA. Depois dessa data, se a loja ficar 14 dias sem clientes na fila, um novo cadastro com este e-mail ou CNPJ substitui esta conta e apaga a loja." Sem `confirmar_ate` (ou com valor inválido), a faixa fica como hoje.

## Risks / Trade-offs

- [Quem quer manter o bloqueio cria entradas falsas na própria fila a cada 14 dias] → Aceito (decisão D2 da exploração): proteger lojas em uso pesa mais. A regra pode ficar mais dura depois sem mudar a API.
- [Quem confirma um e-mail próprio continua ocupando um CNPJ alheio] → Fora do escopo (prova de CNPJ). Documentado no `server/README.md`.
- [A substituição é irreversível] → Só acontece com três condições ao mesmo tempo, a faixa avisa com a data, e uma conta confirmada nunca é tocada.
- [Deadlock ou violação de unicidade em MySQL que o SQLite dos testes não reproduz] → Retry da transação, tradução para 409 e o job MySQL do CI. A corrida é testada simulando a conta criada entre a checagem e o INSERT.
- [O 201 sobre uma conta antiga revela que ela estava abandonada] → Não revela mais do que o 409 de hoje, que já diz que a conta existe.

## Migration Plan

1. Deploy com `php bin/migrate` (roda a `0010`: coluna nova e prazo das contas não confirmadas = momento da migração + 14 dias).
2. Nenhuma conta pode ser substituída nos primeiros 14 dias depois do deploy.
3. Rollback: voltar ao código anterior; a coluna nula é ignorada por ele. Contas já substituídas não voltam (só por backup).
