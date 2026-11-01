import React from 'react';
import { MemoryRouter, Route } from 'react-router-dom';
import { render, wait } from '@testing-library/react';

import Privacidade from '.';
import { buscarPrivacidade } from '../api';

jest.mock('../api', () => ({
  buscarPrivacidade: jest.fn(),
}));

const AVISO = {
  controlador: 'ZeraFilas',
  email: 'privacidade@zerafilas.example',
  encarregado: 'Maria',
  base_legal: 'legítimo interesse (art. 7º, IX, da LGPD)',
  retencao_entrada_ativa_horas: 24,
  retencao_telefone_dias: 30,
};

function renderPagina() {
  return render(
    <MemoryRouter initialEntries={['/privacidade']}>
      <Route path="/privacidade">
        <Privacidade />
      </Route>
    </MemoryRouter>
  );
}

beforeEach(() => {
  buscarPrivacidade.mockReset().mockResolvedValue(AVISO);
});

describe('Aviso de privacidade', () => {
  it('mostra controlador, contato e prazos da configuração', async () => {
    const u = renderPagina();

    await wait(() =>
      expect(
        u.getByText('O ZeraFilas é operado por ZeraFilas.')
      ).toBeInTheDocument()
    );
    expect(
      u.getByText(/Contato de privacidade: privacidade@zerafilas\.example\./)
    ).toBeInTheDocument();
    expect(u.getByText(/Encarregado: Maria/)).toBeInTheDocument();
    expect(
      u.getByText(/30 dias depois do fim do atendimento/)
    ).toBeInTheDocument();
    expect(u.getByText(/24 horas é encerrada/)).toBeInTheDocument();
    expect(buscarPrivacidade).toHaveBeenCalled();
  });

  it('explica a finalidade, a base legal e os direitos', async () => {
    const u = renderPagina();
    await wait(() =>
      expect(u.getByText(/legítimo interesse/)).toBeInTheDocument()
    );

    expect(u.getByText(/organizar a fila da loja/)).toBeInTheDocument();
    expect(u.getByText(/Sair da fila/)).toBeInTheDocument();
    expect(u.getByText(/execução de contrato/)).toBeInTheDocument();
  });

  it('API fora do ar: mantém o texto e avisa', async () => {
    buscarPrivacidade.mockRejectedValue(new Error('rede'));
    const u = renderPagina();

    await wait(() =>
      expect(
        u.getByText(/Não foi possível carregar a configuração/)
      ).toBeInTheDocument()
    );
    expect(u.getByText('Aviso de Privacidade')).toBeInTheDocument();
    expect(u.queryByText(/Contato de privacidade/)).not.toBeInTheDocument();
  });
});
