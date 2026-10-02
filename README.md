## MegaHack 2.0

Este projeto foi desenvolvido durante o MegaHack 2.0

Vejá uma prévia aqui: https://zerafilas.now.sh/
Dashboard: https://zerafilas.now.sh/dashboard


## Desenvolvimento com OpenSpec

O projeto usa o [OpenSpec](https://github.com/Fission-AI/OpenSpec) para desenvolvimento orientado a especificações (spec-driven). As specs e propostas ficam em `openspec/`, e as skills e comandos do Claude Code já estão commitados em `.claude/`.

### Instalação da CLI (opcional)

As skills e os comandos `/opsx:*` funcionam sem a CLI. Para rodar `openspec list`, `openspec validate` etc. na sua máquina:

```bash
npm install -g @fission-ai/openspec
```

### Como usar no Claude Code

| Comando | O que faz |
|---|---|
| `/opsx:propose "ideia"` | Cria uma proposta de mudança (proposta, design, tarefas e specs) |
| `/opsx:explore` | Explora e discute ideias antes de propor |
| `/opsx:apply` | Implementa as tarefas de uma mudança |
| `/opsx:update` | Atualiza os artefatos de uma mudança existente |
| `/opsx:sync` | Sincroniza as specs da mudança com `openspec/specs/` |
| `/opsx:archive` | Arquiva a mudança concluída |

O contexto do projeto (stack, rotas, regras) está em `openspec/config.yaml`. Se os comandos não aparecerem, reinicie a sessão do Claude Code.

### Configuração do servidor

As credenciais do MySQL vêm de variáveis de ambiente (`DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Use `server/.env.example` como modelo e nunca commite segredos.
