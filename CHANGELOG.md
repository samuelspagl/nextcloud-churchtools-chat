# Changelog

All notable changes to ChurchTools Chat for Nextcloud are documented here.

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
