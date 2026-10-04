import React, { useRef, useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Typography from '@material-ui/core/Typography';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';

import { QRCodeSVG } from 'qrcode.react';
import { FaQrcode } from 'react-icons/fa';
import { Wrapper, Container, Content, Painel, InAttendance } from './styles';

import AvisoEmail from './AvisoEmail';
import BarraLateral from './BarraLateral';
import { buscarLoja, definirEndereco } from '../api';
import { useSessao } from '../sessao/SessaoProvider';

const useStyles = makeStyles(() => ({
  title: {
    fontSize: 20,
    textTransform: 'uppercase',
    color: '#ffffff',
    marginBottom: 32,
  },
  aviso: {
    color: '#323c47',
    marginTop: 16,
    marginBottom: 16,
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
    color: '#323c47',
    marginTop: 8,
    wordBreak: 'break-all',
  },
}));

export default function QrCode() {
  const classes = useStyles();
  const { loja, token, expirar, atualizarLoja } = useSessao();
  const { slug } = loja;
  const [url, setUrl] = useState('');
  const [erro, setErro] = useState(false);
  const [gerando, setGerando] = useState(false);
  const [endereco, setEndereco] = useState(loja.endereco_publico || '');
  const [salvando, setSalvando] = useState(false);
  const [resultado, setResultado] = useState(null);
  // muda a cada endereço salvo: descarta um QR que ainda estava sendo gerado
  const versao = useRef(0);

  const gerar = async () => {
    const minha = versao.current;
    setGerando(true);
    setErro(false);
    try {
      const dados = await buscarLoja(slug);
      if (minha !== versao.current) return;
      const base = dados.endereco_publico || window.location.origin;
      setUrl(`${base}/fila/${slug}`);
    } catch (e) {
      if (minha !== versao.current) return;
      setUrl('');
      setErro(true);
    } finally {
      setGerando(false);
    }
  };

  const salvar = async (evento) => {
    evento.preventDefault();
    setSalvando(true);
    setResultado(null);
    try {
      const dados = await definirEndereco(slug, endereco.trim(), token);
      versao.current += 1;
      setUrl('');
      setErro(false);
      setEndereco(dados.endereco_publico || '');
      atualizarLoja(dados);
      setResultado({ ok: true, texto: 'Endereço salvo.' });
    } catch (e) {
      if (e.status === 401) expirar();
      else setResultado({ ok: false, texto: e.message });
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Wrapper>
      <BarraLateral />
      <Container>
        <AvisoEmail />
        <h1 className={classes.title}>Gerar QRCode</h1>
        <Content>
          <Painel size="55%">
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
          <Painel size="40%">
            <form onSubmit={salvar}>
              <TextField
                id="endereco-publico"
                label="Endereço público da loja"
                placeholder="https://loja.exemplo.com"
                value={endereco}
                onChange={(e) => setEndereco(e.target.value)}
                helperText="Só o endereço do site, sem caminho. Vazio usa o endereço desta página."
                fullWidth
                margin="normal"
              />
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
                color="secondary"
                disabled={salvando}
              >
                Salvar endereço
              </Button>
            </form>
          </Painel>
        </Content>
      </Container>
    </Wrapper>
  );
}
