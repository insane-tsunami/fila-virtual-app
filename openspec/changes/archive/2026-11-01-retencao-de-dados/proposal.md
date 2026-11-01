# Proposal

## Why

O banco guarda o telefone completo do cliente para sempre: as entradas finalizadas nunca são apagadas, e as ativas de uma loja que fecha sem finalizar ficam na fila indefinidamente (issue #26). Sessões, tokens e contadores vencidos só somem quando há outra atividade. As decisões técnicas da #26 foram aceitas: apagar só o telefone das entradas encerradas há mais de 30 dias, encerrar as ativas esquecidas depois de 24 h (configurável) e não apagar contas inativas. Este é o primeiro dos quatro changes da #26 e vem antes do aviso de privacidade, para que o aviso só prometa o que já existe.

## What Changes

- **Comando de limpeza:** `php bin/limpar`, para a hospedagem rodar por cron (a hospedagem é a #27). Cada execução:
  - encerra como `cancelado` as entradas ativas paradas há mais de 24 h, promovendo a próxima da fila quando a encerrada estava em atendimento;
  - apaga o telefone (fica `null`) das entradas `finalizado` ou `cancelado` encerradas há mais de 30 dias, mantendo código, status e datas;
  - remove as sessões, os tokens de redefinição de senha, os tokens de confirmação de e-mail e os contadores de tentativas vencidos;
  - mostra quantas linhas mudou e sai com erro, sem mexer em nada, se houver migrações pendentes.
- **Prazos configuráveis:** `RETENCAO_ENTRADA_ATIVA_HORAS` (padrão `24`) e `RETENCAO_TELEFONE_DIAS` (padrão `30`).
- **Entrada cancelada na API:** a consulta da própria posição devolve `status` `cancelado` com `posicao` `null`, e o telefone de uma entrada cancelada pode entrar na fila de novo.
- **Página do cliente:** hoje ela só conhece `finalizado`, e uma entrada cancelada ficaria em "Aguardando", consultando para sempre. Passa a mostrar "Entrada encerrada", parar de consultar e oferecer "Entrar na fila de novo".
- **Fora do escopo:** apagar contas inativas ou não confirmadas (decisão D8; a #32 já substitui a conta abandonada no cadastro); sair da fila pelo cliente, aviso de privacidade e exclusão da conta (os outros três changes da #26); limpar logs e backups da hospedagem.

## Capabilities

### New Capabilities
- `data-retention`: o comando de limpeza e as regras de retenção, ou seja, o encerramento das entradas esquecidas, o apagamento do telefone das entradas antigas, a remoção de sessões, tokens e contadores vencidos e os prazos configuráveis.

### Modified Capabilities
- `queue-intake`: a consulta da própria posição e a regra de entrada duplicada passam a tratar o status `cancelado`.
- `customer-queue-page`: o acompanhamento para e mostra "Entrada encerrada" quando a entrada é cancelada.

## Impact

- **Banco:** migração `0012`: `entradas_fila.telefone` passa a aceitar `null` e ganha um índice em `finalizou_em`; `MigrationTest`.
- **API:** serviço novo `Services\RetencaoDeDados`, `bin/limpar`, prazos em `api/config.php` e `.env.example`, `Models\EntradaFila` (telefone anulável, `CANCELADO` deixa de ser só reservado) e testes de serviço e do comando.
- **Front:** `src/Cliente/index.js` e `index.test.js`.
- **Documentação:** `server/README.md` (variáveis, comando, cron na hospedagem e a pendência da LGPD), `README.md` e o contexto de `openspec/config.yaml`.
- Sem dependências novas. Sem cron configurado, nada é limpo: o deploy tem de agendar o comando.
