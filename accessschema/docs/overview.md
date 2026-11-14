# accessSchema Overview

accessSchema keeps the roles that decide what each OWbN member can do on every OWbN site: HST of a chronicle, CM, coordinator, sub-coordinator, an office's staff. It runs on sso.owbn.net, and every other site asks it.

## What it does

- **Roles.** Each role is a path, such as `chronicle/mckn/hst` or `coordinator/sabbat/coordinator`. The registry holds every path in use, see [The Role Registry](help/roles.md).
- **Members' roles.** Each member's account on sso.owbn.net lists the roles they hold, see [A Member's Roles](help/user-roles.md).
- **Rules.** A rule can stop a grant that would clash with a role someone already holds, see [accessSchema Rules](help/rules.md).
- **The API.** Other OWbN sites read a member's roles through OWBN Core, with a key from [accessSchema Settings](help/settings.md).
- **Grants from other plugins.** C&C Manager and the other OWbN plugins give and take roles here when someone fills or leaves a seat.

## Who does what

- **Members** just hold roles; they see the results on every site.
- **Site admins of sso.owbn.net** keep the registry, give and remove roles by hand, and look after the keys.

## Where to start

- A new chronicle or office: add its roles in [The Role Registry](help/roles.md).
- Someone's access is wrong: [A Member's Roles](help/user-roles.md).
- Something isn't working: [accessSchema Troubleshooting](help/troubleshooting.md).

## Getting help on a screen

Every accessSchema screen in wp-admin has a **?** next to its title that opens its help page right there, and every page is also here on support.owbn.net.
