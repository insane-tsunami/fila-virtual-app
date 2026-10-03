function resposta(status, corpo, { json = true } = {}) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: json
      ? () => Promise.resolve(corpo)
      : () => Promise.reject(new Error('x')),
  };
}

function carregar(base) {
  jest.resetModules();
  if (base === undefined) delete process.env.REACT_APP_API_URL;
  else process.env.REACT_APP_API_URL = base;
  // eslint-disable-next-line global-require
  return require('./api');
}

afterEach(() => {
  delete process.env.REACT_APP_API_URL;
  delete global.fetch;
});

describe('api', () => {
  it('devolve os dados em caso de sucesso e usa a mesma origem com base vazia', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(200, { nome: 'Veste Bem' }));
    const { buscarLoja } = carregar('');

    await expect(buscarLoja('veste-bem')).resolves.toEqual({
      nome: 'Veste Bem',
    });
    expect(global.fetch.mock.calls[0][0]).toBe('/api/filas/veste-bem');
  });

  it('usa a base configurada, sem barra final', async () => {
    global.fetch = jest.fn().mockResolvedValue(resposta(200, {}));
    const { buscarLoja } = carregar('https://api.exemplo.com/');

    await buscarLoja('veste-bem');
    expect(global.fetch.mock.calls[0][0]).toBe(
      'https://api.exemplo.com/api/filas/veste-bem'
    );
  });

  it('entra na fila enviando o telefone em JSON', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(201, { codigo: 'abc' }));
    const { entrarNaFila } = carregar('');

    await entrarNaFila('veste-bem', '11971778203');
    const [url, opcoes] = global.fetch.mock.calls[0];
    expect(url).toBe('/api/filas/veste-bem/entradas');
    expect(opcoes.method).toBe('POST');
    expect(JSON.parse(opcoes.body)).toEqual({ telefone: '11971778203' });
  });

  it('consulta a entrada pelo código', async () => {
    global.fetch = jest.fn().mockResolvedValue(resposta(200, { posicao: 2 }));
    const { consultarEntrada } = carregar('');

    await expect(consultarEntrada('veste-bem', 'abc')).resolves.toEqual({
      posicao: 2,
    });
    expect(global.fetch.mock.calls[0][0]).toBe(
      '/api/filas/veste-bem/entradas/abc'
    );
  });

  it('422 e 404 trazem status e a mensagem do JSON', async () => {
    const { entrarNaFila, buscarLoja, ApiError } = carregar('');

    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(422, { erro: 'Telefone inválido' }));
    await expect(entrarNaFila('veste-bem', '1')).rejects.toMatchObject({
      status: 422,
      message: 'Telefone inválido',
    });

    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(404, { erro: 'Loja não encontrada' }));
    const erro = await buscarLoja('x').catch((e) => e);
    expect(erro).toBeInstanceOf(ApiError);
    expect(erro.status).toBe(404);
    expect(erro.message).toBe('Loja não encontrada');
  });

  it('falha de rede vira ApiError com status 0', async () => {
    global.fetch = jest
      .fn()
      .mockRejectedValue(new TypeError('Failed to fetch'));
    const { buscarLoja } = carregar('');

    await expect(buscarLoja('veste-bem')).rejects.toMatchObject({ status: 0 });
  });

  it('corpo que não é JSON vira erro com o status HTTP', async () => {
    const { buscarLoja } = carregar('');

    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(502, null, { json: false }));
    await expect(buscarLoja('veste-bem')).rejects.toMatchObject({
      status: 502,
    });

    global.fetch = jest
      .fn()
      .mockResolvedValue(resposta(200, null, { json: false }));
    await expect(buscarLoja('veste-bem')).rejects.toMatchObject({
      status: 200,
    });
  });
});
