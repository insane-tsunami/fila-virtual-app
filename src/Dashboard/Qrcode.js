import React from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Typography from '@material-ui/core/Typography';

import { FaQrcode } from 'react-icons/fa';
import {
  Wrapper,
  BarNavigation,
  BarAvatar,
  Container,
  Content,
  Painel,
  InAttendance,
} from './styles';

import Nav from './Nav';
import { nome, inicial } from './estabelecimento';

const useStyles = makeStyles(() => ({
  title: {
    fontSize: 20,
    textTransform: 'uppercase',
    color: '#ffffff',
    marginBottom: 32,
  },
}));

export default function QrCode() {
  const classes = useStyles();
  return (
    <Wrapper>
      <BarNavigation>
        <BarAvatar>{inicial}</BarAvatar>
        <p>{nome}</p>
        <Nav />
      </BarNavigation>
      <Container>
        <h1 className={classes.title}>Gerar QRCode</h1>
        <Content>
          <Painel>
            <InAttendance onClick={() => {}}>
              <FaQrcode size="72" />
              <Typography variant="overline" display="block">
                Gerar QRCode
              </Typography>
            </InAttendance>
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
