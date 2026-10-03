import React from 'react';
import { render } from '@testing-library/react';

import App from './App';

function renderAppAt(path) {
  window.history.pushState({}, '', path);
  return render(<App />);
}

describe('Rotas da aplicação', () => {
  it('exibe o dashboard em /dashboard sem exigir login', () => {
    const { getByText } = renderAppAt('/dashboard');

    expect(getByText('Fila', { selector: 'h3' })).toBeInTheDocument();
    expect(getByText('Em Atendimento', { selector: 'h3' })).toBeInTheDocument();
  });

  it('exibe a geração de QR code em /dashboard/qrcode', () => {
    const { getByText } = renderAppAt('/dashboard/qrcode');

    expect(getByText('Gerar QRCode', { selector: 'h1' })).toBeInTheDocument();
  });

  it('exibe as configurações em /dashboard/perfil', () => {
    const { getByText } = renderAppAt('/dashboard/perfil');

    expect(getByText('Perfil', { selector: 'h1' })).toBeInTheDocument();
  });
});
