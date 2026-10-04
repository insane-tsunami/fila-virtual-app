import { useEffect, useState } from 'react';

import { buscarLoja } from '../api';
import {
  nome as nomeReserva,
  inicial as inicialReserva,
  slug,
} from './estabelecimento';

// Dados da loja para a barra lateral: vêm da API e, enquanto ela não responde
// (ou se falhar), valem os valores fixos de reserva.
export default function useLoja() {
  const [loja, setLoja] = useState(null);

  useEffect(() => {
    let cancelado = false;
    buscarLoja(slug)
      .then((dados) => {
        if (!cancelado) setLoja(dados);
      })
      .catch(() => {});
    return () => {
      cancelado = true;
    };
  }, []);

  const nome = loja && loja.nome ? loja.nome : nomeReserva;
  const inicial =
    loja && loja.nome ? loja.nome.charAt(0).toUpperCase() : inicialReserva;
  return { nome, inicial, loja };
}
