# MuxMate Changelog

## 3.3.1 - 2026-08-19
### Fixed 
- Fixed a bug where Mux videos would not play

## 3.3.0 - 2026-07-03
> [!IMPORTANT]
> This release contains a breaking change: incoming Mux webhooks are now rejected unless the new `muxWebhookSigningSecret` config setting is set to your webhook's signing secret. Webhooks will not work until this is configured.

### Added
- Added support for passing `min_resolution` to `getMuxStreamUrl()` and `getMuxVideo()`
- Added the `muxWebhookSigningSecret` config setting, used to verify the signature of incoming Mux webhooks

### Fixed
- Fixed a bug where signed URL tokens could be corrupted when multiple of them appeared close together (without an intervening line break) in cached template output

### Security
- Incoming Mux webhooks are now verified against the `Mux-Signature` header before being processed; webhooks with a missing or invalid signature are rejected. This requires the new `muxWebhookSigningSecret` config setting to be set
- Fixed an issue where the `_muxmate/default` controller actions (create, update and delete Mux assets) did not check control panel access or asset permissions, allowing any logged-in user to trigger them for arbitrary assets

## 3.2.0 - 2026-02-25
### Changed
- Bumped the requirement for `firebase/php-jwt` to `^7.0`

## 3.1.0 - 2025-03-10
### Added

- Basic GraphQL Types
- Added support for rendering Twig in the `scriptSrcNonce` setting

### Removed

- Static MP4 generation

## 3.1.0 - 2025-03-10

## 3.0.1 - 2024-08-22

### Fixed

- Fixed some minor Craft 5 compatibility issues

## 3.0.0 - 2024-08-22

### Added

- Added support for Craft 5
