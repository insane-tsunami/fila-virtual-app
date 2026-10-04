import React from 'react';
import PropTypes from 'prop-types';

import Logo from '../assets/zerafilas.svg';
import { Container, CollumnLeft, CollumnRight, Brand, Content } from './styles';

// Layout das telas de acesso (coluna da marca + coluna do formulário), como o login.
export default function Moldura({ titulo, children }) {
  return (
    <Container>
      <CollumnLeft>
        <Brand>
          <img src={Logo} alt="ZeraFilas" />
          <p>Atendimento seguro e sem fila!</p>
        </Brand>
      </CollumnLeft>
      <CollumnRight>
        <Content>
          <h1>{titulo}</h1>
          <hr />
          {children}
        </Content>
      </CollumnRight>
    </Container>
  );
}

Moldura.propTypes = {
  titulo: PropTypes.string.isRequired,
  children: PropTypes.node.isRequired,
};
