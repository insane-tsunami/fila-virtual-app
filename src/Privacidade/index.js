import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Typography from '@material-ui/core/Typography';

import { buscarPrivacidade } from '../api';
import { Pagina, Cartao } from './styles';

export default function Privacidade() {
  const [aviso, setAviso] = useState(null);
  const [falhou, setFalhou] = useState(false);

  useEffect(() => {
    let cancelado = false;
    buscarPrivacidade()
      .then((dados) => {
        if (!cancelado) setAviso(dados);
      })
      .catch(() => {
        if (!cancelado) setFalhou(true);
      });
    return () => {
      cancelado = true;
    };
  }, []);

  const controlador = (aviso && aviso.controlador) || 'ZeraFilas';
  const prazos = aviso
    ? `O telefone é apagado ${aviso.retencao_telefone_dias} dias depois do fim do atendimento, e uma entrada ativa parada há mais de ${aviso.retencao_entrada_ativa_horas} horas é encerrada.`
    : 'Os prazos de apagamento são os do aviso vigente no momento em que você usa a fila.';

  return (
    <Pagina>
      <Cartao>
        <h1>Aviso de Privacidade</h1>
        {falhou && (
          <Typography role="alert">
            Não foi possível carregar a configuração do aviso agora. Os dados de
            contato aparecem quando a API voltar.
          </Typography>
        )}

        <h2>Quem trata seus dados</h2>
        <p>{`O ZeraFilas é operado por ${controlador}.`}</p>
        {aviso && aviso.email && (
          <p>
            {`Contato de privacidade: ${aviso.email}.`}
            {aviso.encarregado ? ` Encarregado: ${aviso.encarregado}.` : ''}
          </p>
        )}
        <p>
          Na fila de uma loja, a loja decide para que o telefone é usado e é a
          controladora desses dados; o ZeraFilas atua como operador. Os dados da
          conta da loja são controlados pelo ZeraFilas para prestar o serviço.
        </p>

        <h2>Quais dados tratamos</h2>
        <ul>
          <li>
            Fila: telefone, código da entrada, situação (aguardando, em
            atendimento ou encerrada) e horários.
          </li>
          <li>
            Conta da loja: e-mail, CNPJ, hash da senha, sessões, tokens de
            confirmação e de redefinição de senha e contadores de tentativas.
          </li>
        </ul>

        <h2>Por que tratamos e com que base legal</h2>
        <p>
          {`O telefone é usado para organizar a fila da loja, com base no ${
            (aviso && aviso.base_legal) || 'legítimo interesse'
          }. Os dados da conta são tratados para prestar o serviço contratado, com base na execução de contrato.`}
        </p>

        <h2>Por quanto tempo guardamos</h2>
        <p>{prazos}</p>
        <p>
          As sessões valem 7 dias; os links de redefinição de senha, 1 hora; os
          de confirmação de e-mail, 24 horas; os contadores de tentativas vencem
          sozinhos. A conta e a loja ficam no ar até você pedir a exclusão.
        </p>

        <h2>Compartilhamento</h2>
        <p>
          Os dados ficam com o provedor de hospedagem que opera a plataforma e
          não são vendidos. A loja vê o telefone mascarado no dashboard; o
          número completo não é exibido.
        </p>

        <h2>Seus direitos</h2>
        <p>
          Você pode pedir acesso, correção e eliminação dos seus dados. Para
          eliminar o telefone da fila, use o botão &quot;Sair da fila&quot; na
          página da loja: o número é apagado na hora. A dona da loja exclui a
          conta e tudo o que é dela em Configurações, no Perfil. Para outros
          pedidos, use o contato de privacidade acima.
        </p>

        <p>
          <Link to="/">Voltar para a página inicial</Link>
        </p>
      </Cartao>
    </Pagina>
  );
}
