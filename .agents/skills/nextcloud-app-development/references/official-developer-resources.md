# Official Nextcloud developer resources

Use these official resources rather than relying on cached API knowledge.

## Core documentation

- [Developer Manual](https://docs.nextcloud.com/server/latest/developer_manual/):
  primary entry point for app development, framework concepts, development
  environments, testing, design, publishing, and maintenance.
- [App development](https://docs.nextcloud.com/server/latest/developer_manual/app_development/):
  app structure, metadata, bootstrapping, dependencies and CLI commands.
- [Basics](https://docs.nextcloud.com/server/latest/developer_manual/basics/):
  routes, dependency injection, controllers, events, translations, jobs,
  storage, settings, logging, caching and tests.
- [JavaScript APIs](https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/javascript-apis.html):
  the supported `@nextcloud/*` JavaScript packages; consult it before selecting
  an integration API.
- [Critical changes](https://docs.nextcloud.com/server/latest/developer_manual/release_notes/critical_changes.html):
  inspect for upgrades and when supporting more than one server release.

## Vue UI

- [@nextcloud/vue documentation](https://nextcloud-vue-components.netlify.app/)
  is the component, composable and design reference.
- [@nextcloud/vue source and compatibility table](https://github.com/nextcloud-libraries/nextcloud-vue)
  records which major versions support Vue 2 or Vue 3 and their Nextcloud
  server ranges. Check it before pinning a major version.
- Prefer direct imports, for example
  `@nextcloud/vue/components/NcButton`, when the package documents them; root
  imports can increase bundle size.

## Frontend package routing

| Need | Package |
| --- | --- |
| Shared UI components, layouts, navigation and composables | `@nextcloud/vue` |
| Authenticated calls to the Nextcloud server | `@nextcloud/axios` |
| App routes, assets and server URLs | `@nextcloud/router` |
| Translations and plurals | `@nextcloud/l10n` |
| Dialogs and notifications | `@nextcloud/dialogs` |
| Current user, session and CSP nonce | `@nextcloud/auth` |
| Bootstrap data rendered by the backend | `@nextcloud/initial-state` |
| Cross-app communication | `@nextcloud/event-bus` |
| File integration | `@nextcloud/files` |
| Sharing context and helpers | `@nextcloud/sharing` |

The Developer Manual is the compatibility authority for these packages. Avoid
new code based on globally injected `OC.*` / `OCA.*` APIs: the current
documentation explicitly describes them as legacy and notes removals in its
critical-change guides.
