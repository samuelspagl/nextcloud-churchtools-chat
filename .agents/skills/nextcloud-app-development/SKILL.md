---
name: nextcloud-app-development
description: Build or update Nextcloud server apps using official APIs, PHP app-framework patterns, and the @nextcloud Vue UI libraries. Use for Nextcloud app backends, Vue frontends, integrations, packaging, compatibility, and tests.
---

# Nextcloud App Development

Build maintainable Nextcloud apps against the supported server version(s) of the
target instance. Treat the official Developer Manual as the primary authority;
check its release notes and critical changes before choosing APIs that can be
version-sensitive.

## App architecture

- Keep the PHP backend in the Nextcloud app framework: explicit routes,
  controllers, dependency injection, services, database migrations, background
  jobs, logging, translations, and tests as documented.
- Use public `OCP` APIs. Do not introduce new dependencies on private `OC` or
  `OCA` globals. If existing code uses one, confirm it remains supported for
  the app's declared Nextcloud range before retaining it.
- Declare the app's supported Nextcloud range in `appinfo/info.xml` and align
  dependency versions and implementation choices to that range.

## Front end

- Prefer a Vue app mounted from a minimal template. Use `@nextcloud/vue` for
  the application shell, navigation, forms, lists, dialogs, accessibility and
  Nextcloud-consistent styling. Import components/composables by their direct
  paths when practical to avoid unnecessary bundle size.
- Match `@nextcloud/vue` to the target server: v9 targets Nextcloud 31+ with
  Vue 3; v8 targets Nextcloud 28+ with Vue 2. Verify the current package and
  compatibility table before adding or upgrading it.
- Use the modular packages from the official JavaScript APIs rather than legacy
  globals: `@nextcloud/axios` for authenticated HTTP, `@nextcloud/router` for
  URLs, `@nextcloud/l10n` for translations, `@nextcloud/dialogs` for dialogs
  and notifications, `@nextcloud/auth` for session/CSP helpers, and
  `@nextcloud/initial-state`, `@nextcloud/event-bus`, `@nextcloud/files`, or
  `@nextcloud/sharing` only when the feature needs them.
- Use Nextcloud design tokens and logical CSS properties so themes and RTL
  layouts work. Keep user-facing strings translatable.

## Validation

- Follow the repository's declared Node/npm versions and build scripts. Build
  the production assets before handoff when JavaScript or styles change.
- Run the relevant PHP and JavaScript checks where available. Do not claim
  compatibility solely from a successful local build; check the documented
  compatibility and breaking-change notes for the declared server range.

## References

- Read [official-developer-resources.md](references/official-developer-resources.md)
  for authoritative links and when to use each frontend package.
