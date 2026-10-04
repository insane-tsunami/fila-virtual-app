import React, { useEffect, useState } from 'react';
import { Link, useHistory, useLocation } from 'react-router-dom';

import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import Moldura from '../Login/Moldura';
import { Bottom } from '../Login/styles';
import { confirmarEmail } from '../api';

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const LINK_INVALIDO = 'Link inválido. Peça um novo e-mail de confirmação.';
const PEDIR_OUTRO =
  'Entre no ZeraFilas e peça um novo link na faixa "Confirme seu e-mail".';

// O token vem no fragmento do link (#token=...): não vai para logs nem para o Referer.
function lerToken(hash) {
  const achado = /(?:^#|&)token=([^&]*)/.exec(hash || '');
  if (!achado) return '';
  try {
    return decodeURIComponent(achado[1]);
  } catch (e) {
    return '';
  }
}

// Página aberta pelo link do e-mail. Funciona com ou sem login (o link costuma abrir em
// outra aba ou aparelho), por isso a rota não usa RotaAnonima.
export default function ConfirmarEmail() {
  const history = useHistory();
  const location = useLocation();
  const [token, setToken] = useState(() => lerToken(location.hash));
  const [resultado, setResultado] = useState(
    token ? { estado: 'confirmando' } : { estado: 'erro', texto: LINK_INVALIDO }
  );

  // Tira o token do endereço assim que ele foi lido. Um link novo aberto nesta mesma página
  // (só o fragmento muda, a página não recarrega) troca o token e confirma de novo.
  useEffect(() => {
    const novo = lerToken(location.hash);
    if (novo) setToken(novo);
    if (location.hash) history.replace(location.pathname);
  }, [history, location.hash, location.pathname]);

  useEffect(() => {
    if (!token) return undefined;
    setResultado({ estado: 'confirmando' });
    let cancelado = false;
    confirmarEmail(token)
      .then((dados) => {
        if (!cancelado) setResultado({ estado: 'ok', texto: dados.mensagem });
      })
      .catch((e) => {
        if (cancelado) return;
        setResultado({
          estado: 'erro',
          texto: e.status === 0 ? ERRO_REDE : e.message,
        });
      });
    return () => {
      cancelado = true;
    };
  }, [token]);

  return (
    <Moldura titulo="Confirmar e-mail">
      {resultado.estado === 'confirmando' && (
        <Typography>Confirmando seu e-mail...</Typography>
      )}
      {resultado.estado === 'ok' && (
        <Typography role="status">{resultado.texto}</Typography>
      )}
      {resultado.estado === 'erro' && (
        <>
          <Typography role="alert">{resultado.texto}</Typography>
          <Typography>{PEDIR_OUTRO}</Typography>
        </>
      )}
      <Bottom>
        <Button
          variant="contained"
          size="large"
          color="secondary"
          component={Link}
          to="/login"
        >
          Entrar
        </Button>
      </Bottom>
    </Moldura>
  );
}
