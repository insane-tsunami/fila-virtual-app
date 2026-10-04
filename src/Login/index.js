import React, { useState } from 'react';
import { Link } from 'react-router-dom';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import Logo from '../assets/zerafilas.svg';
import { useSessao } from '../sessao/SessaoProvider';
import {
  Container,
  CollumnLeft,
  CollumnRight,
  Brand,
  Content,
  Bottom,
} from './styles';

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

export default function Login() {
  const classes = useStyles();
  const { entrar, aviso, falhaDeRede } = useSessao();
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState('');
  const [enviando, setEnviando] = useState(false);

  async function enviar(evento) {
    evento.preventDefault();
    if (!email.trim() || !senha) {
      setErro('Informe o e-mail e a senha.');
      return;
    }
    setErro('');
    setEnviando(true);
    try {
      // quem entra é levado ao dashboard (ou à página pedida) pela rota anônima
      await entrar(email, senha);
    } catch (e) {
      setErro(e.status === 0 ? ERRO_REDE : e.message);
      setSenha('');
      setEnviando(false);
    }
  }

  const mensagem = erro || aviso || (falhaDeRede ? ERRO_REDE : '');

  return (
    <>
      <Container>
        <CollumnLeft>
          <Brand>
            <img src={Logo} alt="ZeraFilas" />
            <p>Atendimento seguro e sem fila!</p>
          </Brand>
        </CollumnLeft>
        <CollumnRight>
          <Content>
            <h1>Login</h1>
            <hr />
            <form
              className={classes.root}
              noValidate
              autoComplete="off"
              onSubmit={enviar}
            >
              <div>
                <TextField
                  id="outlined-email-input"
                  label="E-mail"
                  type="email"
                  autoComplete="current-email"
                  variant="outlined"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />

                <TextField
                  id="outlined-password-input"
                  label="Senha"
                  type="password"
                  variant="outlined"
                  value={senha}
                  onChange={(e) => setSenha(e.target.value)}
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
                Entrar
              </Button>
            </form>
            <Bottom>
              <p>Ainda não tem cadastro?</p>
              <Button
                variant="contained"
                size="large"
                color="secondary"
                component={Link}
                to="/cadastro"
              >
                Cadastre-se aqui
              </Button>
            </Bottom>
          </Content>
        </CollumnRight>
      </Container>
    </>
  );
}
