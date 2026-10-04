import React, { useEffect, useState } from 'react';
import { Link, useHistory, useLocation } from 'react-router-dom';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import Moldura from '../Login/Moldura';
import { Bottom } from '../Login/styles';
import { redefinirSenha } from '../api';

const useStyles = makeStyles((theme) => ({
  root: {
    '& .MuiTextField-root': {
      margin: theme.spacing(1),
      width: '50ch',
    },
  },
  margin: {
    margin: theme.spacing(1),
  },
}));

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const LINK_INVALIDO = 'Link inválido ou expirado. Peça um novo.';
export const AVISO_SENHA_ALTERADA = 'Senha alterada. Entre com a nova senha.';

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

export default function RedefinirSenha() {
  const classes = useStyles();
  const history = useHistory();
  const location = useLocation();
  const [token] = useState(() => lerToken(location.hash));
  const [nova, setNova] = useState('');
  const [confirmacao, setConfirmacao] = useState('');
  const [erro, setErro] = useState('');
  const [enviando, setEnviando] = useState(false);

  // Tira o token do endereço assim que ele foi lido.
  useEffect(() => {
    if (location.hash) history.replace(location.pathname);
  }, [history, location.hash, location.pathname]);

  async function enviar(evento) {
    evento.preventDefault();
    if (nova !== confirmacao) {
      setErro('As senhas não são iguais.');
      return;
    }
    setErro('');
    setEnviando(true);
    try {
      await redefinirSenha(token, nova);
      history.replace('/login', { aviso: AVISO_SENHA_ALTERADA });
    } catch (e) {
      if (e.status === 0) {
        setErro(ERRO_REDE);
      } else {
        setNova('');
        setConfirmacao('');
        setErro(e.message);
      }
      setEnviando(false);
    }
  }

  const pedirOutro = !token || erro === LINK_INVALIDO;

  return (
    <Moldura titulo="Nova senha">
      {token ? (
        <form
          className={classes.root}
          noValidate
          autoComplete="off"
          onSubmit={enviar}
        >
          <div>
            <TextField
              id="redefinir-nova-input"
              label="Nova Senha"
              type="password"
              variant="outlined"
              value={nova}
              onChange={(e) => setNova(e.target.value)}
            />
            <TextField
              id="redefinir-confirmacao-input"
              label="Confirmar Nova Senha"
              type="password"
              variant="outlined"
              value={confirmacao}
              onChange={(e) => setConfirmacao(e.target.value)}
            />
          </div>
          {erro && <Typography role="alert">{erro}</Typography>}
          <Button
            type="submit"
            variant="contained"
            size="large"
            color="secondary"
            className={classes.margin}
            disabled={enviando}
          >
            Salvar nova senha
          </Button>
        </form>
      ) : (
        <Typography role="alert">{LINK_INVALIDO}</Typography>
      )}
      <Bottom>
        {pedirOutro && (
          <Button
            variant="contained"
            size="large"
            color="secondary"
            component={Link}
            to="/esqueci-senha"
          >
            Pedir um novo link
          </Button>
        )}
      </Bottom>
    </Moldura>
  );
}
