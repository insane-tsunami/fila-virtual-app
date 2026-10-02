# Design

## Context

Hoje `src/Dashbboard/index.js` declara a fila como array de módulo (`data`), copia esse array para o estado e, ao finalizar um atendimento, **muda o array de módulo** (`shift` e reatribuição de `status`) e dispara um `useEffect` para recopiá-lo. Cada item guarda seu próprio `status` (`current`, `next`, `wait`), que é manipulado à mão. As três páginas do dashboard (`index.js`, `Qrcode.js`, `Perfil.js`) repetem o bloco da barra lateral com "Veste Bem" e a inicial "V", e `Qrcode.js`/`Perfil.js` usam `classes.large`, que só existe no `makeStyles` de `index.js`. Ver proposal.md - Why.

## Goals / Non-Goals

**Goals:**
- Fila apenas em estado do React, atualizada de forma imutável, pronta para trocar a fonte por uma API.
- Um único lugar para os dados do estabelecimento e um único estilo de avatar da barra.
- Poder rodar `yarn lint` sem configuração extra.

**Non-Goals:**
- Extrair um componente de layout compartilhado (Wrapper + BarNavigation + Nav) das três páginas. É uma melhoria natural, mas aumenta o diff e fica para outra mudança.
- Qualquer mudança visual além do tamanho do avatar.
- Persistir o progresso da fila entre navegações (depende de API).

## Decisions

1. **Estado imutável com atualização funcional.** `useState(listaInicial)` e, em "Finalizar Atendimento", `setClients((atual) => atual.slice(1))`. Remove o `useEffect`, o estado `update` e a mutação do módulo. *Alternativa:* `useReducer`; descartada por ser mais cerimônia do que a única ação existente justifica.

2. **Derivar `status` da posição na lista**, em vez de armazená-lo: índice 0 = em atendimento, índice 1 = próximo, demais = aguardando. Elimina o código que reatribuía `status` à mão e o risco de ficar inconsistente com a ordem. O campo `status` sai dos dados de exemplo; o componente `Client` continua recebendo a prop `status`, agora calculada na renderização.

3. **Condicionais booleanas explícitas** (`clients.length > 0 && ...`, `clients.length === 0 && ...`) para nunca renderizar `0`. A mensagem "Fila vazia" mantém a condição atual (`clients.length === 1`), que o spec descreve como "apenas o cliente atual".

4. **Dados do estabelecimento em um módulo simples** (`src/Dashboard/estabelecimento.js`) exportando `{ nome, inicial }`, em vez de Context. Hoje é um valor constante e fictício; Context só se justifica quando houver login e dados vindos do servidor. *Alternativa:* Context agora; descartada por antecipar uma necessidade que ainda não existe.

5. **Avatar da barra:** mover o estilo `large` para onde seja compartilhado pelas três páginas. A opção mais simples é um estilo único no `styles.js` já compartilhado (por exemplo, um styled `BarAvatar`), em vez de duplicar `makeStyles` em cada página.

6. **Renomear com `git mv`** (`Dashbboard` → `Dashboard`) em um commit separado das mudanças de conteúdo, para o Git preservar o histórico dos arquivos. Atenção a sistemas de arquivos sem distinção de maiúsculas: o nome muda de letras, não de caixa, então não há risco de colisão.

7. **Script `lint`:** `eslint src --ext .js`. O `.eslintrc.js` usa `babel-eslint`, que já vem com `react-scripts`; só se adiciona dependência se o lint falhar por isso.

## Risks / Trade-offs

- [O progresso da fila reinicia ao voltar para o dashboard] → Aceito: hoje só "funciona" pela mutação do módulo, com dados fictícios. Registrado na proposta como mudança de comportamento.
- [O lint pode apontar erros do `airbnb`/`prettier` em arquivos existentes] → Corrigir o que estiver nos arquivos tocados por esta mudança; o que sobrar em `Home`, `Login` e `Register` é listado e tratado na mudança de CI, sem ampliar o escopo aqui.
- [Imports quebrados após o rename] → `yarn build` precisa passar; `App.js` é o único importador externo da pasta.
- [Sem testes automatizados] → Esta mudança adiciona um teste (Jest + Testing Library, já presentes nas dependências) cobrindo os cenários do spec `queue-dashboard`, além de `yarn build` e `yarn lint`. A execução automática em CI fica para a mudança de CI.

## Migration Plan

Mudança só de front, sem deploy especial. Rollback: reverter o commit. As rotas não mudam, então nenhum link externo quebra.
