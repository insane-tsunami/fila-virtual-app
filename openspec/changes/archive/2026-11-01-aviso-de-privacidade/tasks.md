# Tasks

## 1. API

- [x] 1.1 Acrescentar a seção `privacidade` (`controlador`, `email`, `encarregado`) em `server/api/config.php`, com padrões provisórios, e as variáveis em `server/.env.example`; testar padrões e leitura em `server/tests/ConfigTest.php`.
- [x] 1.2 Criar `Controllers\PrivacidadeController` e a rota pública `GET /api/privacidade` em `server/app/support/Aplicacao.php`, devolvendo também `retencao_telefone_dias`, `retencao_entrada_ativa_horas` e `base_legal`. Teste de API em `server/tests/PrivacyApiTest.php` (200, campos e prazos iguais à configuração, sem sessão). Verificar com `composer test`.

## 2. Front

- [x] 2.1 Criar `src/Privacidade/index.js` (e estilos) com o texto do aviso interpolando a configuração, rota pública em `src/App.js` e `buscarPrivacidade` em `src/api.js`. Teste em `src/Privacidade/index.test.js`.
- [x] 2.2 Mostrar o aviso curto sob o campo de telefone em `src/Cliente/index.js` (com o prazo do endpoint e link para `/privacidade`); teste em `src/Cliente/index.test.js`.
- [x] 2.3 Exigir o checkbox "Li e aceito o Aviso de Privacidade" em `src/Register/index.js`, com link, e desabilitar "Cadastrar" sem ele; testes em `src/Register/index.test.js`.
- [x] 2.4 Adicionar o link para `/privacidade` no rodapé da `Home` e no Perfil; testes.
- [x] 2.5 Verificar com `CI=true yarn test --watchAll=false`, `yarn lint` e `CI=true yarn build`.

## 3. Documentação

- [x] 3.1 `server/README.md`: variáveis `PRIVACIDADE_*`, endpoint, prazos, teste de balanceamento do legítimo interesse e pendências jurídicas (revisão do texto, controlador/encarregado reais, contrato com as lojas, incidentes, transferência internacional). `README.md`: aviso e links. `openspec/config.yaml`: contexto do aviso.
- [x] 3.2 Verificar com `openspec validate --all --strict`.
