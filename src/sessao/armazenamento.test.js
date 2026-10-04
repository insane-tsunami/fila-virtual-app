import { lerToken, guardarToken, apagarToken } from './armazenamento';

afterEach(() => {
  apagarToken();
  jest.restoreAllMocks();
  window.sessionStorage.clear();
  window.localStorage.clear();
});

describe('guarda do token de sessão', () => {
  it('guarda, lê e apaga', () => {
    expect(lerToken()).toBeNull();

    guardarToken('tok');
    expect(lerToken()).toBe('tok');

    apagarToken();
    expect(lerToken()).toBeNull();
  });

  it('usa só o armazenamento de sessão, nunca o persistente', () => {
    guardarToken('tok');

    expect(window.sessionStorage.getItem('zerafilas:token')).toBe('tok');
    expect(window.localStorage.length).toBe(0);
  });

  it('com o armazenamento bloqueado, mantém o token em memória sem lançar', () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);

    expect(() => guardarToken('tok')).not.toThrow();
    expect(lerToken()).toBe('tok');
    expect(() => apagarToken()).not.toThrow();
    expect(lerToken()).toBeNull();
  });
});
