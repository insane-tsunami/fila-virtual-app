import React, { useState } from 'react';

import { makeStyles } from '@material-ui/core/styles';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import FormControl from '@material-ui/core/FormControl';
import FormLabel from '@material-ui/core/FormLabel';
import RadioGroup from '@material-ui/core/RadioGroup';
import Radio from '@material-ui/core/Radio';
import FormControlLabel from '@material-ui/core/FormControlLabel';

import AvatarDaLoja from './AvatarDaLoja';
import { CORES, ICONES, desenhoDoAvatar } from './avatar';
import { definirAvatar } from '../api';
import { useSessao } from '../sessao/SessaoProvider';

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';

const useStyles = makeStyles((theme) => ({
  secao: {
    margin: theme.spacing(1),
    marginTop: theme.spacing(4),
    color: '#323c47',
  },
  titulo: {
    fontSize: 16,
    fontWeight: 600,
    marginBottom: theme.spacing(2),
  },
  grupo: {
    margin: theme.spacing(2, 0),
  },
  opcao: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
  },
  amostra: {
    display: 'inline-block',
    width: 16,
    height: 16,
    borderRadius: '50%',
  },
  botao: {
    marginRight: theme.spacing(1),
  },
}));

// Seção "Avatar da loja" do Perfil: a dona escolhe uma cor e um ícone das listas fixas (sem envio
// de imagem). Salvar atualiza a loja da sessão, e a barra lateral muda na hora.
export default function EscolherAvatar() {
  const classes = useStyles();
  const { loja, token, expirar, atualizarLoja } = useSessao();
  const salvo = desenhoDoAvatar(loja.avatar);
  const [cor, setCor] = useState(salvo ? salvo.cor.nome : '');
  const [icone, setIcone] = useState(salvo ? salvo.icone.nome : '');
  const [resultado, setResultado] = useState(null);
  const [enviando, setEnviando] = useState(false);

  async function enviar(novaCor, novoIcone, sucesso) {
    setResultado(null);
    setEnviando(true);
    try {
      const dados = await definirAvatar(loja.slug, novaCor, novoIcone, token);
      atualizarLoja(dados);
      if (!dados.avatar) {
        setCor('');
        setIcone('');
      }
      setResultado({ ok: true, texto: sucesso });
    } catch (e) {
      if (e.status === 401) {
        expirar();
        return;
      }
      setResultado({
        ok: false,
        texto: e.status === 0 ? ERRO_REDE : e.message,
      });
    }
    setEnviando(false);
  }

  function salvar(evento) {
    evento.preventDefault();
    // a API recusa a escolha incompleta; nem chega a pedir (o botão também fica desativado)
    if (!cor || !icone) return;
    enviar(cor, icone, 'Avatar salvo.');
  }

  return (
    <form
      className={classes.secao}
      aria-label="Avatar da loja"
      noValidate
      onSubmit={salvar}
    >
      <Typography component="h2" className={classes.titulo}>
        Avatar da loja
      </Typography>
      <AvatarDaLoja
        nome={loja.nome}
        avatar={cor && icone ? { cor, icone } : null}
      />
      <FormControl component="fieldset" className={classes.grupo}>
        <FormLabel component="legend">Cor</FormLabel>
        <RadioGroup
          row
          name="avatar-cor"
          value={cor}
          onChange={(e) => setCor(e.target.value)}
        >
          {CORES.map((c) => (
            <FormControlLabel
              key={c.nome}
              value={c.nome}
              control={<Radio />}
              label={
                <span className={classes.opcao}>
                  <span
                    className={classes.amostra}
                    style={{ backgroundColor: c.hex }}
                    aria-hidden="true"
                  />
                  {c.rotulo}
                </span>
              }
            />
          ))}
        </RadioGroup>
      </FormControl>
      <FormControl component="fieldset" className={classes.grupo}>
        <FormLabel component="legend">Ícone</FormLabel>
        <RadioGroup
          row
          name="avatar-icone"
          value={icone}
          onChange={(e) => setIcone(e.target.value)}
        >
          {ICONES.map(({ nome, rotulo, Icone }) => (
            <FormControlLabel
              key={nome}
              value={nome}
              control={<Radio />}
              label={
                <span className={classes.opcao}>
                  <Icone fontSize="small" aria-hidden="true" />
                  {rotulo}
                </span>
              }
            />
          ))}
        </RadioGroup>
      </FormControl>
      {resultado && (
        <Typography role={resultado.ok ? 'status' : 'alert'}>
          {resultado.texto}
        </Typography>
      )}
      <div>
        <Button
          type="submit"
          variant="contained"
          color="secondary"
          className={classes.botao}
          disabled={enviando || !cor || !icone}
        >
          Salvar avatar
        </Button>
        {loja.avatar && (
          <Button
            variant="outlined"
            color="secondary"
            disabled={enviando}
            onClick={() => enviar(null, null, 'Avatar removido.')}
          >
            Usar a inicial
          </Button>
        )}
      </div>
    </form>
  );
}
