<div align="center">

<img src="img/app.svg" alt="ChurchTools Chat" width="96" height="96">

# ChurchTools Chat for Nextcloud

**A native, Talk-like ChurchTools chat experience inside Nextcloud.**

[![Main verification](https://github.com/samuelspagl/nextcloud-churchtools-chat/actions/workflows/main-verification.yml/badge.svg)](https://github.com/samuelspagl/nextcloud-churchtools-chat/actions/workflows/main-verification.yml)
[![PR checks](https://github.com/samuelspagl/nextcloud-churchtools-chat/actions/workflows/pr-checks.yml/badge.svg)](https://github.com/samuelspagl/nextcloud-churchtools-chat/actions/workflows/pr-checks.yml)
[![Release](https://img.shields.io/github/v/release/samuelspagl/nextcloud-churchtools-chat?label=release)](https://github.com/samuelspagl/nextcloud-churchtools-chat/releases)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-33%20%E2%80%93%2034-0082C9?logo=nextcloud&logoColor=white)](https://nextcloud.com)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.1-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org)

</div>

> **⚠️ Experimental:** This app is still experimental. It may change, be
> incomplete, or behave unexpectedly. Use it with care and do not rely on it
> for critical workflows.

A chat-only Nextcloud app with a Talk-like Vue 3 interface, Markdown rendering,
Nextcloud Smart Picker support, and per-user ChurchTools credentials.
It can search visible ChurchTools people by name and start or reopen a Matrix
direct conversation for the selected person.
For group conversations, the chat inspector can resolve a uniquely named,
visible ChurchTools group and show its group details together with matching
Nextcloud Teams, linked folders, and Deck boards. Chats also support
reactions, `@` mentions with autocomplete, typing indicators, and read
receipts.

### ✨ Features

- 🔍 **Person search & direct conversations** — find visible ChurchTools people by name and start or reopen a Matrix direct conversation with them
- 💬 **Native Vue 3 interface** — a Talk-like chat experience built with maintained `@nextcloud/vue` components
- 📝 **Markdown rendering** — messages render Markdown, including inline replies shown as block quotes
- 📎 **Smart Picker support** — insert files, links, and other Nextcloud content directly into messages
- 👥 **Group context inspector** — resolves a uniquely named ChurchTools group and shows its details together with matching Nextcloud Teams, linked folders, and Deck boards
- 😀 **Reactions & mentions** — react to messages with emoji and mention people with autocomplete
- ⌨️ **Typing indicators & read receipts** — see when others are typing and whether direct messages have been read
- 🔐 **Per-user credentials** — every user connects their own ChurchTools account; no shared or admin-level credentials are required

## Table of contents

- [Current integration boundary](#current-integration-boundary)
- [Starting a direct chat](#starting-a-direct-chat)
- [Development](#development)
- [Releases](#releases)
- [Nextcloud App Store](#nextcloud-app-store)
- [Local Docker test stack](#local-docker-test-stack)
- [Security](#security)

## 🧭 Current integration boundary

The published ChurchTools OpenAPI exposes administrator-only chat metadata at
`/api/chat`, but no message transport or Matrix token-exchange endpoint. Reading
that endpoint requires the broad `administer persons` or `administer settings`
permission, so it is not part of normal user requests. The app validates and
stores each user's ChurchTools login token separately. To obtain a Matrix
session, users enter only their CT Chat password. The Matrix user ID is derived
from the GUID returned by `/api/whoami`: the GUID is normalized to lowercase and
formatted as `@ct_<guid>:<matrix-server-name>`, where the Matrix server name is
the host of the homeserver URL configured by the administrator. The derived ID
and password are sent once to `POST <matrix-homeserver>/_matrix/client/v3/login`;
the password is not persisted. Only the returned Matrix access token, user ID
and device ID are kept. The app never treats the API token as a Matrix password.

Matrix canonical aliases can identify a ChurchTools chat type and chat-specific
GUID, but they do not contain the numeric ChurchTools group or event ID. Future
group or event linking must use APIs available under the connected user's own
permissions and must not make `/api/chat` a runtime dependency.

The group inspector follows that boundary: it searches visible ChurchTools
groups by the Matrix room display name, accepts only one normalized exact
match, and uses the connected user's permissions for all group and member
details. Matching Nextcloud Teams and resources are likewise limited to the
current Nextcloud user's access.

## 💬 Starting a direct chat

The **New chat** action searches ChurchTools with
`GET /api/search?query=<name>&domain_types[]=person`. After selection, the
backend resolves the person again by ChurchTools person ID, derives the Matrix
ID from the returned GUID, reuses an existing `m.direct` room when possible,
or creates a private room and invites the selected person.

## 🛠️ Development

```sh
pnpm install
pnpm typecheck
pnpm test
pnpm build
```

Install the folder as `churchtools_chat` inside the Nextcloud apps directory,
enable it, configure the ChurchTools tenant and Matrix homeserver under
**Administration settings → Additional settings**, and have each user connect
their own account in **Personal settings → Additional settings**.

## 🚀 Releases

`appinfo/info.xml` is the release-version source of truth; `package.json` must
contain the same `major.minor.patch` version. Pull requests to `main` run the
frontend and PHP checks and are rejected when their version is not strictly
higher than both `main` and the latest `v*` tag. A successful merge to `main`
creates the corresponding GitHub Release and a `churchtools_chat` ZIP archive.

After the repository is pushed to GitHub, protect `main` and require the
**PR checks / Version gate**, **PR checks / Frontend**, and **PR checks / PHP**
status checks before merging. Allow GitHub Actions to create and approve pull
requests only if that matches the repository's governance policy; no additional
release secret is required for the built-in `GITHUB_TOKEN`.

## 🏪 Nextcloud App Store

After the Nextcloud certificate request for `churchtools_chat` is approved and
the app is registered in the App Store, create the protected GitHub environment
`release`. Add `APP_PRIVATE_KEY`, `APP_PUBLIC_CRT`, and `APPSTORE_TOKEN` as
environment secrets, then set the repository variable `APPSTORE_ENABLED` to
`true`. Publishing a GitHub Release will build and attach a
`churchtools_chat-<tag>.tar.gz` archive and submit it to the App Store after
environment approval. Use **Publish to Nextcloud App Store** with a tag to
publish an existing release manually.

## 🐳 Local Docker test stack

The included Compose stack runs Nextcloud 34 with PostgreSQL and Redis, mounts
the release-like `.build/churchtools_chat` staging directory as the local app,
and enables it during
Nextcloud startup. On the first start it also downloads and enables Deck, Talk,
Tables, and the OpenStreetMap integration. Together with the bundled Files and
Profile apps, these provide a useful dynamic Smart Picker test set. The first
startup therefore needs access to the Nextcloud App Store and can take longer.
Downloaded provider apps are kept in a dedicated `nextcloud_custom_apps`
volume; a one-shot init service gives Nextcloud's web-server user access to
that volume, while only the staged, installable ChurchTools Chat app is mounted
read-only inside it. Source files, tests, and Node dependencies are not exposed
to the container.

Build the frontend assets and start the stack:

```sh
corepack pnpm install --frozen-lockfile
pnpm build:docker
cp .env.example .env
docker compose up -d --wait
```

After changing the source, rebuild the staging directory and recreate the app
container so its bind mount is refreshed:

```sh
pnpm build:docker
docker compose up -d --force-recreate app
```

Open <http://localhost:8080> and sign in with `admin` / `admin`, unless those
development credentials were changed in `.env`. Then open **Administration
settings → Additional settings**, configure the ChurchTools tenant URL, and open
**Personal settings → Additional settings** to connect your ChurchTools account.

The development port binds to `127.0.0.1` by default and is therefore not
published to the local network.

The provider apps can be overridden in `.env` with a space-separated list:

```sh
CT_CHAT_SMART_PICKER_APPS=deck spreed tables integration_openstreetmap
```

Set `CT_CHAT_SMART_PICKER_APPS=` to skip all optional provider apps. Provider
entries are never hard-coded by ChurchTools Chat; the composer always uses the
Reference Providers registered in the current Nextcloud instance.

Useful commands:

```sh
docker compose logs -f app
docker compose exec -u www-data app php occ app:list
docker compose exec -u www-data app php occ app:enable churchtools_chat
docker compose down
```

`docker compose down` keeps the local Nextcloud and database volumes. To erase
the complete test instance, including its database and stored encrypted
credentials, use `docker compose down --volumes` deliberately.

The verified remote boundary is recorded in
[`docs/integration-contract.md`](docs/integration-contract.md).

## 🔐 Security

- Only hosted `https://*.church.tools` tenant URLs are accepted (configured by the administrator).
- The Matrix homeserver URL is configured by the administrator and defaults to `https://chat.church.tools`.
- ChurchTools and Matrix access tokens are encrypted through Nextcloud's crypto
  API; the CT Chat password is never stored.
- Secrets never appear in normal API responses, browser storage, or logs.
- The browser calls only authenticated Nextcloud app routes.
- Markdown rendering and Smart Picker links use maintained `@nextcloud/vue`
  components.
