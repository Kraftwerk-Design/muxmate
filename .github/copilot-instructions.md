# MuxMate Plugin Development Guide

## Overview

MuxMate is a Craft CMS 5.x plugin that integrates Mux video streaming services. It automatically processes video assets, creates Mux assets via API, handles webhooks for status updates, and provides Twig methods for video rendering with `<mux-video>` web components.

## Core Architecture

### Plugin Structure (namespace: `vaersaagod\muxmate`)

- **Main Plugin**: `src/MuxMate.php` - Central plugin class with event handlers and initialization
- **Custom Field**: `src/fields/MuxMateField.php` - Stores Mux metadata (`muxAssetId`, `muxMetaData`) in asset field layouts
- **Asset Behavior**: `src/behaviors/MuxAssetBehavior.php` - Adds Mux methods to all Asset elements (`getMuxVideo()`, `getMuxStreamUrl()`, etc.)
- **API Client**: `src/helpers/MuxApiHelper.php` - Wrapper for `muxinc/mux-php` SDK operations
- **Core Helper**: `src/helpers/MuxMateHelper.php` - Central business logic for asset processing and URL generation

### Data Flow Pattern

1. **Asset Upload** → Video saved to Craft volume
2. **Auto-Processing** → `EVENT_AFTER_PROPAGATE` triggers `MuxMateHelper::updateOrCreateMuxAsset()`
3. **Mux Creation** → `MuxApiHelper::createAsset()` calls Mux API with video URL
4. **Webhook Updates** → `controllers/WebhookController.php` receives status changes, saves to field
5. **Frontend Rendering** → Twig calls `asset.getMuxVideo()` which generates `<mux-video>` component

## Key Development Patterns

### Configuration System

- Config file: `config/_muxmate.php` (not `config/muxmate.php`)
- Settings model: `src/models/Settings.php` with environment variable parsing
- Required: `muxAccessTokenId`, `muxSecretKey`
- Volume-specific overrides: `volumes[handle].baseUrl` for local development tunnels

### Field Integration

- Only one MuxMateField per field layout (enforced in `FieldLayout::EVENT_DEFINE_RULES`)
- Only works on video assets (validated in field input rendering)
- Stores JSON metadata in `{fieldHandle}_muxMetaData` database column
- Query support: `Asset::find().muxMateField({ status: 'ready' })` or `.muxMateField(':notempty:')`

### Asset Behavior Methods

All video assets automatically get these methods via `MuxAssetBehavior`:

```php
$asset->isMuxVideo() // bool
$asset->getMuxVideo($options, $params, $policy) // Markup - renders <mux-video>
$asset->getMuxStreamUrl($params, $policy) // string - HLS URL
$asset->getMuxMp4Url($quality, $policy) // string - static rendition
$asset->getMuxImageUrl($params, $policy) // string - thumbnail
$asset->getMuxGifUrl($params, $policy) // string - animated GIF
```

### Signed URLs & Security

- Dual playback IDs created: public + signed (when `muxSigningKey` configured)
- Policy parameter: `'public'` or `'signed'` (defaults to `defaultPolicy` setting)
- Template caching compatible: token placeholders replaced in `Response::EVENT_AFTER_PREPARE`
- JWT signing: `src/helpers/SignedUrlsHelper.php` handles token generation

### Event-Driven Processing

Critical events in `src/MuxMate.php::attachEventHandlers()`:

- `Asset::EVENT_AFTER_PROPAGATE` - Create Mux assets (excludes resaving)
- `Assets::EVENT_BEFORE_REPLACE_ASSET` - Delete old Mux data
- `Asset::EVENT_AFTER_DELETE` - Cleanup Mux assets
- `Assets::EVENT_DEFINE_THUMB_URL` - Use Mux thumbnails in CP
- `Response::EVENT_AFTER_PREPARE` - Replace nonce/token placeholders

## Development Commands

### CLI Commands (`src/console/controllers/CreateController.php`)

```bash
./craft _muxmate/create                    # All video assets
./craft _muxmate/create --volume=videos    # Specific volume
./craft _muxmate/create --assetId=123      # Single asset
./craft _muxmate/create --update=1         # Force recreation
```

### Code Quality

```bash
composer check-cs    # ECS code style check
composer fix-cs      # Fix code style issues
composer phpstan     # Static analysis
```

## Integration Points

### Webhook Endpoint

- Route: `muxmate/webhook` → `WebhookController::actionIndex()`
- Handles events: `video.asset.created|ready|updated|static_renditions.ready|deleted`
- Anonymous access, CSRF disabled
- Finds assets by iterating through all MuxMateField instances

### Template Integration

```twig
{# Basic video with controls #}
{{ video.getMuxVideo()|attr({ controls: true }) }}

{# Inline/autoplay video #}
{{ video.getMuxVideo({ inline: true }) }}

{# Signed video with quality limit #}
{{ video.getMuxVideo(null, { max_resolution: '720p' }, 'signed') }}

{# Custom object-fit styling #}
{{ video.getMuxVideo()|attr({
    style: { '--media-object-fit': 'cover' }
}) }}
```

### Local Development Setup

- Use cloud storage volumes OR tunnel (ngrok) for asset access
- Override volume base URLs via `volumes[handle].baseUrl` config
- Webhook requires public endpoint - use tunneling service

## Error Handling Patterns

- API exceptions logged to `_muxmate` log category
- Webhook failures return false but don't throw
- Missing config throws exceptions in `MuxApiHelper::getApiClient()`
- Field validation prevents multiple MuxMateFields per layout

## Dependencies

- **Core**: `craftcms/cms: ^5.2.0`, `php: >=8.2`
- **Mux SDK**: `muxinc/mux-php: ^3.0` for API operations
- **JWT**: `firebase/php-jwt: ^6.9` for signed URL tokens
- **Dev Tools**: `craftcms/ecs`, `craftcms/phpstan`
