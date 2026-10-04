# email-delivery Specification

## Purpose
Define como a API envia e-mails (começando pela redefinição de senha), com o meio de envio escolhido por configuração para não prender o projeto a um provedor.

## Requirements

### Requirement: Driver de envio configurável
A API SHALL escolher o meio de envio pela variável `MAIL_DRIVER`, com os valores `desligado`, `log` e `smtp`. Sem a variável, ou com valor desconhecido, SHALL valer `desligado`, e o valor desconhecido SHALL ser registrado no log.

#### Scenario: Padrão
- **WHEN** `MAIL_DRIVER` não está definida
- **THEN** a API usa o driver `desligado`

#### Scenario: Valor desconhecido
- **WHEN** `MAIL_DRIVER` tem um valor que não é `desligado`, `log` nem `smtp`
- **THEN** a API usa `desligado` e registra no log que o valor não é reconhecido

### Requirement: Driver desligado
Com o driver `desligado`, a API MUST NOT enviar nem gravar o conteúdo das mensagens, e SHALL registrar no log um aviso (sem o link nem o token) de que o envio está desligado.

#### Scenario: Envio desligado
- **WHEN** um e-mail precisaria ser enviado e o driver é `desligado`
- **THEN** nenhum e-mail sai, o log recebe um aviso sem o link e a requisição segue normalmente

### Requirement: Driver de log
Com o driver `log`, a API SHALL gravar no log a mensagem inteira (destinatário, assunto e texto, inclusive o link) em vez de enviá-la. Esse driver MUST ser documentado como de uso só em desenvolvimento e testes, porque o link de redefinição é um segredo.

#### Scenario: Mensagem no log
- **WHEN** um e-mail é enviado com o driver `log`
- **THEN** o log traz o destinatário, o assunto e o texto, e nenhum e-mail sai de verdade

### Requirement: Driver SMTP
Com o driver `smtp`, a API SHALL enviar a mensagem pelo servidor indicado em `MAIL_DSN`, com remetente `MAIL_FROM`. Se `MAIL_DSN` ou `MAIL_FROM` faltarem, ou o servidor recusar a mensagem, o envio SHALL falhar com um erro registrado no log e MUST NOT incluir a senha do `MAIL_DSN` nem o link no log.

#### Scenario: Envio pelo servidor SMTP
- **WHEN** o driver é `smtp` com `MAIL_DSN` e `MAIL_FROM` válidos
- **THEN** a mensagem é entregue ao servidor SMTP com o remetente `MAIL_FROM`

#### Scenario: Configuração incompleta
- **WHEN** o driver é `smtp` e `MAIL_DSN` ou `MAIL_FROM` não estão definidas
- **THEN** o envio falha, o erro vai para o log sem o DSN nem o link, e a requisição segue

#### Scenario: Servidor recusa
- **WHEN** o servidor SMTP recusa a mensagem
- **THEN** a falha é registrada no log sem a senha do DSN e sem o link

### Requirement: Mensagens em texto simples
As mensagens SHALL ser em português, em texto simples, e o destinatário e o assunto MUST NOT aceitar quebras de linha que permitam injetar cabeçalhos.

#### Scenario: Texto simples em português
- **WHEN** a mensagem de redefinição é montada
- **THEN** ela é em português, só texto, com assunto próprio

#### Scenario: Quebra de linha no destinatário
- **WHEN** um endereço de destino traz quebra de linha
- **THEN** a mensagem não é enviada
