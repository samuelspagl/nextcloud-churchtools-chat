# Roadmap — ChurchTools Chat for Nextcloud

> **Product focus:** A high-quality ChurchTools / Matrix chat experience inside Nextcloud.
>
> The app should **not** evolve into a general-purpose ChurchTools ↔ Nextcloud integration platform in the near term.
> ChurchTools and Nextcloud features belong in the current roadmap when they directly improve Chat,
> provide useful context for a conversation, or make Chat feel native inside Nextcloud.
>
> Broader integration ideas are intentionally kept for later, but are **not the current focus**.

Current version: **0.4.0**  
Supported: **Nextcloud 31–34**, **PHP ≥ 8.1**

---

## 1. Product principles

The three systems should keep clearly separated responsibilities:

| System | Responsibility |
|---|---|
| **Matrix** | Messages, timeline state, reactions, edits, read state, typing, media, and other chat state |
| **ChurchTools** | Identity, people, groups, events, roles, permissions/context, and ChurchTools chat lifecycle |
| **Nextcloud** | Host UI, secure server-side integration, files, search, dashboard, and notifications |

Guiding principle:

> **Context, not duplication.**

The app should avoid recreating data stores for information that already has a clear source of truth in ChurchTools or Matrix.

For new features, the following rule applies:

> A feature belongs in the current ChurchTools Chat roadmap if its primary purpose is to improve
> a ChurchTools / Matrix conversation or connect that conversation meaningfully to Nextcloud.

---

## 2. Current state

### 2.1 Release and App Store preparation
Starting with version `0.3.21`, the project includes:

- GitHub Actions and release workflows
- version checks for releases
- Nextcloud App Store publishing preparation
- generic tenant examples and project metadata

### 2.2 Central server configuration
Starting with `0.4.0`, server-wide settings are managed by administrators:

- ChurchTools tenant URL
- Matrix homeserver URL
- Matrix default: `https://chat.church.tools`

Personal settings continue to contain user-specific credentials only.

### 2.3 Dedicated settings section
The app has dedicated sections for:

- personal settings
- administration settings

### 2.4 Existing architectural boundary
The published ChurchTools API provides identity and chat metadata, but not message transport
and no documented Matrix token exchange.

The current integration model therefore is:

1. ChurchTools authenticates / identifies the user.
2. The Matrix user ID is derived from the ChurchTools GUID.
3. Messages are transported through the standard Matrix Client-Server API.
4. ChurchTools and Matrix tokens are protected server-side through Nextcloud.
5. The browser communicates only with authenticated Nextcloud app endpoints.

**E2EE remains out of scope.**

ChurchTools also continues to manage group memberships and the lifecycle of
group- and event-related chats. The Nextcloud app should not take ownership of these responsibilities.

---

# 3. Roadmap

## P0 — Chat foundation and reliability

Goal: Before adding more visible functionality, the Matrix integration must be robust and retry-safe.

| ID | Feature | Description | Effort |
|---|---|---|---|
| **M1** | Robust sync lifecycle | Handle reconnects, duplicate events, expired sessions, transient failures, and `429` responses correctly | M |
| **M2** | Retry-safe sends | Stable transaction IDs and idempotent retries for failed sends | S/M |
| **M3** | Relations foundation | Shared internal handling for replies, edits, reactions, and later threads | M |
| **M4** | Session recovery | Clear UX for revoked or expired Matrix sessions | S/M |
| **A1** | Read state & unread handling | Use `m.fully_read` plus Matrix receipts and reset unread counts correctly | M |
| **A2** | Message pagination | Load older messages through `/rooms/{id}/messages` | M |
| **A3** | Scroll state | Preserve scroll position and only auto-scroll when the user is already near the bottom | S |

### A1 — Details

Read state should not be treated as a purely local “reset unread” mechanism.

Planned:

- `m.fully_read`
- optionally `m.read`
- alternatively `m.read.private` if visible read receipts should be disabled
- process receipt events from sync
- derive unread / highlight counts consistently from Matrix state

A later “Mark as unread” feature can build on this foundation.

---

## P1 — Modern day-to-day chat

Goal: The app should feel like a complete modern messenger in daily use.

### Message rendering and interaction

| ID | Feature | Description | Effort |
|---|---|---|---|
| **B1** | Replies | `m.in_reply_to` with quote/context rendering | M |
| **B2** | Date separators | Visible day transitions in the timeline | S |
| **B3** | Sender grouping | Group consecutive messages from the same sender | S |
| **B4** | Reactions | `m.annotation`; track own reaction event IDs for un-react | M |
| **B5** | Edit message | `m.replace`, `m.new_content`; initially own text messages only | M |
| **B6** | Delete message | Matrix redaction | M |
| **B7** | Emoji picker | Use the appropriate Nextcloud Vue component | S |
| **B8** | Typing indicators | Send and display Matrix typing state | M |
| **B9** | Read receipts | Initially most useful for DMs | M |
| **B10** | Mentions | Rendering, highlight state, and later autocomplete | M |
| **B11** | Message search | Start with current-room search; reuse later for global search | M |

### Recommended order within P1

1. B2, B3
2. B1
3. B4, B5, B6
4. B7
5. B8, B9
6. B10
7. B11

The relations foundation from M3 should be implemented before B1/B4/B5 so replies,
reactions, and edits are not implemented as unrelated special cases.

---

## P1/P2 — ChurchTools-specific chat context

Goal: This is where the app should become more than a generic Matrix client.

ChurchTools data should not be mirrored broadly into Nextcloud. It should be used where
it directly explains or improves a conversation.

### D — ChurchTools Chat Context

| ID | Feature | Description | Effort |
|---|---|---|---|
| **D1** | Person deep link | “Open in ChurchTools” for DMs; verify exact URL format live | S |
| **D2** | Group memberships in DM | Load relevant visible groups for the person | M |
| **D3** | Shared groups | Highlight the intersection between my groups and the other person's groups | M |
| **D4** | Role / leader context | Show contextual group roles and possibly `job` | M |
| **D5** | CT chat ↔ Matrix room mapping spike | Determine exactly how `/api/chat` maps to Matrix rooms | M |
| **D6** | Detect chat type | DM, group, event, announcement | M |
| **D7** | Group / event context | Show relevant ChurchTools information directly around the room | M |
| **D8** | Send permissions | Proactively enable/disable the composer based on Matrix power levels | M |
| **D9** | List CT group / event chats | Show mapped ChurchTools chats as normal rooms in the client | M/L |

### D5/D9 should have higher priority than before

ChurchTools Chat is not only about direct messages. Group chats, event chats, and
announcement chats are core parts of the product.

The mapping spike should therefore happen **soon after the chat foundation**
rather than near the end of the roadmap.

### Conversation context by room type

#### DM

Possible information:

- name / avatar
- open ChurchTools profile
- `job`
- shared groups
- relevant role in shared groups

#### Group chat

Possible information:

- ChurchTools group name
- own role
- leader status
- open group in ChurchTools

#### Event chat

Possible information:

- event title
- date / time
- location, if available and useful
- open event in ChurchTools

#### Announcement chat

Additionally show clearly:

- that this is an announcement chat
- whether the user may send messages
- disable the composer if Matrix power levels prohibit sending

### Privacy

Do not expose in chat:

- private phone numbers
- private email addresses
- addresses
- birthdays

ChurchTools remains the primary interface for detailed person data.

---

## P2 — Files and media

Goal: Full chat attachments plus a clear Nextcloud-specific advantage.

### C — Matrix Media

| ID | Feature | Description | Effort |
|---|---|---|---|
| **C1** | Upload images / files | Matrix media upload and `m.image` / `m.file` / `m.audio` / `m.video` | L |
| **C2** | Preview & download | Protected backend path similar to the existing avatar pattern | M |
| **C3** | Streaming backend proxy | Browser → Nextcloud/PHP → Matrix; avoid buffering full files in memory | M/L |
| **C4** | Drag & drop / paste | Drop files or paste images directly into the composer | M |
| **C5** | Send Nextcloud file to chat | Select a file from Nextcloud Files inside the composer | M |

### Upload limits

Do not hardcode a 50 MB limit.

The effective limit should be derived from:

- Matrix `m.upload.size`
- PHP `upload_max_filesize`
- PHP `post_max_size`
- reverse-proxy limits
- optional app safety limit

### Nextcloud files

C5 is intentionally an early, chat-focused Nextcloud integration.

Possible composer actions:

- **Upload from device**
- **Choose from Nextcloud Files**

Later, this can be extended with a Files context action such as “Send to ChurchTools Chat”.

A Nextcloud file must never be silently shared publicly.
If a share link is required, the consequence must be transparent to the user and
must respect Nextcloud administrator policies.

---

## P2 — Nextcloud integration with direct chat value

Goal: Chat should feel like a native part of Nextcloud without expanding into a
general ChurchTools integration platform.

### E — Nextcloud Chat Integration

| ID | Feature | Scope | Effort |
|---|---|---|---|
| **E1** | Unified Search | Rooms, messages, and ChurchTools people as “Start chat” results | M |
| **E2** | Dashboard widget | Unread rooms, mentions, recent conversations | M |
| **E3** | Nextcloud notifications | DMs, mentions, replies, and relevant announcements | L |
| **E4** | Start chat from Nextcloud person context | Only where a verified CT↔NC identity mapping exists | M |

### E1 — Unified Search

Current scope:

- chat rooms
- chat messages
- ChurchTools people with a **“Start chat”** action

Not part of the current scope:

- generic ChurchTools group search
- generic ChurchTools event search
- arbitrary ChurchTools resources without a chat relationship

### E2 — Dashboard

Chat-centric, for example:

- unread rooms
- mentions
- recent active chats
- quick action: “New chat”

No general ChurchTools event/group dashboard in this phase.

### E3 — Notifications

Before implementing polling, run a spike to determine whether the ChurchTools Matrix server
allows standard Matrix HTTP Pushers to call a Nextcloud endpoint.

Preferred architecture, if supported:

```text
Matrix Homeserver
    ↓ HTTP Pusher
Nextcloud ChurchTools Chat endpoint
    ↓
Nextcloud Notification API
```

Fallback:

- periodic Nextcloud background job
- preferably delta-based and lightweight
- do not poll the full timeline for every user

---

# 4. P3 — Additional chat features

These features are useful, but should not delay the core roadmap.

| ID | Feature | Notes |
|---|---|---|
| **B12** | Mark as unread | Build on Matrix `m.marked_unread` |
| **B13** | Threads | Render incoming first; sending only after CT compatibility verification |
| **B14** | Voice messages | Verify interoperability with ChurchTools / Element |
| **B15** | Pinned messages | After core features |
| **B16** | Poll compatibility | First observe official ChurchTools Matrix events |
| **B17** | Notification preferences | e.g. all messages / mentions / muted |

---

# 5. Interoperability spikes

For chat features introduced by ChurchTools itself, use the following rule:

> First observe which Matrix events the official ChurchTools / Element client emits.
> Only then decide whether the Nextcloud app should send and/or render them.

This avoids incompatible custom formats.

## Current spikes

1. **ChurchTools person deep link**
2. **CT chat → Matrix room mapping**
3. **Group / event / announcement chat typing**
4. **Role information from `/api/persons/{id}/groups`**
5. **Matrix HTTP Pusher → Nextcloud Notifications**
6. **ChurchTools poll events**
7. **Threads / voice / pins only when needed**

---

# 6. Explicitly outside the current focus

The following ideas are interesting and should not be forgotten, but they are **not**
part of the current product priority.

## Future ecosystem opportunities

- generic ChurchTools Smart Picker for people, groups, and events
- general ChurchTools link previews across Nextcloud
- generic ChurchTools events/groups on the Nextcloud Dashboard
- broad ChurchTools profile integration
- Nextcloud Projects ↔ ChurchTools resources
- generic ChurchTools calendar/workflow integration
- broader ChurchTools ↔ Nextcloud synchronization

Simple foundations may already be considered today if they:

1. do not unnecessarily complicate the current chat architecture,
2. do not introduce a new source of truth,
3. do not create significant additional maintenance burden.

---

# 7. Not planned

The following are explicitly not planned at this stage:

- E2EE support
- custom group / membership management
- Talk ↔ Matrix message bridge
- copying Matrix history into a separate Nextcloud database
- automatic identity matching by name or email
- replacing the ChurchTools UI in general
- implementing every Element Web feature only because Matrix supports it
- silently creating public Nextcloud shares

---

# 8. Recommended implementation order

## Phase 0 — Reliability

1. M1 — Sync lifecycle
2. M2 — Retry-safe sends
3. M3 — Relations foundation
4. M4 — Session recovery
5. A1 — Read state / unread
6. A2 — Pagination
7. A3 — Scroll state

## Phase 1 — Core Chat

1. B2 — Date separators
2. B3 — Sender grouping
3. B1 — Replies
4. B4 — Reactions
5. B5 — Edit
6. B6 — Delete
7. B7 — Emoji picker
8. B8 — Typing
9. B9 — Read receipts
10. B10 — Mentions
11. B11 — Search

## Phase 2 — ChurchTools Chat

1. D5 — Mapping spike
2. D6 — Chat type detection
3. D9 — Group / event chats
4. D8 — Permission UX
5. D1–D4 — DM context
6. D7 — Group / event context

## Phase 3 — Media

1. C1 — Upload
2. C2 — Preview / download
3. C3 — Streaming proxy
4. C4 — Drag & drop / paste
5. C5 — Choose from Nextcloud Files

## Phase 4 — Native Nextcloud Chat Integration

1. E1 — Unified Search
2. E3 — Notifications
3. E2 — Dashboard
4. E4 — Start chat from verified person context

## Phase 5 — Nice-to-have / Interoperability

- B12 — Mark unread
- B13 — Threads
- B14 — Voice messages
- B15 — Pins
- B16 — Polls
- B17 — Notification preferences

---

# 9. Target vision

Short term:

> **A very good Matrix / ChurchTools chat app inside Nextcloud.**

Then:

> **ChurchTools explains the conversation: Who is involved, which group or event does it belong to, and what role do I have there?**

Then:

> **Nextcloud makes the conversation more useful: Files, search, notifications, and dashboard integration become seamless.**

Only later:

> **Broader ChurchTools ↔ Nextcloud integration beyond Chat.**

This keeps the scope controlled while leaving the architecture open for later expansion.

