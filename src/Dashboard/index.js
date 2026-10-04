import React from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Typography from '@material-ui/core/Typography';
import Avatar from '@material-ui/core/Avatar';

import {
  Wrapper,
  Container,
  Content,
  Painel,
  Client,
  Number,
  InAttendance,
} from './styles';

import AvisoEmail from './AvisoEmail';
import BarraLateral from './BarraLateral';
import useFila from './useFila';
import { useSessao } from '../sessao/SessaoProvider';

const useStyles = makeStyles((theme) => ({
  root: {
    display: 'flex',
    '& > *': {
      margin: theme.spacing(1),
    },
  },
  title: {
    fontSize: 20,
    textTransform: 'uppercase',
    color: '#ffffff',
    marginBottom: 32,
  },
  secondary: {
    color: '#E10050',
    backgroundColor: '#ffffff',
  },
  current: {
    backgroundColor: '#E10050',
    color: '#ffffff',
    width: theme.spacing(9),
    height: theme.spacing(9),
  },
}));

export default function Dashboard() {
  const classes = useStyles();
  const { loja, token, expirar } = useSessao();
  const {
    clientes,
    carregado,
    falha,
    finalizando,
    erroFinalizar,
    finalizar,
  } = useFila(loja.slug, token, expirar);

  function statusOf(index) {
    if (index === 0) return 'current';
    if (index === 1) return 'next';
    return 'wait';
  }

  return (
    <Wrapper>
      <BarraLateral />
      <Container>
        <AvisoEmail />
        <h1 className={classes.title}>Dashboard</h1>
        <Content>
          <Painel size="300px">
            <h3>Fila</h3>
            {falha && (
              <p role="alert">
                Não foi possível atualizar a fila. Tentando de novo...
              </p>
            )}
            {clientes.map((c, index) => (
              <Client key={c.codigo} status={statusOf(index)}>
                <Avatar
                  className={`${
                    statusOf(index) === 'next' ? classes.secondary : ''
                  }`}
                >
                  {c.posicao}
                </Avatar>
                <Number status={statusOf(index)}>{c.telefone}</Number>
              </Client>
            ))}
            {clientes.length === 1 && <p>Fila vazia</p>}
          </Painel>
          <Painel>
            <h3>Em Atendimento</h3>
            {clientes.length > 0 && (
              <InAttendance onClick={() => finalizar()} aria-busy={finalizando}>
                <Avatar className={classes.current}>
                  {clientes[0].posicao}
                </Avatar>
                <Typography variant="overline" display="block">
                  Finalizar Atendimento
                </Typography>
              </InAttendance>
            )}
            {erroFinalizar && <p role="alert">{erroFinalizar}</p>}
            {carregado && clientes.length === 0 && (
              <p>
                Atendeu todos os clientes! <br />
                Que tal lavar as mãos e tomar um café?
              </p>
            )}
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
