import { guardarCodigo, lerCodigo, apagarCodigo } from './armazenamento';

afterEach(() => {
  jest.restoreAllMocks();
  window.localStorage.clear();
});

describe('armazenamento do código da entrada', () => {
  it('guarda, lê e apaga por loja, com lojas independentes', () => {
    guardarCodigo('a', 'codigo-a');
    guardarCodigo('b', 'codigo-b');

    expect(lerCodigo('a')).toBe('codigo-a');
    expect(lerCodigo('b')).toBe('codigo-b');

    apagarCodigo('a');
    expect(lerCodigo('a')).toBeNull();
    expect(lerCodigo('b')).toBe('codigo-b');
  });

  it('não lança exceção com o armazenamento bloqueado', () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);

    expect(() => guardarCodigo('a', 'x')).not.toThrow();
    expect(lerCodigo('a')).toBeNull();
    expect(() => apagarCodigo('a')).not.toThrow();
  });
});
