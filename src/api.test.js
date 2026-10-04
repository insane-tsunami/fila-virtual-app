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

  describe('chamadas protegidas', () => {
    it('listarFila envia o token como Bearer e não envia corpo', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(200, []));
      const { listarFila } = carregar('');

      await expect(listarFila('veste-bem', 'segredo')).resolves.toEqual([]);
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/filas/veste-bem/entradas');
      expect(opcoes.method).toBe('GET');
      expect(opcoes.headers.Authorization).toBe('Bearer segredo');
      expect(opcoes.headers['X-API-Key']).toBeUndefined();
      expect(opcoes.body).toBeUndefined();
      expect(opcoes.headers['Content-Type']).toBeUndefined();
    });

    it('finalizarEntrada faz POST no código com o token', async () => {
      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(200, { atual: null }));
      const { finalizarEntrada } = carregar('');

      await finalizarEntrada('veste-bem', 'abc', 'segredo');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/filas/veste-bem/entradas/abc/finalizar');
      expect(opcoes.method).toBe('POST');
      expect(opcoes.headers.Authorization).toBe('Bearer segredo');
      expect(opcoes.headers['X-API-Key']).toBeUndefined();
    });

    it('definirEndereco faz PUT em JSON com o token, inclusive texto vazio', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(200, {}));
      const { definirEndereco } = carregar('');

      await definirEndereco('veste-bem', '', 'segredo');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/filas/veste-bem/endereco');
      expect(opcoes.method).toBe('PUT');
      expect(opcoes.headers.Authorization).toBe('Bearer segredo');
      expect(opcoes.headers['X-API-Key']).toBeUndefined();
      expect(opcoes.headers['Content-Type']).toBe('application/json');
      expect(JSON.parse(opcoes.body)).toEqual({ endereco_publico: '' });
    });

    it('as chamadas públicas não enviam credencial', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(200, {}));
      const { buscarLoja, consultarEntrada } = carregar('');

      await buscarLoja('veste-bem');
      await consultarEntrada('veste-bem', 'abc');
      global.fetch.mock.calls.forEach(([, opcoes]) => {
        expect(opcoes.headers.Authorization).toBeUndefined();
        expect(opcoes.headers['X-API-Key']).toBeUndefined();
      });
    });

    it('401, 409 e 422 trazem status e mensagem', async () => {
      const { listarFila, finalizarEntrada, definirEndereco } = carregar('');

      global.fetch = jest
        .fn()
        .mockResolvedValue(
          resposta(401, { erro: 'Autenticação ausente ou inválida.' })
        );
      await expect(listarFila('x', 'k')).rejects.toMatchObject({
        status: 401,
        message: 'Autenticação ausente ou inválida.',
      });

      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(409, { erro: 'Não está em atendimento' }));
      await expect(finalizarEntrada('x', 'c', 'k')).rejects.toMatchObject({
        status: 409,
        message: 'Não está em atendimento',
      });

      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(422, { erro: 'Endereço inválido' }));
      await expect(definirEndereco('x', 'ftp://a', 'k')).rejects.toMatchObject({
        status: 422,
        message: 'Endereço inválido',
      });
    });
  });

  describe('conta e sessão', () => {
    it('cadastrar faz POST em /api/contas com os quatro campos, sem credencial', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(201, { token: 't' }));
      const { cadastrar } = carregar('');

      await expect(
        cadastrar({
          email: 'a@b.com',
          cnpj: '93.339.970/0001-05',
          nome: 'Moda Azul',
          senha: 'senha-segura-1',
        })
      ).resolves.toEqual({ token: 't' });
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/contas');
      expect(opcoes.method).toBe('POST');
      expect(opcoes.headers.Authorization).toBeUndefined();
      expect(JSON.parse(opcoes.body)).toEqual({
        email: 'a@b.com',
        cnpj: '93.339.970/0001-05',
        nome: 'Moda Azul',
        senha: 'senha-segura-1',
      });
    });

    it('entrar faz POST em /api/sessoes com e-mail e senha, sem credencial', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(200, { token: 't' }));
      const { entrar } = carregar('');

      await entrar('a@b.com', 'senha-segura-1');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/sessoes');
      expect(opcoes.method).toBe('POST');
      expect(opcoes.headers.Authorization).toBeUndefined();
      expect(JSON.parse(opcoes.body)).toEqual({
        email: 'a@b.com',
        senha: 'senha-segura-1',
      });
    });

    it('obterConta faz GET em /api/conta com o token', async () => {
      global.fetch = jest.fn().mockResolvedValue(resposta(200, { conta: {} }));
      const { obterConta } = carregar('');

      await obterConta('tok');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/conta');
      expect(opcoes.method).toBe('GET');
      expect(opcoes.headers.Authorization).toBe('Bearer tok');
    });

    it('trocarSenha faz PUT em /api/conta/senha com o token e os dois campos', async () => {
      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(200, { mensagem: 'ok' }));
      const { trocarSenha } = carregar('');

      await trocarSenha('tok', 'atual-1234', 'nova-12345');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/conta/senha');
      expect(opcoes.method).toBe('PUT');
      expect(opcoes.headers.Authorization).toBe('Bearer tok');
      expect(JSON.parse(opcoes.body)).toEqual({
        senha_atual: 'atual-1234',
        nova_senha: 'nova-12345',
      });
    });

    it('pedirRedefinicao faz POST em /api/senha/esqueci só com o e-mail, sem token', async () => {
      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(202, { mensagem: 'ok' }));
      const { pedirRedefinicao } = carregar('');

      await expect(pedirRedefinicao('dona@exemplo.com')).resolves.toEqual({
        mensagem: 'ok',
      });
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/senha/esqueci');
      expect(opcoes.method).toBe('POST');
      expect(opcoes.headers.Authorization).toBeUndefined();
      expect(JSON.parse(opcoes.body)).toEqual({ email: 'dona@exemplo.com' });
    });

    it('redefinirSenha faz POST em /api/senha/redefinir com token e nova_senha', async () => {
      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(200, { mensagem: 'Senha alterada.' }));
      const { redefinirSenha } = carregar('');

      await redefinirSenha('tok-do-email', 'nova-12345');
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/senha/redefinir');
      expect(opcoes.method).toBe('POST');
      expect(opcoes.headers.Authorization).toBeUndefined();
      expect(JSON.parse(opcoes.body)).toEqual({
        token: 'tok-do-email',
        nova_senha: 'nova-12345',
      });
    });

    it('os erros 422 e 429 do esqueci a senha viram ApiError com a mensagem da API', async () => {
      const { pedirRedefinicao, redefinirSenha, ApiError } = carregar('');

      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(429, { erro: 'Muitas tentativas.' }));
      await expect(pedirRedefinicao('a@b.com')).rejects.toMatchObject({
        status: 429,
        message: 'Muitas tentativas.',
      });

      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(422, { erro: 'Link inválido.' }));
      const falha = await redefinirSenha('x', 'y').catch((e) => e);
      expect(falha).toBeInstanceOf(ApiError);
      expect(falha.status).toBe(422);
    });

    it('sair faz DELETE em /api/sessao e aceita a resposta 204 sem corpo', async () => {
      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(204, null, { json: false }));
      const { sair } = carregar('');

      await expect(sair('tok')).resolves.toEqual({});
      const [url, opcoes] = global.fetch.mock.calls[0];
      expect(url).toBe('/api/sessao');
      expect(opcoes.method).toBe('DELETE');
      expect(opcoes.headers.Authorization).toBe('Bearer tok');
    });

    it('409 e 422 do cadastro e 401 do login trazem status e mensagem', async () => {
      const { cadastrar, entrar } = carregar('');

      global.fetch = jest
        .fn()
        .mockResolvedValue(
          resposta(409, { erro: 'Já existe uma conta com este e-mail.' })
        );
      await expect(cadastrar({})).rejects.toMatchObject({
        status: 409,
        message: 'Já existe uma conta com este e-mail.',
      });

      global.fetch = jest
        .fn()
        .mockResolvedValue(resposta(422, { erro: 'E-mail inválido.' }));
      await expect(cadastrar({})).rejects.toMatchObject({ status: 422 });

      global.fetch = jest
        .fn()
        .mockResolvedValue(
          resposta(401, { erro: 'E-mail ou senha incorretos.' })
        );
      await expect(entrar('a@b.com', 'x')).rejects.toMatchObject({
        status: 401,
        message: 'E-mail ou senha incorretos.',
      });
    });
  });
});
