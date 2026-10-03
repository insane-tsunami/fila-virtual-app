import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';

import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';
import Typography from '@material-ui/core/Typography';

import { buscarLoja, entrarNaFila, consultarEntrada } from '../api';
import { guardarCodigo, lerCodigo, apagarCodigo } from './armazenamento';
import { Pagina, Cartao } from './styles';

const INTERVALO_MS = 5000;
const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';

export default function Cliente() {
  const { slug } = useParams();
  const [loja, setLoja] = useState(null);
  const [lojaInexistente, setLojaInexistente] = useState(false);
  const [carregando, setCarregando] = useState(true);
  const [erroCarga, setErroCarga] = useState('');
  const [telefone, setTelefone] = useState('');
  const [erro, setErro] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [entrada, setEntrada] = useState(null);
  const [falhaConsulta, setFalhaConsulta] = useState(false);
  const ativo = useRef(true);

  useEffect(() => {
    ativo.current = true;
    return () => {
      ativo.current = false;
    };
  }, []);

  const finalizada = entrada && entrada.status === 'finalizado';
  const codigo = entrada ? entrada.codigo : null;

  // Carrega a loja e retoma a entrada guardada, se houver.
  useEffect(() => {
    let cancelado = false;
    setCarregando(true);
    setLoja(null);
    setLojaInexistente(false);
    setErroCarga('');
    setEntrada(null);

    (async () => {
      try {
        const dados = await buscarLoja(slug);
        if (cancelado) return;
        setLoja(dados);
        const guardado = lerCodigo(slug);
        if (guardado) {
          try {
            const existente = await consultarEntrada(slug, guardado);
            if (!cancelado) setEntrada({ ...existente, codigo: guardado });
          } catch (e) {
            if (e.status === 404) apagarCodigo(slug);
            // outras falhas: mostra o formulário; o código guardado fica
          }
        }
      } catch (e) {
        if (cancelado) return;
        if (e.status === 404) setLojaInexistente(true);
        else setErroCarga(e.message);
      } finally {
        if (!cancelado) setCarregando(false);
      }
    })();

    return () => {
      cancelado = true;
    };
  }, [slug]);

  // Acompanhamento: uma consulta por vez, a cada 5 s, enquanto ativa.
  useEffect(() => {
    if (!codigo || finalizada) return undefined;
    let cancelado = false;
    let temporizador;

    const consultar = async () => {
      if (cancelado) return;
      try {
        const dados = await consultarEntrada(slug, codigo);
        if (cancelado) return;
        setFalhaConsulta(false);
        setEntrada({ ...dados, codigo });
      } catch (e) {
        if (cancelado) return;
        if (e.status === 404) {
          apagarCodigo(slug);
          setEntrada(null);
          return;
        }
        setFalhaConsulta(true);
      }
      if (!cancelado) temporizador = setTimeout(consultar, INTERVALO_MS);
    };

    temporizador = setTimeout(consultar, INTERVALO_MS);
    return () => {
      cancelado = true;
      clearTimeout(temporizador);
    };
  }, [slug, codigo, finalizada]);

  const enviar = useCallback(
    async (evento) => {
      evento.preventDefault();
      setErro('');
      setEnviando(true);
      try {
        const dados = await entrarNaFila(slug, telefone);
        if (!ativo.current) return;
        guardarCodigo(slug, dados.codigo);
        setFalhaConsulta(false);
        setEntrada(dados);
      } catch (e) {
        if (!ativo.current) return;
        setErro(e.status === 0 ? ERRO_REDE : e.message);
      } finally {
        if (ativo.current) setEnviando(false);
      }
    },
    [slug, telefone]
  );

  const entrarDeNovo = () => {
    apagarCodigo(slug);
    setEntrada(null);
    setFalhaConsulta(false);
    setTelefone('');
    setErro('');
  };

  let conteudo;
  if (carregando) {
    conteudo = <Typography>Carregando...</Typography>;
  } else if (lojaInexistente) {
    conteudo = <Typography>Loja não encontrada.</Typography>;
  } else if (erroCarga) {
    conteudo = <Typography role="alert">{erroCarga}</Typography>;
  } else if (entrada && finalizada) {
    conteudo = (
      <>
        <Typography variant="h6">Atendimento finalizado</Typography>
        <Button variant="contained" color="primary" onClick={entrarDeNovo}>
          Entrar na fila de novo
        </Button>
      </>
    );
  } else if (entrada) {
    conteudo = (
      <>
        <Typography variant="h6">
          {entrada.status === 'em_atendimento' ? 'É a sua vez!' : 'Aguardando'}
        </Typography>
        <Typography>{`Sua posição: ${entrada.posicao}`}</Typography>
        {falhaConsulta && (
          <Typography role="alert">
            Não foi possível atualizar. Tentando de novo...
          </Typography>
        )}
      </>
    );
  } else {
    conteudo = (
      <form onSubmit={enviar}>
        <TextField
          id="telefone"
          label="Seu telefone (com DDD)"
          type="tel"
          value={telefone}
          onChange={(e) => setTelefone(e.target.value)}
          fullWidth
          margin="normal"
        />
        {erro && <Typography role="alert">{erro}</Typography>}
        <Button
          type="submit"
          variant="contained"
          color="primary"
          disabled={enviando}
        >
          Entrar na fila
        </Button>
      </form>
    );
  }

  return (
    <Pagina>
      <Cartao>
        {loja && <h1>{loja.nome}</h1>}
        {conteudo}
      </Cartao>
    </Pagina>
  );
}
