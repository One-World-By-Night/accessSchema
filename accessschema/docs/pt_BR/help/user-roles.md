# Os cargos de um membro

Os cargos que um membro tem, na conta dele em sso.owbn.net, e como achar quem tem o quê.

## Quem pode usar

Administradores do site de sso.owbn.net.

## Como chegar

wp-admin em sso.owbn.net → **Users** → clique no membro → **Access Schema Roles**, perto do fim.

## A tela

**Na conta de um membro**

- **Assigned Roles** - todos os cargos que ele tem, cada um com um botão de remover.
- **Add Roles** - digite para procurar no registro, e escolha um ou mais cargos.
- **Role Information** - **View role hierarchy** mostra onde os cargos dele ficam.
- Uma concessão que uma regra recusa mostra "Role assignment blocked by rules:" com o motivo.

**Na lista Users**

- **ASC Roles** - os cargos de cada membro.
- **All ASC Roles** - mostra só os membros que têm um cargo, ou **— No ASC Roles —** para os que não têm nenhum.
- **Role pattern** - mostra os membros que têm qualquer cargo que combine, como `chronicle/*/hst` para todos os HSTs.

## Tarefas comuns

### Dar um cargo a alguém

1. Abra a conta e role até **Access Schema Roles**.
2. Em **Add Roles**, escolha o cargo.
3. Clique em **Update User**.

### Tirar um cargo

1. Clique no botão de remover ao lado do cargo em **Assigned Roles**.
2. Clique em **Update User**.

### Achar todos que têm um cargo

1. Em **Users**, digite um padrão em **Role pattern**, como `coordinator/*/coordinator`.
2. Clique em **Filter**.

## Bom saber

- Mude a staff de crônicas e coordenações pelo C&C Manager sempre que der: ele mantém a ficha e os cargos juntos. Um cargo mudado só aqui deixa a ficha dizendo outra coisa.
- Os outros sites guardam os cargos de um membro por um tempo. O membro recebe a mudança saindo e entrando de novo.
- Tirar um cargo aqui não se desfaz por esta tela; devolva-o do mesmo jeito.

## Solução de problemas

- **"Role assignment blocked by rules:"** O membro tem um cargo que uma regra diz que entra em conflito com este. Veja [Regras do accessSchema](rules.md).
- **O cargo não está em Add Roles.** Ele não está registrado. Acrescente-o antes em [O registro de cargos](roles.md).
- **O membro ainda não consegue fazer algo em outro site.** Peça que saia e entre de novo lá.

## Relacionados

- [O registro de cargos](roles.md)
- [Regras do accessSchema](rules.md)
- [Solução de problemas do accessSchema](troubleshooting.md)
