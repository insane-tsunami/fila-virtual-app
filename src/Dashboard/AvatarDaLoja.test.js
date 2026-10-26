import React from 'react';
import { render } from '@testing-library/react';

import AvatarDaLoja from './AvatarDaLoja';
import { CORES, ICONES, desenhoDoAvatar } from './avatar';

describe('Listas do avatar', () => {
  it('têm os 6 nomes de cor e os 6 de ícone que a API aceita', () => {
    expect(CORES.map((c) => c.nome)).toEqual([
      'roxo',
      'azul',
      'verde',
      'laranja',
      'vermelho',
      'cinza',
    ]);
    expect(ICONES.map((i) => i.nome)).toEqual([
      'loja',
      'mercado',
      'restaurante',
      'cafe',
      'farmacia',
      'beleza',
    ]);
  });

  it('desenhoDoAvatar: conhecido, nulo e desconhecido', () => {
    const desenho = desenhoDoAvatar({ cor: 'verde', icone: 'restaurante' });
    expect(desenho.cor.hex).toBe('#2e7d32');
    expect(desenho.icone.rotulo).toBe('Restaurante');
    expect(desenhoDoAvatar(null)).toBeNull();
    expect(desenhoDoAvatar({ cor: 'Verde', icone: 'restaurante' })).toBeNull();
    expect(desenhoDoAvatar({ cor: 'verde', icone: 'carro' })).toBeNull();
  });
});

describe('AvatarDaLoja', () => {
  it('com avatar: o ícone sobre a cor, sem a inicial', () => {
    const u = render(
      <AvatarDaLoja nome="Moda Azul" avatar={{ cor: 'azul', icone: 'cafe' }} />
    );

    expect(u.getByTitle('Avatar: Café sobre Azul')).toBeInTheDocument();
    expect(u.container.firstChild).toHaveStyle('background-color: #1565c0');
    expect(u.queryByText('M')).not.toBeInTheDocument();
  });

  it('sem avatar: a inicial em maiúscula', () => {
    const u = render(<AvatarDaLoja nome="ótica sol" avatar={null} />);

    expect(u.getByText('Ó')).toBeInTheDocument();
  });

  it('com avatar desconhecido: a inicial', () => {
    const u = render(
      <AvatarDaLoja nome="Moda Azul" avatar={{ cor: 'rosa', icone: 'loja' }} />
    );

    expect(u.getByText('M')).toBeInTheDocument();
    expect(u.queryByTitle(/Avatar:/)).not.toBeInTheDocument();
  });
});
