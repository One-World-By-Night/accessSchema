# O registro de cargos

Todos os caminhos de cargo que o accessSchema conhece. Um cargo precisa estar aqui antes de alguém poder tê-lo.

## Quem pode usar

Administradores do site de sso.owbn.net.

## Como chegar

wp-admin em sso.owbn.net → **Users** → **accessSchema** (a tela se chama **Access Schema Role Registry**).

## A tela

- **Add Role(s)** - **Full Role Path(s)**, um por linha, com `/` entre os níveis: `chronicle/mckn/cm`. Minúsculas, sem plural. Clique em **Add Role(s)**.
- **Registered Roles** - todos os cargos, com **Name**, **Full Path** e **Depth**.
  - **Search roles...** acha cargos por nome ou caminho.
  - **Edit** muda o nome de exibição de um cargo; o caminho continua.
  - **Delete** remove um cargo. Se ele tem cargos abaixo, você é perguntado se quer apagá-los também.
  - **Bulk Actions**: **Delete**, ou **Export** para um arquivo CSV.

## Tarefas comuns

### Acrescentar os cargos de uma crônica nova

1. Em **Full Role Path(s)**, digite um por linha: `chronicle/<slug>/hst`, `chronicle/<slug>/cm`, `chronicle/<slug>/staff` e `chronicle/<slug>/player`. Uma satélite não tem `cm`.
2. Clique em **Add Role(s)**.

### Acrescentar os cargos de um escritório de coordenação novo

1. Digite `coordinator/<slug>/coordinator` e `coordinator/<slug>/sub-coordinator`, ou, para um escritório administrativo, `exec/<slug>/coordinator` e `exec/<slug>/staff`.
2. Clique em **Add Role(s)**.

## Bom saber

- Acrescentar um caminho acrescenta também os níveis acima dele: `chronicle/mckn/cm` cria também `chronicle` e `chronicle/mckn`.
- Os caminhos precisam combinar com o que os plugins procuram, letra por letra: `chronicle/mckn/hst`, não `Chronicles/MCKN/HST`.
- Apagar um cargo o tira de todos que o têm, na hora, e a tela não desfaz isso. Exporte antes.
- Outro plugin só pode dar a alguém um cargo que já está aqui.

## Solução de problemas

- **Um cargo não entra.** Ele já está registrado, ou o caminho é mais fundo que **Maximum Role Depth** em [Configurações do accessSchema](settings.md).
- **O C&C Manager salvou um HST novo mas ele não recebeu cargo.** Os cargos da crônica ainda não estavam registrados. Acrescente-os e salve o HST de novo em chronicles.owbn.net.

## Relacionados

- [Os cargos de um membro](user-roles.md)
- [Regras do accessSchema](rules.md)
- [Visão geral do accessSchema](../overview.md)
