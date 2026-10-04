import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import PropTypes from 'prop-types';
import { Redirect, useHistory, useLocation } from 'react-router-dom';

import * as api from '../api';
import { lerToken, guardarToken, apagarToken } from './armazenamento';

const AVISO_EXPIROU = 'Sua sessão expirou. Entre de novo.';

export const SessaoContext = createContext(null);

// Sessão do estabelecimento: o token (em sessionStorage) e a conta/loja que a API
// devolve para ele. `estado` é 'restaurando' (confirmando o token guardado),
// 'anonimo' ou 'logado'.
export function SessaoProvider({ children }) {
  const history = useHistory();
  // Um estado só (token, conta e loja mudam juntos): atualizar em partes, fora de um
  // evento do React, faria as páginas renderizarem com a loja já nula antes da rota
  // protegida tirá-las da tela.
  const [sessao, setSessao] = useState(() => {
    const guardado = lerToken();
    return {
      estado: guardado ? 'restaurando' : 'anonimo',
      token: guardado,
      conta: null,
      loja: null,
    };
  });
  const [aviso, setAviso] = useState('');
  const [falhaDeRede, setFalhaDeRede] = useState(false);
  const { estado, token, conta, loja } = sessao;

  // Ao abrir a página com um token guardado, confirma a sessão na API (só o estado
  // 'restaurando', que existe apenas na abertura, dispara a chamada).
  useEffect(() => {
    if (estado !== 'restaurando') return undefined;
    let cancelado = false;
    api
      .obterConta(token)
      .then((dados) => {
        if (cancelado) return;
        setSessao({
          estado: 'logado',
          token,
          conta: dados.conta,
          loja: dados.loja,
        });
      })
      .catch((e) => {
        if (cancelado) return;
        if (e.status === 401) {
          apagarToken();
          setSessao({
            estado: 'anonimo',
            token: null,
            conta: null,
            loja: null,
          });
        } else {
          setFalhaDeRede(true);
          setSessao({ estado: 'anonimo', token, conta: null, loja: null });
        }
      });
    return () => {
      cancelado = true;
    };
  }, [estado, token]);

  const abrir = useCallback((dados) => {
    guardarToken(dados.token);
    setSessao({
      estado: 'logado',
      token: dados.token,
      conta: dados.conta,
      loja: dados.loja,
    });
    setAviso('');
    setFalhaDeRede(false);
  }, []);

  const entrar = useCallback(
    async (email, senha) => abrir(await api.entrar(email, senha)),
    [abrir]
  );

  const cadastrar = useCallback(
    async (dados) => abrir(await api.cadastrar(dados)),
    [abrir]
  );

  const encerrar = useCallback(() => {
    apagarToken();
    setSessao({ estado: 'anonimo', token: null, conta: null, loja: null });
  }, []);

  // Sair não espera a API: o token local é apagado e a navegação acontece na hora; o
  // pedido de encerramento segue em segundo plano e, se falhar, o token vence em 7 dias.
  const sair = useCallback(() => {
    const tokenAtual = token;
    encerrar();
    history.push('/');
    api.sair(tokenAtual).catch(() => {});
  }, [token, encerrar, history]);

  // Só as chamadas autenticadas devem chamar isto (o 401 do login não é expiração).
  const expirar = useCallback(() => {
    setAviso(AVISO_EXPIROU);
    encerrar();
  }, [encerrar]);

  const limparAviso = useCallback(() => setAviso(''), []);

  const atualizarLoja = useCallback(
    (nova) => setSessao((atual) => ({ ...atual, loja: nova })),
    []
  );

  // Reconsulta a conta (ex.: o e-mail foi confirmado em outro aparelho). Devolve a conta
  // nova; 401 expira a sessão; falha de rede chega a quem chamou, sem derrubar a sessão.
  const atualizarConta = useCallback(async () => {
    try {
      const dados = await api.obterConta(token);
      setSessao((atual) => ({
        ...atual,
        conta: dados.conta,
        loja: dados.loja,
      }));
      return dados.conta;
    } catch (e) {
      if (e.status === 401) {
        expirar();
        return null;
      }
      throw e;
    }
  }, [token, expirar]);

  const definirConta = useCallback(
    (nova) => setSessao((atual) => ({ ...atual, conta: nova })),
    []
  );

  const valor = useMemo(
    () => ({
      estado,
      token,
      conta,
      loja,
      aviso,
      falhaDeRede,
      entrar,
      cadastrar,
      sair,
      expirar,
      limparAviso,
      atualizarLoja,
      atualizarConta,
      definirConta,
    }),
    [
      estado,
      token,
      conta,
      loja,
      aviso,
      falhaDeRede,
      entrar,
      cadastrar,
      sair,
      expirar,
      limparAviso,
      atualizarLoja,
      atualizarConta,
      definirConta,
    ]
  );

  return (
    <SessaoContext.Provider value={valor}>{children}</SessaoContext.Provider>
  );
}

SessaoProvider.propTypes = {
  children: PropTypes.node.isRequired,
};

export function useSessao() {
  return useContext(SessaoContext);
}

const Carregando = () => <p>Carregando...</p>;

// Páginas do dashboard: só para quem tem sessão; os demais vão para /login e,
// depois de entrar, voltam para a página que tentavam abrir.
export function RotaProtegida({ children }) {
  const { estado } = useSessao();
  const location = useLocation();

  if (estado === 'restaurando') return <Carregando />;
  if (estado === 'anonimo') {
    return <Redirect to={{ pathname: '/login', state: { from: location } }} />;
  }
  return children;
}

RotaProtegida.propTypes = {
  children: PropTypes.node.isRequired,
};

// /login e /cadastro: quem já tem sessão vai para o dashboard (ou para a página
// que tentava abrir antes de passar pelo login).
export function RotaAnonima({ children }) {
  const { estado } = useSessao();
  const location = useLocation();

  if (estado === 'restaurando') return <Carregando />;
  if (estado === 'logado') {
    const destino = (location.state && location.state.from) || '/dashboard';
    return <Redirect to={destino} />;
  }
  return children;
}

RotaAnonima.propTypes = {
  children: PropTypes.node.isRequired,
};
