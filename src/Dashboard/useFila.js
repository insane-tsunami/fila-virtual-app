import { useCallback, useEffect, useRef, useState } from 'react';

import { listarFila, finalizarEntrada } from '../api';

const INTERVALO_MS = 5000;

// Fila do dashboard: consulta a API ao montar e a cada 5 s (uma consulta por
// vez), mantém a última fila se a API falhar e finaliza o atendimento atual.
export default function useFila(slug, chave, aoRecusarChave) {
  const [clientes, setClientes] = useState([]);
  const [carregado, setCarregado] = useState(false);
  const [falha, setFalha] = useState(false);
  const [finalizando, setFinalizando] = useState(false);
  const [erroFinalizar, setErroFinalizar] = useState('');

  const vivo = useRef(true);
  const ultimaConsulta = useRef(0);
  const clientesRef = useRef([]);
  const finalizandoRef = useRef(false);
  const recusar = useRef(aoRecusarChave);
  recusar.current = aoRecusarChave;

  const buscar = useCallback(async () => {
    ultimaConsulta.current += 1;
    const minha = ultimaConsulta.current;
    try {
      const lista = await listarFila(slug, chave);
      // ignora respostas de uma página já fechada ou ultrapassadas por outra
      if (!vivo.current || minha !== ultimaConsulta.current) return;
      clientesRef.current = lista;
      setClientes(lista);
      setCarregado(true);
      setFalha(false);
    } catch (e) {
      if (!vivo.current || minha !== ultimaConsulta.current) return;
      if (e.status === 401) {
        recusar.current();
        return;
      }
      setFalha(true);
    }
  }, [slug, chave]);

  useEffect(() => {
    vivo.current = true;
    let temporizador;

    const ciclo = async () => {
      if (!vivo.current) return;
      await buscar();
      if (vivo.current) temporizador = setTimeout(ciclo, INTERVALO_MS);
    };
    ciclo();

    return () => {
      vivo.current = false;
      clearTimeout(temporizador);
    };
  }, [buscar]);

  const finalizar = useCallback(async () => {
    const atual = clientesRef.current[0];
    if (!atual || finalizandoRef.current) return;
    finalizandoRef.current = true;
    setFinalizando(true);
    setErroFinalizar('');
    try {
      await finalizarEntrada(slug, atual.codigo, chave);
      await buscar();
    } catch (e) {
      if (!vivo.current) return;
      if (e.status === 401) recusar.current();
      else if (e.status === 409) await buscar();
      else setErroFinalizar(e.message);
    } finally {
      finalizandoRef.current = false;
      if (vivo.current) setFinalizando(false);
    }
  }, [slug, chave, buscar]);

  return { clientes, carregado, falha, finalizando, erroFinalizar, finalizar };
}
