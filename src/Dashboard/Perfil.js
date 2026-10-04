import React, { useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';

import { Wrapper, Container, Content, Painel } from './styles';

import BarraLateral from './BarraLateral';
import AvisoEmail from './AvisoEmail';
import { trocarEmail, trocarSenha } from '../api';
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

// XX.XXX.XXX/XXXX-DD (as 12 primeiras posições aceitam letras; um valor que não
// casa com o formato é mostrado como veio).
const mascararCnpj = (cnpj) =>
  cnpj.replace(
    /^([0-9A-Z]{2})([0-9A-Z]{3})([0-9A-Z]{3})([0-9A-Z]{4})([0-9]{2})$/,
    '$1.$2.$3/$4-$5'
  );

export default function Perfil() {
  const classes = useStyles();
  const { conta, token, expirar, definirConta } = useSessao();
  // O estado vem da API (sempre um booleano); sem ele a conta é tratada como confirmada.
  const emailConfirmado = conta.email_confirmado !== false;
  const [novoEmail, setNovoEmail] = useState('');
  const [senhaDoEmail, setSenhaDoEmail] = useState('');
  const [resultadoEmail, setResultadoEmail] = useState(null);
  const [enviandoEmail, setEnviandoEmail] = useState(false);
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

  async function trocarOEmail(evento) {
    evento.preventDefault();
    setResultadoEmail(null);
    setEnviandoEmail(true);
    try {
      const dados = await trocarEmail(token, novoEmail, senhaDoEmail);
      definirConta(dados.conta);
      setNovoEmail('');
      setSenhaDoEmail('');
      setResultadoEmail({ ok: true, texto: dados.mensagem });
    } catch (e) {
      if (e.status === 401) {
        expirar();
        return;
      }
      if (e.status === 0) {
        setResultadoEmail({ ok: false, texto: ERRO_REDE });
      } else {
        setSenhaDoEmail('');
        setResultadoEmail({ ok: false, texto: e.message });
      }
    }
    setEnviandoEmail(false);
  }

  return (
    <Wrapper>
      <BarraLateral />
      <Container>
        <AvisoEmail />
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
                  helperText={
                    emailConfirmado ? 'Não é possivel alterar o e-mail' : ''
                  }
                  InputProps={{
                    readOnly: true,
                  }}
                />
                <Typography className={classes.aviso}>
                  {emailConfirmado
                    ? 'E-mail confirmado'
                    : 'E-mail ainda não confirmado'}
                </Typography>
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
            {!emailConfirmado && (
              <form
                className={classes.root}
                noValidate
                autoComplete="off"
                aria-label="Trocar e-mail"
                onSubmit={trocarOEmail}
              >
                <Typography className={classes.aviso}>
                  Digitou o e-mail errado? Corrija abaixo enquanto ele não for
                  confirmado.
                </Typography>
                <div>
                  <TextField
                    id="outlined-new-email-input"
                    label="Novo e-mail"
                    type="email"
                    autoComplete="off"
                    variant="outlined"
                    value={novoEmail}
                    onChange={(e) => setNovoEmail(e.target.value)}
                  />
                  <TextField
                    id="outlined-email-password-input"
                    label="Senha atual"
                    type="password"
                    autoComplete="current-password"
                    variant="outlined"
                    value={senhaDoEmail}
                    onChange={(e) => setSenhaDoEmail(e.target.value)}
                  />
                </div>
                {resultadoEmail && (
                  <Typography
                    role={resultadoEmail.ok ? 'status' : 'alert'}
                    className={classes.aviso}
                  >
                    {resultadoEmail.texto}
                  </Typography>
                )}
                <Button
                  type="submit"
                  variant="contained"
                  size="large"
                  color="secondary"
                  className={classes.margin}
                  disabled={enviandoEmail}
                >
                  Trocar e-mail
                </Button>
              </form>
            )}
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
