## MODIFIED Requirements

### Requirement: Estado de confirmação do e-mail
Toda conta SHALL ter o estado `email_confirmado` (verdadeiro ou falso). Uma conta nova SHALL nascer com o e-mail **não confirmado**. A conta não confirmada MUST poder entrar e usar o dashboard normalmente; a confirmação muda o que o "esqueci a senha" faz (ver `password-recovery`) e protege a conta de ser substituída por um cadastro novo depois do prazo (ver `establishment-account`).

#### Scenario: Conta nova
- **WHEN** uma conta é criada pelo cadastro
- **THEN** o e-mail dela está não confirmado e ela já pode usar o dashboard

#### Scenario: Conta confirmada
- **WHEN** o e-mail da conta é confirmado
- **THEN** `email_confirmado` passa a ser verdadeiro e continua assim nos logins seguintes

## ADDED Requirements

### Requirement: Prazo para confirmar o e-mail
Toda conta não confirmada SHALL ter um prazo para confirmar o e-mail, `confirmar_ate`, de 14 dias a partir do cadastro. Os dados da conta nas respostas de cadastro, login e `GET /api/conta` SHALL incluir `confirmar_ate` (data e hora em UTC, no mesmo formato de `expira_em`) enquanto o e-mail não está confirmado, e `null` depois de confirmado. Trocar o e-mail ou reenviar a confirmação MUST NOT mudar o prazo.

#### Scenario: Conta nova
- **WHEN** uma conta é criada pelo cadastro
- **THEN** a resposta traz `confirmar_ate` 14 dias depois do momento do cadastro

#### Scenario: Conta confirmada
- **WHEN** a conta confirma o e-mail e consulta `GET /api/conta`
- **THEN** `confirmar_ate` é `null`

#### Scenario: Trocar o e-mail não renova o prazo
- **WHEN** a dona com e-mail não confirmado troca o e-mail ou pede o reenvio da confirmação
- **THEN** `confirmar_ate` continua o mesmo

### Requirement: Prazo das contas que já existiam
As contas não confirmadas que já existiam quando esta mudança entrar SHALL receber o prazo de 14 dias contados a partir da entrada desta mudança, e não da data do cadastro delas. As contas já confirmadas MUST NOT receber prazo.

#### Scenario: Conta antiga não confirmada
- **WHEN** uma conta criada meses antes desta mudança, ainda não confirmada, consulta `GET /api/conta` logo depois da mudança
- **THEN** `confirmar_ate` fica 14 dias depois da entrada da mudança e a conta ainda não pode ser substituída

#### Scenario: Conta antiga confirmada
- **WHEN** uma conta confirmada antes desta mudança consulta `GET /api/conta`
- **THEN** `confirmar_ate` é `null`

### Requirement: Prazo na faixa de aviso
Enquanto o e-mail não estiver confirmado e a conta tiver `confirmar_ate`, a faixa "Confirme seu e-mail" SHALL mostrar a data do prazo (dia, mês e ano, no fuso do navegador) e avisar que, depois dela, se a loja ficar 14 dias sem clientes na fila, um cadastro novo com o mesmo e-mail ou CNPJ substitui a conta e apaga a loja. Sem `confirmar_ate`, a faixa SHALL aparecer sem a data.

#### Scenario: Faixa com o prazo
- **WHEN** a dona com e-mail não confirmado e `confirmar_ate` em `2026-10-28 15:00:00` abre o dashboard
- **THEN** a faixa mostra "Confirme até 28/10/2026" e o aviso sobre a substituição da conta

#### Scenario: Conta sem prazo na resposta
- **WHEN** a sessão tem uma conta não confirmada sem `confirmar_ate`
- **THEN** a faixa aparece como antes, sem data e sem quebrar
