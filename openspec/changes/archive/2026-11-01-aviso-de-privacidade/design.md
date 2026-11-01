# Design

## Context

- A API lê toda a configuração de variáveis de ambiente em `api/config.php` e não expõe segredos ao front; os prazos de retenção já vivem lá (`retencao.entrada_ativa_horas` e `retencao.telefone_dias`).
- O front é um CRA separado, com `REACT_APP_*` gravado no build; um valor que muda no servidor exigiria rebuild se ficasse no front.
- As páginas públicas (`Home`, `Cliente`, `Login`, `Register`) não têm rodapé comum; cada uma tem o seu layout.
- O cadastro envia só `email`, `cnpj`, `nome` e `senha`; o backend não guarda aceite.
- Base legal escolhida para o telefone: legítimo interesse (art. 7º, IX). Para a conta da loja: execução de contrato (art. 7º, V). A loja é controladora dos dados da fila; a plataforma atua como operadora da fila e controladora dos dados da conta.

## Goals / Non-Goals

**Goals:**
- Um único texto-fonte do aviso, com os prazos reais da retenção, sem duplicar números no front.
- Aviso visível onde o telefone é coletado e no cadastro da loja.
- Nenhum dado novo guardado e nenhum campo novo obrigatório na API.

**Non-Goals:**
- Contrato entre a plataforma e as lojas, política de incidentes, transferência internacional e revisão jurídica (ficam documentados como pendência).
- Persistir o aceite no banco (o aceite é ciência do aviso, não consentimento; a base legal é legítimo interesse).

## Decisions

### 1. `GET /api/privacidade` público em vez de valores no build

O endpoint devolve `controlador`, `email`, `encarregado`, `retencao_telefone_dias`, `retencao_entrada_ativa_horas` e `base_legal`. Assim o prazo exibido é o mesmo que o `php bin/limpar` aplica, sem rebuild do front e sem duplicar a configuração. Não devolve nada sensível.

### 2. Padrões provisórios e explícitos

`PRIVACIDADE_CONTROLADOR` tem padrão `ZeraFilas`; `PRIVACIDADE_EMAIL` tem padrão `privacidade@zerafilas.example` (TLD reservado pela RFC 2606, para nunca parecer um e-mail real) e `PRIVACIDADE_ENCARREGADO` é opcional. O `server/README.md` diz que esses valores são um rascunho e precisam ser trocados antes de produção.

### 3. Página `/privacidade` estática com dados do endpoint

A página tem o texto fixo (finalidades, dados, direitos) e interpola controlador, contato, base legal e prazos vindos de `GET /api/privacidade`. Enquanto carrega, mostra "Carregando..."; se a API falhar, mostra o texto-base com um aviso de que os dados de contato não puderam ser carregados, sem esconder o resto. A página não exige login.

### 4. Aviso curto na fila

Sob o campo de telefone: "Usamos seu telefone apenas para organizar a fila desta loja. Ele é apagado {N} dias depois do fim do atendimento." com link para `/privacidade`. O número vem do mesmo endpoint; se ele falhar, a frase fica sem o número ("... é apagado depois do prazo do aviso.") e a entrada na fila continua funcionando.

### 5. Checkbox no cadastro, sem mudança na API

O checkbox "Li e aceito o Aviso de Privacidade" é obrigatório na tela e o botão "Cadastrar" fica desabilitado sem ele. O envio à API continua com os mesmos quatro campos: como a base legal é legítimo interesse, não há consentimento a registrar; o aviso já está publicado e o aceite é ciência. Isso evita migração, não quebra clientes da API e não transforma o aviso em consentimento (o que exigiria revogação).

### 6. Links

`/privacidade` aparece no rodapé da `Home` e no Perfil. A página é pública e fica fora de `RotaProtegida`.

## Risks / Trade-offs

- **[Texto sem revisão jurídica]** → Fica declarado no README como rascunho; o e-mail de contato padrão é inválido de propósito, para não passar por real.
- **[Falha da API esconde contato]** → A página mostra o texto-base mesmo assim e o README orienta configurar; a fila não depende do aviso.
- **[Legítimo interesse sem LIA formal]** → O teste de balanceamento é escrito no README para revisão; a base pode mudar sem tocar no código.
- **[Checkbox não registrado]** → Não é consentimento; o registro seria útil para a conta, mas fica fora do escopo para não mudar o contrato da API.
