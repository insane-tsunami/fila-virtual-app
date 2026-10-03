import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Perfil from './Perfil';

function renderPerfil() {
  return render(
    <MemoryRouter>
      <Perfil />
    </MemoryRouter>
  );
}

describe('Configurações do estabelecimento', () => {
  const originalFetch = window.fetch;
  let fetchSpy;

  beforeEach(() => {
    fetchSpy = jest.fn();
    window.fetch = fetchSpy;
  });

  afterEach(() => {
    window.fetch = originalFetch;
  });

  it('exibe e-mail e CNPJ somente leitura com as mensagens', () => {
    const { getByLabelText, getByText } = renderPerfil();

    expect(getByLabelText('E-mail')).toHaveAttribute('readonly');
    expect(getByLabelText('E-mail').value).not.toBe('');
    expect(getByLabelText('CNPJ')).toHaveAttribute('readonly');
    expect(getByLabelText('CNPJ').value).not.toBe('');
    expect(getByText('Não é possivel alterar o e-mail')).toBeInTheDocument();
    expect(getByText('Não é possivel alterar o CNPJ')).toBeInTheDocument();
  });

  it('oferece nova senha mascarada, seletor só de imagens e "Atualizar"', () => {
    const { getByLabelText, getByText } = renderPerfil();

    expect(getByLabelText('Nova Senha')).toHaveAttribute('type', 'password');
    expect(getByLabelText('Confirme a nova senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(getByLabelText(/Imagem de Avatar/)).toHaveAttribute(
      'accept',
      'image/*'
    );
    expect(getByText('Atualizar')).toBeInTheDocument();
  });

  it('não envia nada nem altera os dados ao acionar "Atualizar"', () => {
    const { getByLabelText, getByText } = renderPerfil();
    const email = getByLabelText('E-mail').value;
    const cnpj = getByLabelText('CNPJ').value;

    fireEvent.change(getByLabelText('Nova Senha'), {
      target: { value: 'nova-senha' },
    });
    fireEvent.click(getByText('Atualizar'));

    expect(fetchSpy).not.toHaveBeenCalled();
    expect(getByLabelText('E-mail').value).toBe(email);
    expect(getByLabelText('CNPJ').value).toBe(cnpj);
  });
});
