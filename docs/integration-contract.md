# ChurchTools and Matrix integration contract

Verified against the tenant OpenAPI and public Matrix discovery on 2026-08-21.

## ChurchTools REST

Base URL: the hosted `https://<tenant>.church.tools` instance configured by the
administrator in the app's administration settings.

Authentication uses the documented header form:

```text
Authorization: Login <token>
```

Endpoints used by this app:

- `GET /api/whoami?only_allow_authenticated=true` validates the token and
  returns the current person's ID, GUID, display information and chat flags.
- `GET /api/chat` returns chat-domain metadata. The published schema contains
  `guid`, `prefix`, `domainId`, `roomname`, `creator`, and `status`.
- `GET /api/search?query=<query>&domain_types[]=person` searches visible
  ChurchTools people by name. Queries contain between 2 and 100 characters.
- `GET /api/persons?ids[]=<personId>&limit=1` resolves a selected search result
  again on the server and supplies the authoritative GUID and chat flags.

The ChurchTools `canChat` flag is treated as advisory because it may be false
for accounts that can still authenticate with the Matrix homeserver. The app
shows a warning but continues the Matrix login or direct-room operation. A
false `chatActive` flag for a selected recipient still prevents creating a new
conversation.

The OpenAPI also documents creating, changing and deleting chat metadata, and
starting/stopping group or event chats. This app does not invoke those mutating
operations in its initial release.

### Chat metadata (`GET /api/chat`)

Verified against the tenant OpenAPI (`/system/runtime/swagger/openapi.json`)
on 2026-08-25. Each entry is:

| Field | Type | Meaning |
|---|---|---|
| `creator` | `int`\|`null` | Person ID that created the chat |
| `domainId` | `int` | ChurchTools entity the chat belongs to (group, event, ...) |
| `guid` | `string` | Chat-specific GUID, e.g. `681F54E3-2EB7-40A4-84F0-EFF8E8F05727` |
| `prefix` | `string` | Chat key/prefix, e.g. `ctg` ("ct group") |
| `roomname` | `string`\|`null` | Display name, e.g. `Technik` |
| `status` | `string` | `NOT_STARTED` \| `STARTED` \| `STARTING` \| `STOPPED` |

OpenAPI example:

```json
{
  "creator": 1,
  "domainId": 9,
  "guid": "681F54E3-2EB7-40A4-84F0-EFF8E8F05727",
  "prefix": "ctg",
  "roomname": "Technik",
  "status": "STARTED"
}
```

Related endpoints:

- `POST /api/chat` creates chat metadata (`domainId`, `guid`, `prefix`, `roomname`).
- `PATCH /api/chat/{guid}` and `DELETE /api/chat/{guid}` update/remove it.
- `POST /groups/{groupId}/chat` and `POST /events/{eventId}/chat` start or stop a
  chat with `{ "enabled": bool, "triggerChatInviteMail": bool }`; the server
  generates the `guid`, `prefix` and `roomname` itself.

### CT chat -> Matrix room mapping (D5 spike)

The OpenAPI does not expose any Matrix room identifier, alias, or state event for
a chat. The exact room mapping therefore has to be verified empirically.

Working hypothesis (to confirm via `occ churchtools_chat:probe`): the Matrix room
is addressed through a canonical alias derived from the chat `prefix` and GUID,
analogous to the `@ct_<guid>` user-id derivation:

```text
#<prefix>_<lowercase-guid>:<matrix-server-name>
#ctg_681f54e3-2eb7-40a4-84f0-eff8e8f05727:chat.church.tools
```

The mapping may instead be carried by the room's `m.room.name` (`roomname`) or a
custom state event holding the `domainId`/`guid`. Until confirmed, the app must
not rely on a single formula.

The published OpenAPI does **not** expose message events or a Matrix
token-exchange/bootstrap endpoint. Those capabilities must not be invented from
private web endpoints.

## Matrix transport

Homeserver: configured by the administrator in the app's administration settings.
It defaults to `https://chat.church.tools`. The server name used in the derived
Matrix user ID is the host of the configured homeserver URL.

Public discovery currently advertises Matrix Client-Server API versions through
`v1.12` and supports `m.login.password`. Users supply only their CT Chat
password in the personal Nextcloud settings. The app takes the GUID from the
validated ChurchTools `/api/whoami` response, normalizes its hexadecimal
letters to lowercase, and derives the Matrix identifier as
`@ct_<lowercase-guid>:<matrix-server-name>`. It submits this value as an
`m.id.user` identifier to `POST /_matrix/client/v3/login`. The returned access
token is persisted through Nextcloud encryption; the password is never stored.

The gateway uses standard Matrix endpoints only:

- `GET /_matrix/client/v3/sync`
- `GET /_matrix/client/v3/rooms/{roomId}/messages`
- `POST /_matrix/client/v3/createRoom` with `is_direct: true`,
  `trusted_private_chat`, and the derived Matrix ID in `invite`
- `PUT /_matrix/client/v3/user/{userId}/account_data/m.direct`
- `PUT /_matrix/client/v3/rooms/{roomId}/send/m.room.message/{transactionId}`
- `PUT /_matrix/client/v3/rooms/{roomId}/send/m.reaction/{transactionId}`

End-to-end encrypted rooms are detected through `m.room.encryption` and are
shown as unsupported. The server does not attempt to decrypt or return encrypted
event payloads.
