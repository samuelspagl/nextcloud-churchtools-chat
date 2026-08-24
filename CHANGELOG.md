# Changelog

All notable changes to ChurchTools Chat for Nextcloud are documented here.

## [0.6.0]

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
