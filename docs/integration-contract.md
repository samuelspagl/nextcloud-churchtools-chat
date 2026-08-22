# ChurchTools and Matrix integration contract

Verified against the tenant OpenAPI and public Matrix discovery on 2026-08-21.

## ChurchTools REST

Base URL: the user-configured hosted `https://<tenant>.church.tools` instance.

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

The published OpenAPI does **not** expose message events or a Matrix
token-exchange/bootstrap endpoint. Those capabilities must not be invented from
private web endpoints.

## Matrix transport

Homeserver: `https://chat.church.tools`.

Public discovery currently advertises Matrix Client-Server API versions through
`v1.12` and supports `m.login.password`. Users supply only their CT Chat
password in the personal Nextcloud settings. The app takes the GUID from the
validated ChurchTools `/api/whoami` response, normalizes its hexadecimal
letters to lowercase, and derives the Matrix identifier as
`@ct_<lowercase-guid>:chat.church.tools`. It submits this value as an
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
