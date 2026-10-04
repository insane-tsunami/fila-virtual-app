import React, { createContext, useCallback, useContext, useState } from 'react';
import PropTypes from 'prop-types';

import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';
import Typography from '@material-ui/core/Typography';

import { listarFila } from '../api';
import { Pagina, Cartao } from '../Cliente/styles';
import { lerChave, guardarChave, apagarChave } from './chave';
import { slug } from './estabelecimento';

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';

const ChaveContext = createContext({ chave: null, recusar: () => {} });

export const ChaveProvider = ChaveContext.Provider;

// Dá às páginas protegidas a chave guardada e um `recusar()` para quando a API
// responder 401: apaga a chave e devolve o dono à tela de chave.
export function useChave() {
  return useContext(ChaveContext);
}

export default function ChaveGate({ children }) {
  const [chave, setChave] = useState(() => lerChave());
  const [campo, setCampo] = useState('');
  const [aviso, setAviso] = useState('');
  const [validando, setValidando] = useState(false);

  const recusar = useCallback(() => {
    apagarChave();
    setCampo('');
    setAviso('Chave inválida.');
    setChave(null);
  }, []);

  async function entrar(evento) {
    evento.preventDefault();
    const candidata = campo.trim();
    if (!candidata) {
      setAviso('Chave inválida.');
      return;
    }
    setAviso('');
    setValidando(true);
    try {
      await listarFila(slug, candidata);
      guardarChave(candidata);
      setChave(candidata);
    } catch (e) {
      setAviso(e.status === 401 ? 'Chave inválida.' : ERRO_REDE);
    } finally {
      setValidando(false);
    }
  }

  if (chave) {
    return <ChaveProvider value={{ chave, recusar }}>{children}</ChaveProvider>;
  }

  return (
    <Pagina>
      <Cartao>
        <h1>Acesso ao dashboard</h1>
        <form onSubmit={entrar}>
          <TextField
            id="chave-de-acesso"
            label="Chave de acesso"
            type="password"
            autoComplete="off"
            value={campo}
            onChange={(e) => setCampo(e.target.value)}
            fullWidth
            margin="normal"
          />
          {aviso && <Typography role="alert">{aviso}</Typography>}
          <Button
            type="submit"
            variant="contained"
            color="primary"
            disabled={validando}
          >
            Entrar
          </Button>
        </form>
      </Cartao>
    </Pagina>
  );
}

ChaveGate.propTypes = {
  children: PropTypes.node.isRequired,
};
