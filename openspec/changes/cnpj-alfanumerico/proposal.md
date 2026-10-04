# Proposal

## Why

Desde julho de 2026 a Receita Federal emite CNPJs **alfanuméricos** para empresas novas (12 posições com letras e números, mais dois dígitos verificadores). O cadastro de hoje só aceita 14 dígitos, então uma empresa aberta com o formato novo **não consegue criar a conta**. Aproveitando a troca do validador, também passamos a **conferir o dígito verificador**, a única proteção do cadastro contra CNPJ digitado errado.

## What Changes

**Decisões confirmadas pelo usuário na exploração:**
- O cadastro aceita o formato novo (`[0-9A-Z]` nas 12 primeiras posições e dois dígitos no fim, com ou sem máscara) **e confere o dígito verificador**, tanto nos CNPJs alfanuméricos quanto nos numéricos (módulo 11, cada caractere vale o código ASCII menos 48).

**Padrões propostos e confirmados ("segue"):** o CNPJ é guardado em maiúsculas; CNPJ com os 14 caracteres iguais é recusado; a mensagem de erro manda conferir os dígitos; nenhuma migração; o campo de CNPJ do cadastro põe as letras em maiúsculas ao digitar e a máscara do Perfil passa a reconhecer letras.

**Backend (`server/`)**
- `Support\Cnpj::normalizar` passa a aceitar letras, converter para maiúsculas, conferir o DV (e recusar os 14 caracteres iguais). O valor guardado continua sendo 14 caracteres sem máscara, então a coluna `contas.cnpj` (`char(14)`, índice único) **não muda**.
- A mensagem de `422` do cadastro passa a dizer para conferir os caracteres e os dígitos verificadores.
- Unicidade independente de maiúsculas: `12.abc...` e `12.ABC...` são o mesmo CNPJ (o SQLite diferencia a caixa; o MySQL não).

**Front (React)**
- O campo "CNPJ" do cadastro converte para maiúsculas ao digitar.
- O Perfil exibe CNPJs alfanuméricos com a máscara `XX.XXX.XXX/XXXX-XX`.
- **Sem validação de DV no front:** o servidor é a única fonte da regra, e o front mostra a mensagem do `422`.

**Dados de teste e documentação**
- O CNPJ de teste `93.339.970/0001-05` (usado em 61 pontos de 17 arquivos) tem dígito verificador **válido** (`05`), então **nenhum dado de teste precisa mudar**. Os testes novos acrescentam vetores alfanuméricos válidos (inclusive o exemplo oficial `12.ABC.345/01DE-35`) e de dígito errado.
- `server/README.md`, o `README.md` do front e o contexto do `openspec/config.yaml` deixam de dizer que o CNPJ "não é validado".

## Capabilities

### New Capabilities
Nenhuma.

### Modified Capabilities
- `establishment-account`: a validação do CNPJ aceita letras, confere o DV, guarda em maiúsculas e recusa 14 caracteres iguais; a unicidade vale também entre caixas diferentes.
- `establishment-signup`: o campo "CNPJ" converte para maiúsculas ao digitar.
- `establishment-profile`: o CNPJ alfanumérico aparece com máscara.

## Impact

- **Backend:** `server/app/support/Cnpj.php`, `server/app/services/ContaService.php` (mensagem), `server/tests/` (`CnpjTest`, `ContaServiceTest`, `AccountApiTest`), `server/README.md`.
- **Front:** `src/Register/index.js`, `src/Dashboard/Perfil.js`, os testes correspondentes e `README.md`.
- **Banco, dependências e CI:** nenhuma migração, nenhuma dependência nova e nenhum job novo; os 4 jobs existentes continuam sendo a barreira.
- **Incompatibilidade:** CNPJs numéricos com DV inválido, que hoje são aceitos, passam a ser recusados. Não há conta real em produção, então nada precisa ser migrado.
- **Fora desta mudança:** provar que o CNPJ pertence a quem se cadastra (consulta à Receita, confirmação de e-mail), limite de tentativas, "esqueci a senha", e qualquer validação do CNPJ no front.
- **Riscos registrados:** se a regra da Receita tivesse algum detalhe diferente do implementado, CNPJs reais seriam recusados (mitigado pelo exemplo oficial `12.ABC.345/01DE-35` e por vetores fixos nos testes); o DV só pega erro de digitação, não prova a titularidade.
