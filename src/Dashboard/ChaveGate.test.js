import React from 'react';
import { render, fireEvent, act } from '@testing-library/react';

import ChaveGate, { useChave } from './ChaveGate';
import App from '../App';
import { ApiError, listarFila } from '../api';
import { lerChave, guardarChave, apagarChave } from './chave';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    listarFila: jest.fn(),
    buscarLoja: jest.fn(() => Promise.resolve({ nome: 'Veste Bem' })),
  };
});

function Protegida() {
  const { chave, recusar } = useChave();
  return (
    <div>
      <p>conteúdo protegido</p>
      <p>chave em uso: {chave}</p>
      <button type="button" onClick={recusar}>
        simular 401
      </button>
    </div>
  );
}

function renderGate() {
  return render(
    <ChaveGate>
      <Protegida />
    </ChaveGate>
  );
}

async function entrarComChave(u, valor) {
  fireEvent.change(u.getByLabelText('Chave de acesso'), {
    target: { value: valor },
  });
  await act(async () => {
    fireEvent.submit(u.getByLabelText('Chave de acesso').closest('form'));
  });
}

beforeEach(() => {
  listarFila.mockReset();
  apagarChave();
  window.sessionStorage.clear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('Tela de chave', () => {
  it('sem chave: mostra o formulário e não chama a API', () => {
    const u = renderGate();

    expect(u.getByLabelText('Chave de acesso')).toBeInTheDocument();
    expect(u.getByText('Entrar')).toBeInTheDocument();
    expect(u.queryByText('conteúdo protegido')).not.toBeInTheDocument();
    expect(listarFila).not.toHaveBeenCalled();
  });

  it('chave correta: valida pela listagem, guarda e libera a página', async () => {
    listarFila.mockResolvedValue([]);
    const u = renderGate();
    await entrarComChave(u, 'segredo');

    expect(listarFila).toHaveBeenCalledWith('veste-bem', 'segredo');
    expect(u.getByText('conteúdo protegido')).toBeInTheDocument();
    expect(u.getByText('chave em uso: segredo')).toBeInTheDocument();
    expect(window.sessionStorage.getItem('zerafilas:chave')).toBe('segredo');
  });

  it('chave errada: "Chave inválida.", mantém o campo e não guarda', async () => {
    listarFila.mockRejectedValue(new ApiError(401, 'x'));
    const u = renderGate();
    await entrarComChave(u, 'errada');

    expect(u.getByText('Chave inválida.')).toBeInTheDocument();
    expect(u.getByLabelText('Chave de acesso')).toBeInTheDocument();
    expect(u.queryByText('conteúdo protegido')).not.toBeInTheDocument();
    expect(lerChave()).toBeNull();
  });

  it('API fora do ar: mensagem de rede e não guarda', async () => {
    listarFila.mockRejectedValue(new ApiError(0, 'rede'));
    const u = renderGate();
    await entrarComChave(u, 'segredo');

    expect(
      u.getByText('Não foi possível falar com o servidor. Tente de novo.')
    ).toBeInTheDocument();
    expect(lerChave()).toBeNull();
  });

  it('campo vazio: recusa sem chamar a API', async () => {
    const u = renderGate();
    await entrarComChave(u, '   ');

    expect(u.getByText('Chave inválida.')).toBeInTheDocument();
    expect(listarFila).not.toHaveBeenCalled();
  });

  it('recarregar na mesma sessão: não pede a chave de novo', () => {
    guardarChave('segredo');
    const u = renderGate();

    expect(u.getByText('conteúdo protegido')).toBeInTheDocument();
    expect(listarFila).not.toHaveBeenCalled();
  });

  it('armazenamento bloqueado: funciona e a chave some ao recarregar', async () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);
    listarFila.mockResolvedValue([]);

    const u = renderGate();
    await entrarComChave(u, 'segredo');
    expect(u.getByText('conteúdo protegido')).toBeInTheDocument();

    // "recarregar" = nova instância sem a memória do módulo
    u.unmount();
    apagarChave();
    expect(renderGate().getByLabelText('Chave de acesso')).toBeInTheDocument();
  });

  it('401 durante o uso: apaga a chave e volta à tela com o aviso', async () => {
    guardarChave('segredo');
    const u = renderGate();

    fireEvent.click(u.getByText('simular 401'));

    expect(u.getByText('Chave inválida.')).toBeInTheDocument();
    expect(u.getByLabelText('Chave de acesso')).toBeInTheDocument();
    expect(lerChave()).toBeNull();
  });
});

describe('Rotas protegidas pela chave', () => {
  const abrir = (caminho) => {
    window.history.pushState({}, '', caminho);
    return render(<App />);
  };

  it.each(['/dashboard', '/dashboard/qrcode'])('%s pede a chave', (caminho) => {
    const u = abrir(caminho);

    expect(u.getByLabelText('Chave de acesso')).toBeInTheDocument();
    expect(u.queryByText('Gerar QRCode', { selector: 'h1' })).toBeNull();
    expect(u.queryByText('Dashboard', { selector: 'h1' })).toBeNull();
  });

  it('/dashboard/perfil não pede a chave', () => {
    const u = abrir('/dashboard/perfil');

    expect(u.queryByLabelText('Chave de acesso')).not.toBeInTheDocument();
    expect(u.getByText('Perfil', { selector: 'h1' })).toBeInTheDocument();
  });
});
