# Regras do accessSchema

Regras que recusam um cargo quando o membro já tem um que entra em conflito com ele.

## Quem pode usar

Administradores do site de sso.owbn.net.

## Como chegar

wp-admin em sso.owbn.net → **Users** → **accessSchema Rules** (a tela se chama **Access Schema Rules**).

## A tela

- **Add Rule**
  - **Type** - **Exclusion**: se recebe A, não pode já ter B.
  - **Trigger Pattern** - o cargo que está sendo dado. A regra só dispara quando ele combina.
  - **Conflict Pattern** - o cargo que o barra. Se o membro já tem um que combine, a concessão é recusada.
  - **Description** - por que a regra existe, em palavras simples.
- **Active Rules** - o **ID**, o **Trigger**, o **Conflict** de cada regra e se ela está **Active**, com **Disable** ou **Enable**, e **Delete**.

## Tarefas comuns

### Impedir que uma pessoa tenha dois postos em conflito

1. Em **Add Rule**, ponha em **Trigger Pattern** o posto que está sendo dado, como `coordinator/*/coordinator`.
2. Ponha em **Conflict Pattern** o posto que entra em conflito com ele.
3. Escreva o motivo em **Description** e acrescente a regra.

### Pausar uma regra

1. Clique em **Disable** ao lado dela. **Enable** a liga de novo.

## Bom saber

- As regras são conferidas só quando um cargo é dado, nunca quando um é tirado, e nunca contra cargos que já estão em mãos.
- Num padrão, `*` vale por uma parte qualquer e `**` por qualquer número de partes.
- Uma concessão recusada vinda de outro plugin, como o C&C Manager, não dá cargo à pessoa; a ficha do plugin ainda pode listá-la.

## Solução de problemas

- **Uma concessão foi recusada e não devia.** Ache a regra cujos padrões combinam, e desligue-a ou estreite-a.
- **Uma regra nunca dispara.** O **Trigger Pattern** dela não combina com o cargo que está sendo dado; confira as partes e os curingas.

## Relacionados

- [Os cargos de um membro](user-roles.md)
- [O registro de cargos](roles.md)
