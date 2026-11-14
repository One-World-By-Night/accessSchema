# Visão geral do accessSchema

O accessSchema guarda os cargos que decidem o que cada membro da OWbN pode fazer em cada site da OWbN: HST de uma crônica, CM, coordenador, subcoordenador, a staff de um escritório. Ele roda em sso.owbn.net, e todos os outros sites perguntam a ele.

## O que ele faz

- **Cargos.** Cada cargo é um caminho, como `chronicle/mckn/hst` ou `coordinator/sabbat/coordinator`. O registro guarda todos os caminhos em uso, veja [O registro de cargos](help/roles.md).
- **Cargos dos membros.** A conta de cada membro em sso.owbn.net lista os cargos que ele tem, veja [Os cargos de um membro](help/user-roles.md).
- **Regras.** Uma regra pode barrar uma concessão que entraria em conflito com um cargo que alguém já tem, veja [Regras do accessSchema](help/rules.md).
- **A API.** Os outros sites da OWbN leem os cargos de um membro pelo OWBN Core, com uma chave de [Configurações do accessSchema](help/settings.md).
- **Concessões de outros plugins.** O C&C Manager e os outros plugins da OWbN dão e tiram cargos aqui quando alguém ocupa ou deixa um posto.

## Quem faz o quê

- **Os membros** só têm cargos; eles veem os resultados em todo site.
- **Os administradores do site de sso.owbn.net** cuidam do registro, dão e tiram cargos à mão e zelam pelas chaves.

## Por onde começar

- Uma crônica ou escritório novo: acrescente os cargos dele em [O registro de cargos](help/roles.md).
- O acesso de alguém está errado: [Os cargos de um membro](help/user-roles.md).
- Algo não funciona: [Solução de problemas do accessSchema](help/troubleshooting.md).

## Ajuda numa tela

Toda tela do accessSchema no wp-admin tem um **?** ao lado do título que abre a página de ajuda dela ali mesmo, e toda página está também aqui em support.owbn.net.
