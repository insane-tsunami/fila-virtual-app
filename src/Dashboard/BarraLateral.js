import React from 'react';

import { BarNavigation, BarAvatar } from './styles';
import Nav from './Nav';
import { useSessao } from '../sessao/SessaoProvider';

// Barra lateral do dashboard: inicial e nome da loja da conta logada e o menu.
export default function BarraLateral() {
  const { loja } = useSessao();
  const nome = loja ? loja.nome : '';

  return (
    <BarNavigation>
      <BarAvatar>{nome.charAt(0).toUpperCase()}</BarAvatar>
      <p>{nome}</p>
      <Nav />
    </BarNavigation>
  );
}
