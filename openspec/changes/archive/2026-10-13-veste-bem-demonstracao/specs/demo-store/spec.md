# Spec Delta

## Purpose

Define a loja de demonstração `veste-bem`: ela existe só em desenvolvimento e testes, criada por um comando explícito, e nunca nasce com o banco de produção.

## ADDED Requirements

### Requirement: Banco novo sem loja de demonstração
Rodar todas as migrações em um banco novo SHALL deixar o banco **sem nenhuma loja**: nem a loja `veste-bem` nem outra. O slug `veste-bem` MUST ficar livre para uma loja de verdade.

#### Scenario: Banco novo
- **WHEN** todas as migrações são executadas em um banco vazio
- **THEN** não existe nenhum estabelecimento no banco

#### Scenario: Slug livre
- **WHEN** uma conta se cadastra com o nome "Veste Bem" em um banco recém-migrado
- **THEN** a loja dela recebe o slug `veste-bem`

### Requirement: Migração remove a demonstração sem uso
A migração `0009` SHALL apagar a loja `veste-bem` de um banco já migrado **somente** quando ela não tem dona e não tem nenhuma entrada na fila. A loja que tem dona MUST ser preservada em silêncio. A loja sem dona que tem entradas MUST ser preservada e a migração SHALL avisar no log que ela foi mantida. As outras lojas MUST NOT ser alteradas.

#### Scenario: Demonstração vazia e sem dona
- **WHEN** a `0009` roda em um banco com a `veste-bem` sem dona e sem entradas
- **THEN** a loja deixa de existir

#### Scenario: Demonstração com entradas
- **WHEN** a `0009` roda em um banco com a `veste-bem` sem dona e com entradas na fila
- **THEN** a loja e as entradas são preservadas e o log avisa que a loja foi mantida

#### Scenario: Loja com dona
- **WHEN** a `0009` roda em um banco em que a `veste-bem` pertence a uma conta
- **THEN** a loja é preservada, com a mesma dona

#### Scenario: Outras lojas
- **WHEN** a `0009` roda em um banco com outras lojas, com ou sem dona
- **THEN** nenhuma outra loja é alterada

### Requirement: Comando para semear a demonstração
`php bin/seed-demo` SHALL criar a loja `Veste Bem` com o slug `veste-bem`, sem dona e sem endereço público, e informar que foi criada. O comando MUST ser idempotente: se já existir uma loja com o slug `veste-bem`, SHALL não alterar nada e avisar que ela já existe. Em um banco não migrado, SHALL falhar com a orientação de rodar `php bin/migrate` antes.

#### Scenario: Criar a demonstração
- **WHEN** o comando roda em um banco migrado sem a loja `veste-bem`
- **THEN** a loja é criada sem dona e sem endereço público, e a saída informa que foi criada

#### Scenario: Rodar de novo
- **WHEN** o comando roda em um banco que já tem a `veste-bem`
- **THEN** nada é alterado e a saída avisa que ela já existe

#### Scenario: Banco sem migrar
- **WHEN** o comando roda em um banco em que as migrações não foram executadas
- **THEN** o comando falha com a orientação de rodar `php bin/migrate` antes e não cria nada

### Requirement: Semear só com confirmação fora do desenvolvimento
Com `DB_DRIVER=mysql`, `php bin/seed-demo` SHALL recusar rodar, sem criar nada e com saída de erro e código diferente de zero, a menos que receba a opção `--forcar`. Com `DB_DRIVER=sqlite`, o comando SHALL rodar sem a opção.

#### Scenario: MySQL sem confirmação
- **WHEN** o comando roda com `DB_DRIVER=mysql` e sem `--forcar`
- **THEN** ele recusa, sai com código diferente de zero e nenhuma loja é criada

#### Scenario: MySQL com confirmação
- **WHEN** o comando roda com `DB_DRIVER=mysql` e com `--forcar` em um banco migrado
- **THEN** a loja de demonstração é criada

#### Scenario: SQLite
- **WHEN** o comando roda com `DB_DRIVER=sqlite`
- **THEN** ele cria a loja de demonstração sem exigir `--forcar`
