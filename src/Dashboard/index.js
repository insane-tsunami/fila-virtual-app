import React, { useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Typography from '@material-ui/core/Typography';
import Avatar from '@material-ui/core/Avatar';

import {
  Wrapper,
  BarNavigation,
  BarAvatar,
  Container,
  Content,
  Painel,
  Client,
  Number,
  InAttendance,
} from './styles';

import Nav from './Nav';
import useLoja from './useLoja';

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

const initialClients = [
  {
    id: 10031,
    position: 31,
    number: '*****5314',
  },
  {
    id: 10032,
    position: 32,
    number: '*****5314',
  },
  {
    id: 10033,
    position: 33,
    number: '*****1245',
  },
  {
    id: 10034,
    position: 34,
    number: '*****1155',
  },
  {
    id: 10035,
    position: 35,
    number: '*****2414',
  },
  {
    id: 10036,
    position: 36,
    number: '*****1679',
  },
  {
    id: 10037,
    position: 37,
    number: '*****1247',
  },
  {
    id: 10038,
    position: 38,
    number: '*****3347',
  },
  {
    id: 10039,
    position: 39,
    number: '*****9854',
  },
  {
    id: 10040,
    position: 40,
    number: '*****9854',
  },
  {
    id: 10041,
    position: 41,
    number: '*****9854',
  },
  {
    id: 10042,
    position: 42,
    number: '*****9854',
  },
  {
    id: 10043,
    position: 43,
    number: '*****9854',
  },
];

export default function Dashboard() {
  const classes = useStyles();
  const { nome, inicial } = useLoja();
  const [clients, setClients] = useState(initialClients);

  function handleEndService() {
    setClients((current) => current.slice(1));
  }

  function statusOf(index) {
    if (index === 0) return 'current';
    if (index === 1) return 'next';
    return 'wait';
  }

  return (
    <Wrapper>
      <BarNavigation>
        <BarAvatar>{inicial}</BarAvatar>
        <p>{nome}</p>
        <Nav />
      </BarNavigation>
      <Container>
        <h1 className={classes.title}>Dashboard</h1>
        <Content>
          <Painel size="300px">
            <h3>Fila</h3>
            {clients.length > 0 &&
              clients.map((c, index) => (
                <Client key={c.id} status={statusOf(index)}>
                  <Avatar
                    className={`${
                      statusOf(index) === 'next' ? classes.secondary : ''
                    }`}
                  >
                    {c.position}
                  </Avatar>
                  <Number status={statusOf(index)}>{c.number}</Number>
                </Client>
              ))}
            {clients.length === 1 && <p>Fila vazia</p>}
          </Painel>
          <Painel>
            <h3>Em Atendimento</h3>
            {clients.length > 0 && (
              <InAttendance onClick={() => handleEndService()}>
                <Avatar className={classes.current}>
                  {clients[0].position}
                </Avatar>
                <Typography variant="overline" display="block">
                  Finalizar Atendimento
                </Typography>
              </InAttendance>
            )}
            {clients.length === 0 && (
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
