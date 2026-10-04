/* eslint-env jest */
import React from 'react';
import PropTypes from 'prop-types';

import { SessaoContext } from './SessaoProvider';

export const CONTA_DE_TESTE = {
  email: 'contato@modaazul.com',
  cnpj: '93339970000105',
};

export const LOJA_DE_TESTE = {
  nome: 'Moda Azul',
  slug: 'moda-azul',
  endereco_publico: null,
};

// Sessão pronta para os testes das páginas do dashboard (sem passar pela API).
export default function SessaoDeTeste({ children, valor }) {
  return (
    <SessaoContext.Provider
      value={{
        estado: 'logado',
        token: 'token-de-teste',
        conta: CONTA_DE_TESTE,
        loja: LOJA_DE_TESTE,
        aviso: '',
        falhaDeRede: false,
        entrar: jest.fn(),
        cadastrar: jest.fn(),
        sair: jest.fn(),
        expirar: jest.fn(),
        limparAviso: jest.fn(),
        atualizarLoja: jest.fn(),
        ...valor,
      }}
    >
      {children}
    </SessaoContext.Provider>
  );
}

SessaoDeTeste.propTypes = {
  children: PropTypes.node.isRequired,
  valor: PropTypes.shape({}),
};

SessaoDeTeste.defaultProps = {
  valor: {},
};
