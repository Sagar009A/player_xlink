# Terabox API Fix for 1024tera.com

## Problem Summary
The system was accepting URLs from `1024tera.com` but the API calls were hardcoded to use `terabox.com` or `terabox.app` endpoints. This caused extraction failures for 1024tera.com links.

## Solution Implemented

### 1. Dynamic Domain Detection
The extractor now automatically detects the domain from the input URL and uses it for API calls.

**Domain Mapping:**
- `1024tera.com` → `www.1024tera.com`
- `1024terabox.com` → `www.1024terabox.com`
- `terabox.com` → `www.terabox.com`
- `terabox.app` → `www.terabox.app`
- Other TeraBox variants → `www.terabox.app` (fallback)

### 2. Files Modified

#### `/extractors/TeraboxExtractor.php`
- Added `$inputDomain` property to store detected domain
- Added `setDomainFromUrl()` method to detect and map domains
- Modified `fetchVideoInfo()` to use dynamic domain in API URL
- Updated headers (Host, Referer) to use dynamic domain

#### `/includes/terabox_helper.php`
- Added domain detection logic
- Updated API URL to use detected domain
- Updated headers to use dynamic domain

#### `/admin/settings.php`
- Added **Terabox API Settings** section
- New settings:
  - **Use Dynamic Domain Detection** (checkbox) - Default: ON
  - **Default Terabox API Domain** (dropdown) - Fallback domain
  - **Terabox JS Token** (textarea) - Optional manual override

### 3. New Admin Settings

Navigate to **Admin Panel → Settings → Terabox API Settings**

Available Options:
1. **Use Dynamic Domain Detection** - Automatically detect and use the correct API domain
2. **Default API Domain** - Choose fallback domain (terabox.app, terabox.com, 1024tera.com, etc.)
3. **JS Token Override** - Manually set a token (leave empty for auto-fetch)

## How It Works

### Before the Fix:
```
Input: https://1024tera.com/s/ABC123
API Call: https://www.terabox.com/api/shorturlinfo  ❌ Wrong!
Result: Failed extraction
```

### After the Fix:
```
Input: https://1024tera.com/s/ABC123
Detection: 1024tera.com → www.1024tera.com
API Call: https://www.1024tera.com/api/shorturlinfo  ✓ Correct!
Result: Successful extraction
```

## Testing

### Test Script
Run the test script to verify the fix:
```
http://your-domain.com/test_1024tera_fix.php
```

### Diagnostic Script
Check API configuration and test all domains:
```
http://your-domain.com/check_terabox_api.php
```

### Manual Testing URLs
Test with these URLs:
- `https://1024tera.com/s/16y9PvRU-Kx5LEb83Yh6iAg`
- `https://www.1024tera.com/s/16y9PvRU-Kx5LEb83Yh6iAg`
- `https://1024terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg`
- `https://terabox.app/s/16y9PvRU-Kx5LEb83Yh6iAg`

## Supported TeraBox Domains

The extractor now properly supports ALL TeraBox domain variants:

### Primary Domains (with dedicated API mapping):
- ✓ `terabox.com` / `www.terabox.com`
- ✓ `terabox.app` / `www.terabox.app`
- ✓ `1024tera.com` / `www.1024tera.com`
- ✓ `1024terabox.com` / `www.1024terabox.com`

### Secondary Domains (mapped to terabox.app):
- `teraboxapp.com`
- `4funbox.com`
- `mirrobox.com`
- `momerybox.com`
- `teraboxlink.com`
- `terasharelink.com`
- `teraboxurl.com`
- `teraboxurl1.com`
- `terasharefile.com`
- `terafileshare.com`

## Configuration Best Practices

### Recommended Settings:
1. **Dynamic Domain Detection:** ON (enabled)
2. **Default API Domain:** www.terabox.app
3. **JS Token:** Leave empty (auto-fetch)

### Troubleshooting:

#### If 1024tera.com still doesn't work:
1. Check admin settings - ensure "Use Dynamic Domain Detection" is enabled
2. Check logs: `/logs/extractor_YYYY-MM-DD.log`
3. Verify the token is being fetched correctly
4. Try manually setting a fresh token in admin settings

#### If all TeraBox domains fail:
1. Check the JS token in database: `SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'`
2. Update token manually via admin panel
3. Check cron job: `/cron/fetch_terabox_token.php`
4. Verify API accessibility from your server

## API Rate Limiting

TeraBox may implement rate limiting. If you encounter CAPTCHA/verification errors:
- Wait 5-10 minutes before retrying
- The system has built-in retry logic with exponential backoff
- Rotate user agents automatically
- Consider distributing requests across multiple servers if needed

## Logs and Debugging

Check extractor logs for detailed information:
```bash
tail -f /workspace/logs/extractor_$(date +%Y-%m-%d).log
```

Look for lines like:
```
[INFO] Domain mapping: 1024tera.com -> www.1024tera.com
[INFO] Fetching video info for shortcode: ABC123
[INFO] Successfully connected to TeraBox API
```

## Database Settings

The following settings are stored in the `settings` table:

| Setting Key | Description | Default |
|------------|-------------|---------|
| `terabox_use_dynamic_domain` | Enable dynamic domain detection | 1 (enabled) |
| `terabox_api_domain` | Default/fallback API domain | www.terabox.app |
| `terabox_js_token` | Cached JS token for API | (auto-fetched) |

## Summary

✅ **Fixed:** 1024tera.com API now works correctly
✅ **Added:** Dynamic domain detection for all TeraBox variants
✅ **Added:** Admin settings for Terabox API configuration
✅ **Improved:** Better logging and error handling
✅ **Tested:** Multiple domain variants supported

The system now intelligently detects which TeraBox domain is being used and makes API calls to the correct endpoint with proper headers.

## Questions or Issues?

If you encounter any issues:
1. Check the test scripts first
2. Review the logs
3. Verify admin settings
4. Ensure the token is valid and not expired

---
**Last Updated:** 2025-11-08
**Version:** 1.0
