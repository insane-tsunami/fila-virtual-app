import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, within } from '@testing-library/react';

import Dashboard from '.';
import { nome, inicial } from './estabelecimento';

function renderDashboard() {
  const utils = render(
    <MemoryRouter>
      <Dashboard />
    </MemoryRouter>
  );
  const painel = (titulo) =>
    utils.getByText(titulo, { selector: 'h3' }).parentElement;
  return {
    ...utils,
    fila: painel('Fila'),
    atendimento: painel('Em Atendimento'),
  };
}

function finalizar({ getByText }, vezes = 1) {
  for (let i = 0; i < vezes; i += 1) {
    fireEvent.click(getByText('Finalizar Atendimento'));
  }
}

describe('Dashboard da fila', () => {
  it('mostra o primeiro cliente em atendimento e os demais na fila', () => {
    const { fila, atendimento } = renderDashboard();

    expect(within(atendimento).getByText('31')).toBeInTheDocument();
    expect(within(fila).getByText('32')).toBeInTheDocument();
    expect(within(fila).getByText('33')).toBeInTheDocument();
    expect(within(fila).getAllByText(/^\*{5}\d{4}$/).length).toBeGreaterThan(1);
  });

  it('promove o próximo cliente ao finalizar um atendimento', () => {
    const utils = renderDashboard();

    finalizar(utils);

    expect(within(utils.atendimento).getByText('32')).toBeInTheDocument();
    expect(utils.queryByText('31')).not.toBeInTheDocument();
    expect(within(utils.fila).getByText('33')).toBeInTheDocument();
  });

  it('mostra "Fila vazia" quando só resta o cliente atual', () => {
    const utils = renderDashboard();

    finalizar(utils, 12);

    expect(within(utils.atendimento).getByText('43')).toBeInTheDocument();
    expect(within(utils.fila).getByText('Fila vazia')).toBeInTheDocument();
  });

  it('mostra a mensagem final, sem "0" solto, ao atender todos', () => {
    const utils = renderDashboard();

    finalizar(utils, 13);

    expect(
      within(utils.atendimento).getByText(/Atendeu todos os clientes!/)
    ).toBeInTheDocument();
    expect(utils.queryByText('Finalizar Atendimento')).not.toBeInTheDocument();
    expect(utils.queryByText('0')).not.toBeInTheDocument();
    expect(utils.fila.textContent).toBe('Fila');
  });

  it('exibe nome e inicial do estabelecimento na barra lateral', () => {
    const { getByText } = renderDashboard();

    expect(getByText(nome)).toBeInTheDocument();
    expect(getByText(inicial)).toBeInTheDocument();
  });
});
