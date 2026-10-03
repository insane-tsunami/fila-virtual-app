import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import QrCode from './Qrcode';

function renderQrCode() {
  return render(
    <MemoryRouter>
      <QrCode />
    </MemoryRouter>
  );
}

describe('Geração de QR code', () => {
  it('exibe o título e o botão "Gerar QRCode"', () => {
    const { getByText, container } = renderQrCode();

    expect(getByText('Gerar QRCode', { selector: 'h1' })).toBeInTheDocument();
    expect(
      getByText('Gerar QRCode', { selector: '.MuiTypography-root' })
    ).toBeInTheDocument();
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('não gera imagem nem altera a página ao acionar o botão', () => {
    const { getByText, container } = renderQrCode();
    const antes = container.innerHTML;

    fireEvent.click(
      getByText('Gerar QRCode', { selector: '.MuiTypography-root' })
    );

    expect(container.querySelector('img')).not.toBeInTheDocument();
    expect(container.innerHTML).toBe(antes);
  });
});
