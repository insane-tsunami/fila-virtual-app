# Spec Delta

## ADDED Requirements

### Requirement: CNPJ em maiúsculas ao digitar
O campo "CNPJ" da página `/cadastro` SHALL converter as letras digitadas ou coladas para maiúsculas, e SHALL enviar o valor em maiúsculas à API. O front MUST NOT conferir o dígito verificador: a recusa vem da API e a página mostra a mensagem recebida.

#### Scenario: Digitar em minúsculas
- **WHEN** o visitante digita `12.abc.345/01de-35` no campo "CNPJ"
- **THEN** o campo passa a mostrar `12.ABC.345/01DE-35`

#### Scenario: Colar em minúsculas
- **WHEN** o visitante cola `12abc34501de35` no campo "CNPJ"
- **THEN** o campo mostra `12ABC34501DE35` e o cadastro envia esse valor

#### Scenario: Dígito verificador errado
- **WHEN** o visitante envia o cadastro com um CNPJ de dígito verificador errado e a API recusa com `422`
- **THEN** a página mostra a mensagem recebida, mantém o CNPJ digitado e continua em `/cadastro`
