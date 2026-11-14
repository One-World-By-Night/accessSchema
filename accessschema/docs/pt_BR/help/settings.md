# Configurações do accessSchema

As chaves que os outros sites da OWbN usam para ler e mudar cargos, o registro de auditoria e o cache.

## Quem pode usar

Administradores do site de sso.owbn.net.

## Como chegar

wp-admin em sso.owbn.net → **Settings** → **Access Schema** (a tela se chama **Access Schema Settings**).

## A tela

- **API Settings**
  - **API URL** - o endereço que os outros sites usam, com **Copy**.
  - **Read-Only API Key** - deixa um site ler cargos.
  - **Read-Write API Key** - deixa um site dar e tirar cargos também.
  - **Generate New** faz uma chave nova.
- **Logging Settings** - **Log Level**, **Enable Audit Logging** (toda mudança e conferência de cargo) e **Audit Log Retention** em dias.
- **Performance Settings** - **Cache TTL** em segundos, e **Maximum Role Depth**.
- **Advanced Settings** - **Enable REST API**, de que os outros sites precisam, e **Remove Data on Uninstall**.
- **Maintenance** - **Clear All Caches**, **Export Settings** e **Debug Information**, com as contagens de cargos, concessões e entradas do registro.

## Tarefas comuns

### Ligar outro site da OWbN

1. Clique em **Copy** ao lado de **API URL**.
2. Dê ao administrador desse site o endereço e a chave certa: somente leitura se ele só confere cargos, de leitura e escrita se ele os dá, como o C&C Manager faz.
3. Ele digita os dois em **OWBN Core** → **Settings** → **accessSchema** no site dele.

## Bom saber

- **Generate New** corta, de uma vez, todo site que usa a chave antiga, até cada um receber a nova. Mude uma chave só quando ela vazou.
- Deixe **Enable REST API** ligado: sem ele nenhum outro site confere os cargos de ninguém.
- Deixe **Remove Data on Uninstall** desligado.

## Solução de problemas

- **Os outros sites pararam de ver cargos.** **Enable REST API** está desligado, ou uma chave foi refeita. Confira os dois e dê aos sites a chave atual.
- **Uma mudança de cargo não aparece aqui.** Clique em **Clear All Caches**.

## Relacionados

- [O registro de cargos](roles.md)
- [Solução de problemas do accessSchema](troubleshooting.md)
- [Visão geral do accessSchema](../overview.md)
