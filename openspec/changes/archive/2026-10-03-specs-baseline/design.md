# Design

## Context

Hoje só `queue-dashboard` tem spec. As outras páginas foram lidas em `src/Home`, `src/Login`, `src/Register`, `src/Dashboard/{Nav,Qrcode,Perfil}.js` e `src/App.js`. O que o código faz de fato: nenhum formulário tem `onSubmit` e nenhum botão (exceto "Finalizar Atendimento") tem `onClick` com efeito; `Perfil` mostra e-mail e CNPJ fixos; "Sair" é um `Link` para `/`; as rotas `/dashboard/*` não exigem login; e uma URL inexistente renderiza uma página em branco (o `Switch` não tem rota padrão). Ver proposal.md - Why.

## Goals / Non-Goals

**Goals:**
- Specs que descrevam só o comportamento observável hoje, sem prometer o que não existe.
- Tornar visível, e testável, o que é só interface.
- Deixar um alvo claro de `MODIFIED` para as mudanças de autenticação, QR code e perfil.

**Non-Goals:**
- Alterar qualquer arquivo de produção ou comportamento.
- Especificar a rota inexistente: a página em branco é uma lacuna, não um comportamento desejado, e não vira requisito.

## Decisions

1. **"Estado atual" como requisito explícito.** Cada capability que é só interface ganha um requisito `... sem integração (estado atual)` com `MUST NOT` para envio, navegação ou efeito. Quando a integração chegar, a mudança correspondente faz `MODIFIED` nele em vez de criar um requisito paralelo. *Alternativa:* deixar o gap só em prosa no design; descartada porque a spec ficaria descrevendo telas que parecem funcionar.

2. **Seis capabilities, uma por tela ou conjunto coeso**, em vez de uma spec única "frontend". Cada uma tem dono claro e cresce sozinha: `establishment-login` vira autenticação, `qrcode-generation` vira geração real, etc. A marca e o slogan aparecem nos requisitos de `landing-page`; as páginas de login e cadastro repetem a marca no código, mas isso é identidade visual e não ganha requisito próprio.

3. **Rotas e menu juntos em `dashboard-navigation`**, porque o menu é a única forma de navegar entre as rotas e os dois mudam juntos. A identificação do estabelecimento na barra lateral continua em `queue-dashboard` e não é repetida.

4. **Teste de renderização por tela** (Jest + Testing Library, `MemoryRouter`, mesmo padrão de `src/Dashboard/index.test.js`), com cenários 1:1 com os das specs. Para os cenários de navegação, o teste verifica o destino do link (`href`), em vez de montar o roteador completo. Para "não envia nada", o teste aciona o botão e confirma que não há requisição (`fetch` não é chamado) e que o caminho atual não muda. *Alternativa:* só documentar; descartada porque uma spec sem teste vira outra afirmação não verificada.

5. **Linguagem dos requisitos:** textos de tela citados entre aspas exatamente como no código (incluindo "Não é possivel", sem acento em "possivel"), para o teste poder buscá-los literalmente. Corrigir a ortografia dos textos é outra mudança.

## Risks / Trade-offs

- [Os requisitos "estado atual" ficam obsoletos quando a integração chegar] → É o objetivo: cada mudança de integração os modifica ou remove, e o teste correspondente quebra e avisa.
- [Os testes travam textos que podem ser reescritos] → Aceito; alterar um texto de tela passa a exigir atualizar spec e teste, que é o comportamento desejado de uma baseline.
- [Os testes nomeiam textos com erro de ortografia] → Mantidos como estão (decisão 5); corrigir texto exige mudança de spec explícita.

## Migration Plan

Só adiciona specs e testes; sem deploy. Rollback: reverter o commit.
