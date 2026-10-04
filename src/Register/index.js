import React, { useState } from 'react';
import { Link } from 'react-router-dom';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';

import {
  Container,
  CollumnLeft,
  CollumnRight,
  Brand,
  Content,
  Bottom,
} from './styles';
import Logo from '../assets/zerafilas.svg';
import { useSessao } from '../sessao/SessaoProvider';

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

export default function Register() {
  const classes = useStyles();
  const { cadastrar } = useSessao();
  const [campos, setCampos] = useState({
    email: '',
    cnpj: '',
    nome: '',
    senha: '',
    confirmacao: '',
  });
  const [erro, setErro] = useState('');
  const [enviando, setEnviando] = useState(false);

  const mudar = (campo) => (evento) =>
    setCampos({ ...campos, [campo]: evento.target.value });

  // O CNPJ pode ter letras (formato alfanumérico) e é sempre guardado em maiúsculas.
  const mudarCnpj = (evento) =>
    setCampos({ ...campos, cnpj: evento.target.value.toUpperCase() });

  async function enviar(evento) {
    evento.preventDefault();
    if (campos.senha !== campos.confirmacao) {
      setErro('As senhas não são iguais.');
      return;
    }
    setErro('');
    setEnviando(true);
    try {
      // quem cadastra já fica logada e a rota anônima a leva ao dashboard
      await cadastrar({
        email: campos.email,
        cnpj: campos.cnpj,
        nome: campos.nome,
        senha: campos.senha,
      });
    } catch (e) {
      setErro(e.status === 0 ? ERRO_REDE : e.message);
      setCampos({ ...campos, senha: '', confirmacao: '' });
      setEnviando(false);
    }
  }

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
            <h1>Cadastre-se</h1>
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
                  value={campos.email}
                  onChange={mudar('email')}
                />
                <TextField
                  id="outlined-cnpj-input"
                  label="CNPJ"
                  type="text"
                  autoComplete=""
                  variant="outlined"
                  value={campos.cnpj}
                  onChange={mudarCnpj}
                />
                <TextField
                  id="outlined-nome-input"
                  label="Nome do estabelecimento"
                  type="text"
                  autoComplete="organization"
                  variant="outlined"
                  value={campos.nome}
                  onChange={mudar('nome')}
                />
                <TextField
                  id="outlined-password-input"
                  label="Escolha uma Senha"
                  type="password"
                  variant="outlined"
                  value={campos.senha}
                  onChange={mudar('senha')}
                />
                <TextField
                  id="outlined-confirm-password-input"
                  label="Confirme a senha"
                  type="password"
                  variant="outlined"
                  value={campos.confirmacao}
                  onChange={mudar('confirmacao')}
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
                Cadastrar
              </Button>
            </form>
            <Bottom>
              <p>Ja possui um cadastro?</p>
              <Button
                variant="contained"
                size="large"
                color="secondary"
                component={Link}
                to="/login"
              >
                Faça o Login aqui
              </Button>
            </Bottom>
          </Content>
        </CollumnRight>
      </Container>
    </>
  );
}
