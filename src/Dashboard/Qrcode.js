import React, { useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Typography from '@material-ui/core/Typography';

import { QRCodeSVG } from 'qrcode.react';
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
import { buscarLoja } from '../api';
import { slug } from './estabelecimento';
import useLoja from './useLoja';

const useStyles = makeStyles(() => ({
  title: {
    fontSize: 20,
    textTransform: 'uppercase',
    color: '#ffffff',
    marginBottom: 32,
  },
  aviso: {
    color: '#ffffff',
    marginTop: 16,
  },
  resultado: {
    marginTop: 24,
    textAlign: 'center',
  },
  qr: {
    display: 'inline-block',
    padding: 8,
    background: '#ffffff',
  },
  link: {
    color: '#ffffff',
    marginTop: 8,
    wordBreak: 'break-all',
  },
}));

export default function QrCode() {
  const classes = useStyles();
  const { nome, inicial } = useLoja();
  const [url, setUrl] = useState('');
  const [erro, setErro] = useState(false);
  const [gerando, setGerando] = useState(false);

  const gerar = async () => {
    setGerando(true);
    setErro(false);
    try {
      const loja = await buscarLoja(slug);
      const base = loja.endereco_publico || window.location.origin;
      setUrl(`${base}/fila/${slug}`);
    } catch (e) {
      setUrl('');
      setErro(true);
    } finally {
      setGerando(false);
    }
  };

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
            <InAttendance onClick={gerando ? undefined : gerar}>
              <FaQrcode size="72" />
              <Typography variant="overline" display="block">
                Gerar QRCode
              </Typography>
            </InAttendance>
            {erro && (
              <Typography role="alert" className={classes.aviso}>
                Não foi possível gerar o QR code. Tente de novo.
              </Typography>
            )}
            {url && (
              <div className={classes.resultado}>
                <div className={classes.qr}>
                  <QRCodeSVG value={url} size={220} includeMargin />
                </div>
                <Typography className={classes.link}>{url}</Typography>
              </div>
            )}
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
