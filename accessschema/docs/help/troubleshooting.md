# accessSchema Troubleshooting

Fixes for what goes wrong most with roles: someone without the access they should have, roles that don't reach other sites, and grants that fail.

## Who can use this

Site admins of sso.owbn.net.

## How to get there

Most fixes start from wp-admin on sso.owbn.net → **Users**.

## The screen

Most fixes use the member's own account, see [A Member's Roles](user-roles.md), and [The Role Registry](roles.md).

## Common tasks

### Check what someone holds

1. Open their account on sso.owbn.net and read **Assigned Roles**.
2. Compare each path, letter for letter, with what the plugin looks for, such as `chronicle/mckn/hst`.

### Make another site see a change

1. Have the member sign out of that site and sign in again.

## Things to know

- Other sites keep a member's roles for a while and re-read them when the member signs in.
- A grant from another plugin fails without a message when the role isn't registered or a rule refuses it.

## Troubleshooting

- **Someone has a seat in C&C Manager but no role.** The role wasn't registered when they were saved, a rule refused it, or they were saved without an account. Register the role and save the seat again on chronicles.owbn.net.
- **A member holds the role but a site still says no.** That site has their old roles. They sign out and in again there.
- **Every other site lost everyone's roles at once.** A key was regenerated or the REST API was switched off, see [accessSchema Settings](settings.md).
- **"Role assignment blocked by rules:"** See [accessSchema Rules](rules.md).
- **A role disappeared for many people.** A role, or one above it, was deleted from the registry. Re-add it and give it back to each person.

## Related

- [A Member's Roles](user-roles.md)
- [The Role Registry](roles.md)
- [accessSchema Settings](settings.md)
- [accessSchema Overview](../overview.md)
