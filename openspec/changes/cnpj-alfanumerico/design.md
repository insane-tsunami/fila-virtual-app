# Design

## Context

Estado atual verificado. `Support\Cnpj::normalizar` (usado só por `ContaService::cadastrar`) descarta a máscara (`.`, `-`, `/` e espaços) e aceita exatamente 14 **dígitos**, sem conferir o dígito verificador. `contas.cnpj` é `char(14)` com índice único (migração `0005`), então comporta 14 caracteres quaisquer. No front, o campo "CNPJ" do cadastro é um texto livre (`src/Register/index.js`) e o Perfil formata o CNPJ com `replace` por uma expressão só de dígitos (`mascararCnpj`, em `src/Dashboard/Perfil.js`). O CNPJ de teste `93339970000105` (e a forma com máscara) aparece 61 vezes em 17 arquivos de teste e de documentação; ele tem DV válido (`05`), então continua servindo. Ver proposal.md - Why e Impact.

**A regra da Receita (IN RFB 2.229/2024), como aplicada aqui.** O CNPJ tem 14 posições: as 12 primeiras são `[0-9A-Z]` (raiz, ordem do estabelecimento) e as 2 últimas são dígitos verificadores numéricos. CNPJs numéricos continuam valendo. O dígito verificador é módulo 11: cada caractere vale o seu código ASCII menos 48 (`0`–`9` valem 0–9, `A` vale 17, `Z` vale 42); o primeiro DV usa os pesos 5,4,3,2,9,8,7,6,5,4,3,2 sobre as 12 posições; o segundo usa 6,5,4,3,2,9,8,7,6,5,4,3,2 sobre as 12 posições mais o primeiro DV; em cada um, `r = soma % 11` e o dígito é `0` se `r < 2` e `11 - r` nos demais casos. A regra é retrocompatível: para CNPJs só com dígitos dá o mesmo resultado de sempre. **Verificação feita no planejamento:** o cálculo reproduz o exemplo oficial `12.ABC.345/01DE-35` e os numéricos `11.111.111/0001-91` e `22.222.222/0001-91`; a tarefa 1.1 repete isso nos testes e inclui conferir a regra contra o documento oficial da Receita.

## Goals / Non-Goals

**Goals:**
- Aceitar o formato alfanumérico e recusar CNPJ com dígito verificador errado, de forma que a regra fique em **um só lugar** (o servidor).
- Guardar um valor canônico (14 caracteres, maiúsculas, sem máscara) para a unicidade valer em qualquer banco.

**Non-Goals:**
- Provar que o CNPJ é de quem se cadastra (consulta à Receita, confirmação de e-mail).
- Validar o CNPJ no front (duplicaria o algoritmo) ou mudar o banco.
- Máscara automática ao digitar no cadastro (só a caixa alta).

## Decisions

1. **Um passo só no `Support\Cnpj::normalizar`, nesta ordem:** aceitar só `string`; descartar a máscara (`[.\-\/\s]`); passar para maiúsculas (`strtoupper`, que em PHP 8 só mexe em ASCII e não depende de locale); exigir `\A[0-9A-Z]{12}[0-9]{2}\z`; recusar os 14 caracteres iguais; conferir o DV. Devolve os 14 caracteres ou `null`. O DV mora em um método público `digitosVerificadores(string $base): string`, que os testes também usam. Manter `normalizar` com a mesma assinatura evita mexer em `ContaService` além da mensagem.

2. **Os 14 caracteres iguais são recusados, mas só `00000000000000` passa pela conta.** Conferi os 10 casos de dígito repetido: o DV de `0…0` é `00` (aceito pelo algoritmo) e todos os outros dão DV diferente (`11111111111180`, por exemplo, não tem DV repetido e **não** é "14 iguais"). A regra explícita só é necessária para o de zeros, mas vale para qualquer caractere repetido, como nos validadores comuns. *Alternativa:* só zeros; descartada por ser um caso especial sem motivo.

3. **Maiúsculas no servidor e também no front.** O servidor sempre converte (a API pode ser chamada sem o front); o front converte ao digitar e ao colar, para a pessoa ver o que será enviado. A conversão no front é no valor do campo, não só em CSS (`text-transform`), para o estado e o que se envia serem iguais; o custo é que, ao digitar uma letra no meio do texto, o cursor pode pular para o fim (digitar em sequência e colar funcionam normalmente).

4. **Sem migração e sem mexer em `contas`.** `char(14)` e o índice único bastam. O índice é sensível à caixa no SQLite e insensível no MySQL, e a normalização para maiúsculas torna os dois iguais na prática. Não há conta real para converter; contas de desenvolvimento com CNPJ de DV inválido continuam existindo e conseguem entrar (o login não confere o CNPJ), só não seriam aceitas se fossem cadastradas de novo.

5. **Mensagem de erro:** `CNPJ inválido: confira os caracteres e os dígitos verificadores.` (continua começando com "CNPJ", como os testes e o front esperam). `ContaService` só troca o texto.

6. **Máscara de exibição no Perfil.** `mascararCnpj` passa a aceitar `[0-9A-Z]` nas 12 primeiras posições e dígitos nas 2 últimas (`XX.XXX.XXX/XXXX-DD`); se o valor não casar, mostra o texto como está (sem quebrar).

7. **Dados de teste.** Nenhum dado existente precisa mudar: `93339970000105` (o CNPJ de teste de sempre), `11111111000191` e `22222222000191` têm DV válido. Os vetores novos dos testes do validador:

   | Valor | Esperado |
   |---|---|
   | `93.339.970/0001-05`, `11.222.333/0001-81`, `11.111.111/0001-91`, `22.222.222/0001-91` | válidos (numéricos) |
   | `12.ABC.345/01DE-35` (oficial), `AB12CD34EF5602`, `ZZZZZZZZ000191`, `A1B2C3D4E5F668` | válidos (alfanuméricos) |
   | `12.abc.345/01de-35` | válido, guardado como `12ABC34501DE35` |
   | `12ABC34501DE36`, `11222333000180`, `93339970000106` | DV errado |
   | `00000000000000` | recusado (14 iguais) |
   | `12ABC34501DEAB`, `12ABC34501DE3`, `12ABC34501DE355` | recusados (letra no DV, 13 e 15 caracteres) |

## Risks / Trade-offs

- [Algum detalhe da regra difere do oficial e CNPJs reais são recusados] → O exemplo oficial e dois numéricos reais já batem; a tarefa 1.1 confere o documento da Receita e fixa os vetores. Se algum detalhe divergir, a correção é pontual no `Cnpj`.
- [CNPJs numéricos com DV inválido, hoje aceitos, passam a ser recusados] → Mudança incompatível intencional; sem dado real a migrar.
- [O DV não prova a titularidade] → Fora do escopo; continua junto da confirmação de e-mail.
- [Cursor pula ao digitar uma letra no meio do campo] → Aceito (decisão 3); o caso comum é digitar em sequência ou colar.
- [Quem tem CNPJ com letras e um validador antigo em outro sistema] → Não se aplica: o CNPJ só é usado dentro do ZeraFilas.

## Migration Plan

Sem migração de dados. Para subir: nada além do deploy normal. Rollback: reverter o commit; contas criadas com CNPJ alfanumérico continuam no banco (`char(14)` comporta) e a versão anterior só não as aceitaria em um novo cadastro.
