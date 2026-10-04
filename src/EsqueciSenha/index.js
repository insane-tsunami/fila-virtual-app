import React, { useState } from 'react';
import { Link } from 'react-router-dom';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import Moldura from '../Login/Moldura';
import { Bottom } from '../Login/styles';
import { pedirRedefinicao } from '../api';

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

export default function EsqueciSenha() {
  const classes = useStyles();
  const [email, setEmail] = useState('');
  const [erro, setErro] = useState('');
  const [resultado, setResultado] = useState('');
  const [enviando, setEnviando] = useState(false);

  async function enviar(evento) {
    evento.preventDefault();
    if (!email.trim()) {
      setErro('Informe o e-mail.');
      return;
    }
    setErro('');
    setResultado('');
    setEnviando(true);
    try {
      const dados = await pedirRedefinicao(email.trim());
      // a mesma mensagem com ou sem conta: a tela não diz se o e-mail existe
      setResultado(dados.mensagem);
    } catch (e) {
      setErro(e.status === 0 ? ERRO_REDE : e.message);
    }
    setEnviando(false);
  }

  const mensagem = erro || resultado;

  return (
    <Moldura titulo="Esqueci a senha">
      <form
        className={classes.root}
        noValidate
        autoComplete="off"
        onSubmit={enviar}
      >
        <Typography>
          Informe o e-mail da sua conta e enviaremos um link para criar uma nova
          senha.
        </Typography>
        <div>
          <TextField
            id="esqueci-email-input"
            label="E-mail"
            type="email"
            variant="outlined"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>
        {mensagem && <Typography role="alert">{mensagem}</Typography>}
        <Button
          type="submit"
          variant="contained"
          size="large"
          color="secondary"
          className={classes.margin}
          disabled={enviando}
        >
          Enviar link
        </Button>
      </form>
      <Bottom>
        <Button
          variant="contained"
          size="large"
          color="secondary"
          component={Link}
          to="/login"
        >
          Voltar ao login
        </Button>
      </Bottom>
    </Moldura>
  );
}
