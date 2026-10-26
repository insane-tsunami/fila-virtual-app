import React from 'react';

import { BarNavigation } from './styles';
import AvatarDaLoja from './AvatarDaLoja';
import Nav from './Nav';
import { useSessao } from '../sessao/SessaoProvider';

// Barra lateral do dashboard: avatar (ou inicial) e nome da loja da conta logada e o menu.
export default function BarraLateral() {
  const { loja } = useSessao();
  const nome = loja ? loja.nome : '';

  return (
    <BarNavigation as="aside" aria-label="Barra lateral">
      <AvatarDaLoja nome={nome} avatar={loja ? loja.avatar : null} />
      <p>{nome}</p>
      <Nav />
    </BarNavigation>
  );
}
