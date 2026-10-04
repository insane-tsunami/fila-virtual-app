import React, { useState } from 'react';
import { Link } from 'react-router-dom';

import Button from '@material-ui/core/Button';

import { FaixaAviso } from './styles';
import { reenviarConfirmacao } from '../api';
import { useSessao } from '../sessao/SessaoProvider';

const ERRO_REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const AINDA_NAO =
  'Ainda não confirmado. Abra o link do e-mail ou peça um novo.';

// Faixa do dashboard enquanto o e-mail da conta não está confirmado. A conta não confirmada
// usa o dashboard normalmente; a faixa só lembra de confirmar (e dá a saída para um e-mail
// digitado errado).
export default function AvisoEmail() {
  const { conta, token, expirar, atualizarConta } = useSessao();
  const [mensagem, setMensagem] = useState('');
  const [ocupado, setOcupado] = useState(false);

  if (!conta || conta.email_confirmado !== false) return null;

  async function reenviar() {
    setMensagem('');
    setOcupado(true);
    try {
      const dados = await reenviarConfirmacao(token);
      setMensagem(dados.mensagem);
    } catch (e) {
      if (e.status === 401) {
        expirar();
        return;
      }
      setMensagem(e.status === 0 ? ERRO_REDE : e.message);
    }
    setOcupado(false);
  }

  async function jaConfirmei() {
    setMensagem('');
    setOcupado(true);
    try {
      const nova = await atualizarConta();
      // com o e-mail confirmado a faixa some (a conta nova já está no estado da sessão)
      if (nova && nova.email_confirmado === false) setMensagem(AINDA_NAO);
    } catch (e) {
      setMensagem(e.status === 0 ? ERRO_REDE : e.message);
    }
    setOcupado(false);
  }

  return (
    <FaixaAviso role="region" aria-label="Confirmação do e-mail">
      <p>
        <strong>Confirme seu e-mail.</strong>
        Enviamos um link para {conta.email}. Sem confirmar, não dá para
        recuperar a senha por e-mail.
      </p>
      <Button
        variant="contained"
        size="small"
        color="secondary"
        disabled={ocupado}
        onClick={reenviar}
      >
        Reenviar e-mail
      </Button>
      <Button
        variant="outlined"
        size="small"
        color="secondary"
        disabled={ocupado}
        onClick={jaConfirmei}
      >
        Já confirmei
      </Button>
      <Button
        size="small"
        color="secondary"
        component={Link}
        to="/dashboard/perfil"
      >
        Trocar e-mail
      </Button>
      {mensagem && <p role="status">{mensagem}</p>}
    </FaixaAviso>
  );
}
