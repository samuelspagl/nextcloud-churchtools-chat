---
name: matrix-api-documentation
description: Work with the Matrix Client-Server API in this Nextcloud chat app, including endpoint semantics, event formats, sync, relations, media, and compatibility. Do not use for Matrix federation or server-server protocol work.
---

# Matrix Client-Server API Documentation

Use the [current stable Matrix Client-Server specification](https://spec.matrix.org/latest/client-server-api/) as the authority for API semantics and event schemas. Check `GET /_matrix/client/versions` on the target homeserver before depending on version-sensitive behavior or newer endpoints; do not infer support from the latest specification alone.

Before changing this integration, read [the integration contract](../../../docs/integration-contract.md) and inspect [MatrixClient](../../../lib/Service/MatrixClient.php). Keep the documented transport and the concrete implementation aligned.

## Repository integration rules

- Build Matrix requests from the administrator-configured homeserver base URL. The app uses Client-Server `v3` paths and authenticates protected endpoints with `Authorization: Bearer <access token>`.
- URL-encode Matrix identifiers when they are path parameters, including room IDs, event IDs, user IDs, and transaction IDs. Preserve the opaque values themselves; do not parse or reconstruct them.
- Treat `mxc://` URIs as Matrix media references, not browser URLs. Use the Matrix media repository endpoints and preserve the existing proxy's redirect, content-type, size, and streaming safeguards.
- End-to-end encrypted rooms (`m.room.encryption`) are unsupported in this app. Detect and present that boundary; do not claim to decrypt, inspect, or send encrypted payloads unless encryption support is deliberately added.

## Client behavior

- Use server-issued sync tokens as opaque cursors. Preserve `next_batch`, send it as `since`, and make long-poll timeouts and timeline limits explicit.
- Use unique client transaction IDs for event-sending `PUT` requests. Retry only with the same transaction ID when idempotency is required; never create a new ID for an uncertain send result.
- Model replies with `m.in_reply_to`, edits with `m.replace` plus `m.new_content`, and reactions with `m.annotation`. Follow the specification for event content and account for server-provided bundled relations as well as locally received events.
- Send receipts only after the user has actually viewed an event. Keep read markers, receipts, and typing state separate because their semantics and lifetimes differ.
- Map Matrix error responses deliberately. In particular, treat authentication failures as an expired/revoked session, honor rate-limit retry guidance (`M_LIMIT_EXCEEDED`, retry timing, and `Retry-After` where present), and avoid exposing raw upstream errors to users.

## Scope

This skill covers the Matrix Client-Server API only. For Federation, signing, server-server endpoints, or homeserver administration, consult the applicable Matrix specification separately rather than extending this guidance.
