import { lerChave, guardarChave, apagarChave } from './chave';

afterEach(() => {
  apagarChave();
  jest.restoreAllMocks();
  window.sessionStorage.clear();
  window.localStorage.clear();
});

describe('guarda da chave de acesso', () => {
  it('guarda, lê e apaga', () => {
    expect(lerChave()).toBeNull();

    guardarChave('segredo');
    expect(lerChave()).toBe('segredo');

    apagarChave();
    expect(lerChave()).toBeNull();
  });

  it('usa só o armazenamento de sessão, nunca o persistente', () => {
    const local = jest.spyOn(Storage.prototype, 'setItem');
    guardarChave('segredo');

    expect(window.sessionStorage.getItem('zerafilas:chave')).toBe('segredo');
    expect(window.localStorage.length).toBe(0);
    local.mock.instances.forEach((i) => expect(i).toBe(window.sessionStorage));
  });

  it('com o armazenamento bloqueado, mantém a chave em memória sem lançar', () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);

    expect(() => guardarChave('segredo')).not.toThrow();
    expect(lerChave()).toBe('segredo');
    expect(() => apagarChave()).not.toThrow();
    expect(lerChave()).toBeNull();
  });
});
