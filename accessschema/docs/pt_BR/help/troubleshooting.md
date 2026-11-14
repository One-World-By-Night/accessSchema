# Solução de problemas do accessSchema

Soluções para o que mais dá errado com os cargos: alguém sem o acesso que deveria ter, cargos que não chegam aos outros sites e concessões que falham.

## Quem pode usar

Administradores do site de sso.owbn.net.

## Como chegar

A maioria das soluções começa em wp-admin em sso.owbn.net → **Users**.

## A tela

A maioria das soluções usa a conta do próprio membro, veja [Os cargos de um membro](user-roles.md), e [O registro de cargos](roles.md).

## Tarefas comuns

### Conferir o que alguém tem

1. Abra a conta dele em sso.owbn.net e leia **Assigned Roles**.
2. Compare cada caminho, letra por letra, com o que o plugin procura, como `chronicle/mckn/hst`.

### Fazer outro site ver uma mudança

1. Peça ao membro que saia desse site e entre de novo.

## Bom saber

- Os outros sites guardam os cargos de um membro por um tempo e os leem de novo quando o membro entra.
- Uma concessão de outro plugin falha sem mensagem quando o cargo não está registrado ou uma regra a recusa.

## Solução de problemas

- **Alguém tem um posto no C&C Manager mas nenhum cargo.** O cargo não estava registrado quando a pessoa foi salva, uma regra o recusou, ou ela foi salva sem conta. Registre o cargo e salve o posto de novo em chronicles.owbn.net.
- **Um membro tem o cargo mas um site ainda diz não.** Esse site tem os cargos antigos dele. Ele sai e entra de novo lá.
- **Todos os outros sites perderam os cargos de todo mundo de uma vez.** Uma chave foi refeita ou a REST API foi desligada, veja [Configurações do accessSchema](settings.md).
- **"Role assignment blocked by rules:"** Veja [Regras do accessSchema](rules.md).
- **Um cargo sumiu para muita gente.** Um cargo, ou um acima dele, foi apagado do registro. Acrescente-o de novo e devolva-o a cada pessoa.

## Relacionados

- [Os cargos de um membro](user-roles.md)
- [O registro de cargos](roles.md)
- [Configurações do accessSchema](settings.md)
- [Visão geral do accessSchema](../overview.md)
