# ChurchTools and Matrix integration contract

Verified against the tenant OpenAPI, generated authorization documentation, and
public Matrix discovery through 2026-08-26.

## ChurchTools REST

Base URL: the hosted `https://<tenant>.church.tools` instance configured by the
administrator in the app's administration settings.

Authentication uses the documented header form:

```text
Authorization: Login <token>
```

Endpoints used by normal user-facing requests:

- `GET /api/whoami?only_allow_authenticated=true` validates the token and
  returns the current person's ID, GUID, display information and chat flags.
- `GET /api/search?query=<query>&domain_types[]=person` searches visible
  ChurchTools people by name. Queries contain between 2 and 100 characters.
- `GET /api/persons?ids[]=<personId>&limit=1` resolves a selected search result
  again on the server and supplies the authoritative GUID and chat flags.
- `GET /api/search?query=<name>&domain_types[]=group` finds visible group
  candidates for the chat inspector. The app accepts only one normalized exact
  name match and never uses the room name as a ChurchTools identifier.
- `GET /api/groups?ids[]=<groupId>&limit=1`, the group's members endpoint, and
  the group type/category master-data endpoints provide the inspector details
  under the connected user's own permissions.

The ChurchTools `canChat` flag is treated as advisory because it may be false
for accounts that can still authenticate with the Matrix homeserver. The app
shows a warning but continues the Matrix login or direct-room operation. A
false `chatActive` flag for a selected recipient still prevents creating a new
conversation.

The OpenAPI also documents creating, changing and deleting chat metadata, and
starting/stopping group or event chats. This app does not invoke those mutating
operations in its initial release.

### Administrator-only chat metadata (`GET /api/chat`)

Verified against the tenant OpenAPI (`/system/runtime/swagger/openapi.json`)
and generated authorization documentation (`/system/runtime/authdoc.html`) on
2026-08-26. The operation is described as "Get all chats" and requires either
the `administer persons` or `administer settings` permission. It is therefore
not a personal chat-list endpoint and must not be called while loading rooms for
ordinary users. The `churchtools_chat:probe` command retains it solely as an
explicit administrator diagnostic. Each entry is:

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

### CT chat -> Matrix room mapping (D5 spike, confirmed)

The OpenAPI does not expose any Matrix room identifier, alias, or state event for
a chat. The mapping was confirmed empirically on 2026-08-25 via
`occ churchtools_chat:probe`: each candidate alias below resolved through the
Matrix room directory to a real room id.

The Matrix room for a ChurchTools chat is addressed by a canonical alias derived
from the chat `prefix` and GUID, analogous to the `@ct_<guid>` user-id derivation:

```text
#<prefix>_<lowercase-guid>:<matrix-server-name>
#cte_59049027-6296-47d5-9085-affc76ada326:chat.church.tools
  -> !WOPBtHRquRdJWSPHIM:chat.church.tools
```

Observed chat `prefix` values (determine the chat type, see D6):

| Prefix | Type |
|---|---|
| `cte` | event chat (`domainId` = event id, `roomname` = event title) |
| `ctg` | group chat (from the OpenAPI example; `domainId` = group id) |
| `cta` | announcement chat (expected; to be confirmed with live data) |

Other observations: a `creator` of `-4` marks chats created by the ChurchTools
system. A room alias can be resolved without joining when its chat metadata is
available to an authorized administrator.

### Group inspector matching and future event linking

The canonical Matrix alias exposes the chat prefix and chat-specific GUID. The
prefix can classify known chat types (`ctg` for groups and `cte` for events), but
the alias does not contain the numeric ChurchTools `domainId`. Matrix room IDs
are opaque, and room names are not unique, so neither may be used to infer a
ChurchTools group or event ID.

The group inspector searches visible groups through `/api/search`, normalizes
Unicode case and whitespace, and proceeds only when exactly one result has the
same name as the Matrix room. Zero results remain unlinked and duplicate names
are reported as ambiguous. This is deliberately a display-only convenience,
not a persistent identity mapping. It does not reintroduce `/api/chat` as a
normal runtime dependency.

Any future event linking or persistent group mapping must likewise use a
documented endpoint available under the connected user's own permissions and
handle invisible entities explicitly. `/api/chat` may only remain optional
administrator enrichment or diagnosis.

## Nextcloud Teams resources

Nextcloud 33 is the minimum supported release. The inspector uses the public
`OCP\Teams\ITeamManager` API to list teams for the current user, accepts exact
normalized display-name matches, and calls `getSharedWith()` for each match.
Only resources registered by Files or Deck providers are returned; other team
resource types and indirect relationships are ignored. Missing Deck support is
an empty resource list rather than a hard application dependency.

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

The initial room list is a snapshot built from one `/sync` response. Room-local
member events from its state and timeline are used first; a global `/profile`
lookup is only used when a direct-chat target or a necessary unnamed-room hero
lacks display metadata. The snapshot does not issue a `/members` request or a
`/messages?limit=1` backfill for every room. Its timeline window is the source
for `lastMessage`; rooms without a displayable event initially return
`lastMessage: null` and load their history when opened.

After applying that snapshot, the client starts incremental `/sync` requests in
the background using `next_batch`. A request with no new events may remain open
for the configured 20 seconds: this is intentional Matrix long polling and must
not delay rendering the initial room list or ending its loading state.

End-to-end encrypted rooms are detected through `m.room.encryption` and are
shown as unsupported. The server does not attempt to decrypt or return encrypted
event payloads.
