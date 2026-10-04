import React, { useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';

import { Wrapper, Container, Content, Painel } from './styles';

import BarraLateral from './BarraLateral';
import { trocarSenha } from '../api';
import { useSessao } from '../sessao/SessaoProvider';

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';

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
  title: {
    fontSize: 20,
    textTransform: 'uppercase',
    color: '#ffffff',
    marginBottom: 32,
  },
  aviso: {
    color: '#323c47',
    margin: theme.spacing(1),
  },
}));

const mascararCnpj = (cnpj) =>
  cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');

export default function Perfil() {
  const classes = useStyles();
  const { conta, token, expirar } = useSessao();
  const [atual, setAtual] = useState('');
  const [nova, setNova] = useState('');
  const [confirmacao, setConfirmacao] = useState('');
  const [resultado, setResultado] = useState(null);
  const [enviando, setEnviando] = useState(false);

  const limparSenhas = () => {
    setAtual('');
    setNova('');
    setConfirmacao('');
  };

  async function atualizar(evento) {
    evento.preventDefault();
    if (nova !== confirmacao) {
      setResultado({ ok: false, texto: 'As senhas não são iguais.' });
      return;
    }
    setResultado(null);
    setEnviando(true);
    try {
      await trocarSenha(token, atual, nova);
      limparSenhas();
      setResultado({ ok: true, texto: 'Senha alterada.' });
    } catch (e) {
      if (e.status === 401) {
        expirar();
        return;
      }
      if (e.status === 0) {
        setResultado({ ok: false, texto: ERRO_REDE });
      } else {
        limparSenhas();
        setResultado({ ok: false, texto: e.message });
      }
    }
    setEnviando(false);
  }

  return (
    <Wrapper>
      <BarraLateral />
      <Container>
        <h1 className={classes.title}>Perfil</h1>
        <Content>
          <Painel>
            <form
              className={classes.root}
              noValidate
              autoComplete="off"
              onSubmit={atualizar}
            >
              <div>
                <TextField
                  id="outlined-email-input"
                  label="E-mail"
                  type="email"
                  autoComplete="current-email"
                  variant="outlined"
                  value={conta.email}
                  helperText="Não é possivel alterar o e-mail"
                  InputProps={{
                    readOnly: true,
                  }}
                />
                <TextField
                  id="outlined-cnpj-input"
                  label="CNPJ"
                  type="text"
                  autoComplete=""
                  variant="outlined"
                  value={mascararCnpj(conta.cnpj)}
                  helperText="Não é possivel alterar o CNPJ"
                  InputProps={{
                    readOnly: true,
                  }}
                />
                <TextField
                  id="outlined-current-password-input"
                  label="Senha atual"
                  type="password"
                  autoComplete="current-password"
                  variant="outlined"
                  value={atual}
                  onChange={(e) => setAtual(e.target.value)}
                />
                <TextField
                  id="outlined-password-input"
                  label="Nova Senha"
                  type="password"
                  autoComplete="new-password"
                  variant="outlined"
                  value={nova}
                  onChange={(e) => setNova(e.target.value)}
                />
                <TextField
                  id="outlined-confirm-password-input"
                  label="Confirme a nova senha"
                  type="password"
                  autoComplete="new-password"
                  variant="outlined"
                  value={confirmacao}
                  onChange={(e) => setConfirmacao(e.target.value)}
                />
              </div>
              {resultado && (
                <Typography
                  role={resultado.ok ? 'status' : 'alert'}
                  className={classes.aviso}
                >
                  {resultado.texto}
                </Typography>
              )}
              <Button
                type="submit"
                variant="contained"
                size="large"
                color="secondary"
                className={classes.margin}
                disabled={enviando}
              >
                Atualizar
              </Button>
            </form>
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
