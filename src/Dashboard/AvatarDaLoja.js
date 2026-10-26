import React from 'react';
import PropTypes from 'prop-types';

import { BarAvatar } from './styles';
import { desenhoDoAvatar } from './avatar';

// Avatar da loja na barra lateral e na pré-visualização do Perfil: o ícone escolhido sobre a cor
// escolhida ou, sem avatar (ou com um valor desconhecido), a inicial do nome.
export default function AvatarDaLoja({ nome, avatar }) {
  const desenho = desenhoDoAvatar(avatar);
  if (!desenho) return <BarAvatar>{nome.charAt(0).toUpperCase()}</BarAvatar>;

  const { Icone } = desenho.icone;
  return (
    <BarAvatar style={{ backgroundColor: desenho.cor.hex, color: '#ffffff' }}>
      <Icone
        titleAccess={`Avatar: ${desenho.icone.rotulo} sobre ${desenho.cor.rotulo}`}
      />
    </BarAvatar>
  );
}

AvatarDaLoja.propTypes = {
  nome: PropTypes.string,
  avatar: PropTypes.shape({
    cor: PropTypes.string,
    icone: PropTypes.string,
  }),
};

AvatarDaLoja.defaultProps = {
  nome: '',
  avatar: null,
};
