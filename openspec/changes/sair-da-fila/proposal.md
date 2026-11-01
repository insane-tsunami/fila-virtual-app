# Proposal

## Why

Hoje o cliente que entrou na fila não tem como sair: só a dona da loja finaliza o atendimento, e a retenção só encerra a entrada 24 h depois. Para a LGPD (direito à eliminação, art. 18), quem entrou precisa conseguir sair na hora, e o telefone não deve continuar guardado depois que a finalidade acabou. Este é o segundo dos quatro changes da #26.

## What Changes

- **Sair da fila pela API:** `DELETE /api/filas/{slug}/entradas/{codigo}` (público, como a consulta) encerra a entrada ativa do próprio código como `cancelado`, grava a hora do encerramento e apaga o telefone na mesma operação. Se a entrada estava em atendimento, a ativa mais antiga da loja é promovida, como em `finalizar`.
- **Idempotência:** chamar de novo para uma entrada já encerrada responde `200` com o estado atual, sem erro e sem mudar nada. Código inexistente ou de outra loja continua `404`.
- **Página do cliente:** enquanto a entrada está ativa, mostra "Sair da fila". Ao sair, volta ao formulário e apaga o código guardado; falha de rede mantém a entrada na tela e avisa.
- **Documentação:** a saída pela própria página entra como o caminho do titular para eliminar o telefone (junto do contato do aviso de privacidade, no change seguinte).

## Capabilities

### New Capabilities
Nenhuma.

### Modified Capabilities
- `queue-intake`: nova operação pública de saída e a regra de que a entrada sai da fila ativa.
- `customer-queue-page`: botão "Sair da fila" no acompanhamento.

## Impact

- **API:** `FilaService::sair`, `ClienteController::sair`, rota `DELETE` em `Support\Aplicacao`, testes de serviço e de API.
- **Front:** `src/Cliente/index.js`, `src/api.js` e `src/Cliente/index.test.js`.
- **Documentação:** `server/README.md` (endpoint) e `README.md` (fluxo do cliente).
- Sem migração e sem dependências novas.
