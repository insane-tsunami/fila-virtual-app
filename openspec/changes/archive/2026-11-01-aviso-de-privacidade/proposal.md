# Proposal

## Why

O sistema trata telefone de clientes e dados da conta da loja sem aviso de privacidade. A LGPD (art. 9º) pede finalidade, controlador, contato, retenção e direitos de forma clara nos pontos em que o dado é coletado. A retenção já está implementada (`data-retention`) e o titular já pode sair da fila (`sair-da-fila`); falta dizer isso a quem usa o sistema.

## What Changes

- **Aviso completo em `/privacidade`:** página pública com controlador, contato do encarregado, quais dados são tratados (fila e conta), finalidade, base legal, prazos de retenção, compartilhamento, direitos e como exercê-los. O texto mostra os prazos reais da configuração (`RETENCAO_*`).
- **Aviso curto na página do cliente:** abaixo do campo de telefone, informando a finalidade, o prazo de apagamento e o link para `/privacidade`.
- **Aceite no cadastro da loja:** checkbox obrigatório "Li e aceito o Aviso de Privacidade", com link para `/privacidade`, antes de "Cadastrar".
- **Links:** `/privacidade` no rodapé da página inicial e no Perfil.
- **Configuração:** `PRIVACIDADE_CONTROLADOR`, `PRIVACIDADE_EMAIL` e `PRIVACIDADE_ENCARREGADO` no ambiente da API, expostos por `GET /api/privacidade` (público) junto dos prazos de retenção. O padrão do e-mail é provisório e fica documentado como pendência de revisão jurídica.
- **Base legal declarada:** legítimo interesse (art. 7º, IX) para o telefone da fila; execução de contrato (art. 7º, V) para os dados da conta da loja. O teste de balanceamento do legítimo interesse vai no `server/README.md`.

## Capabilities

### New Capabilities
- `privacy-notice`: o aviso público, o resumo na coleta do telefone, o aceite no cadastro e a configuração do controlador/contato/prazos.

### Modified Capabilities
- `establishment-signup`: o formulário passa a exigir o aceite do aviso.
- `customer-queue-page`: o formulário de entrada passa a mostrar o resumo do aviso.

## Impact

- **API:** seção `privacidade` em `api/config.php`, rota pública `GET /api/privacidade` em `Support\Aplicacao`, `Sync/controllers` (controller novo `PrivacidadeController`), testes de config e de API.
- **Front:** página `src/Privacidade`, aviso em `src/Cliente`, checkbox em `src/Register`, links em `src/Home` e `src/Dashboard/Perfil`, `src/api.js`, rota em `src/App.js`; testes.
- **Documentação:** `server/README.md` (variáveis, endpoint, teste de balanceamento e pendências), `README.md` e `openspec/config.yaml`.
- **Fora do escopo:** contrato/termo entre a plataforma e as lojas, nomeação formal de encarregado, transferência internacional e plano de incidente (dependem da hospedagem e de revisão jurídica). O texto é um rascunho operacional e não substitui revisão por advogado.
- Sem migração e sem dependências novas.
