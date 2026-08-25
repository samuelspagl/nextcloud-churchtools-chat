# Changelog

All notable changes to ChurchTools Chat for Nextcloud are documented here.

## [0.6.3]

### Fixed

- Allow logged-in non-admin users to connect their own ChurchTools and CT Chat
  accounts and use all personal chat routes as intended.

## [0.6.2]

### Added

- Reactions: choose any emoji from the picker in the message actions, toggle a
  reaction on the reaction chips, and highlight your own reactions. Your own
  reactions can be removed again (un-react).

## [0.6.1]

### Added

- Emoji picker in the composer, inserting the selected emoji at the cursor
  position.

## [0.6.0]

### Added

- D5 spike: the ChurchTools `/api/chat` contract is documented and parsed into a
  typed model, and the CT chat -> Matrix room mapping is confirmed
  (`#<prefix>_<lowercase-guid>:<server>`, resolvable via the room directory). A
  `churchtools_chat:probe` occ command dumps the raw chat metadata, the Matrix
  rooms, and the resolved aliases for verification.
- Emoji picker in the composer: a smiley button between the "+" menu and the text
  editor opens the Nextcloud emoji picker and inserts the selected emoji at the
  cursor position.
- Typing indicators: other participants' typing state is shown in the conversation
  list and as an animated indicator in the chat history, and the composer publishes
  the local typing state to Matrix while writing.
- Read receipts in direct conversations: the last own message read by the other
  participant is marked with a small check icon.
- Inline replies are now rendered as a block quote above the message, showing a
  two-line excerpt of the original message. The original is resolved from the loaded
  timeline or fetched on demand, the quote can jump back to the original message, and
  replies are detected even when only the Matrix rich-reply fallback is available.
- Matrix rich-reply fallback text (`> ` quoted lines) is stripped from reply bodies.

### Changed

- Date separators now follow the Talk style: a sticky pill header that stays visible
  while scrolling, with relative labels like "Today, March 18" or "Yesterday, March 17"
  that stay correct even when the app is open across midnight.
- Sender grouping now matches the Matrix / Talk convention: consecutive messages from
  the same sender on the same day within five minutes are grouped, hiding the avatar
  and author header.
- Grouped messages show their exact date and time in a tooltip on hover.

## [0.5.1]

### Fixed

- Fixed App Store release packaging failing with a `tar: stdout: write error` caused by a
  SIGPIPE race between `tar` and `grep -q` during archive verification.

## [0.5.0]

### Added

- Retry-safe message sending and a foundation for message relations.
- Resilient sync lifecycle and session-recovery UX.

### Changed

- Improved read state, pagination, scroll, and unread handling.
- Marked the app as experimental in settings, description, and README.

## [0.4.0]

### Changed

- Server configuration (ChurchTools tenant URL and Matrix homeserver URL) moved from the
  personal settings to the administrator settings. Administrators configure the servers once
  under **Administration settings → Additional settings**; users only provide their own
  ChurchTools access token and CT Chat password in **Personal settings → Additional settings**.

### Added

- Administrators can now configure the Matrix homeserver URL (defaults to the previous
  hardcoded `https://chat.church.tools`).

## [0.3.20]

### Fixed

- Trigger App Store publication after the successful GitHub release workflow,
  including releases created with GitHub's built-in token.

## [0.3.19]

### Added

- First signed release prepared for publication in the Nextcloud App Store.
- App Store metadata, release automation, and package validation.
