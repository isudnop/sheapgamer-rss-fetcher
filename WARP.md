# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a WordPress plugin that fetches content from RSS feeds and automatically creates WordPress posts. It integrates with Google's Gemini AI to generate SEO-friendly slugs, tags, excerpts, and fix problematic titles.

**Key Features:**
- Automated RSS content fetching with hourly WP-Cron job
- Gemini AI integration for intelligent content processing
- Category auto-assignment based on title prefixes
- Featured image extraction and upload
- Custom logging system with WordPress admin UI

## Architecture

### Core Components

**Main Plugin Class: `SheapGamer_RSS_Fetcher`**
- Single-class architecture in `sheapgamer-rss-fetcher.php`
- Uses WordPress hooks and actions for integration
- Implements AJAX handlers for admin UI interactions

**Key Methods:**
- `run_fetch_process()`: Core logic for RSS fetching and post creation (called by both AJAX and cron)
- `_fetch_rss_posts()`: Fetches and parses RSS feed using WordPress SimplePie
- `_create_wordpress_post()`: Creates WordPress post with AI enhancements
- `_get_gemini_suggestions()`: Calls Gemini API for slug/tag generation
- `_get_gemini_title_suggestion()`: Fixes problematic titles using AI
- `_get_gemini_excerpt_suggestion()`: Generates Thai excerpt using AI
- `_set_featured_image_from_url()`: Downloads and attaches featured images
- `_log_message()`: Logs to custom database table

**Category System:**
The plugin uses hardcoded category IDs defined in `ID_CATEGORIES` constant:
- 'news' => 1
- 'deals' => 19 (default)
- 'article' => 1641
- 'demo' => 1670
- 'mods' => 1347

Category assignment is based on title prefixes: [News], [Article], [Demo], [Mods]

**Admin UI Components:**
- `admin_rss_fetcher.js`: jQuery-based AJAX handlers
- `admin_rss_fetcher.css`: Admin panel styling
- Settings page located at: Settings > RSS Fetcher

### Data Flow

1. **RSS Fetching:**
   RSS Feed → SimplePie Parser → Extract title/content/image/date → Post Data Array

2. **Post Creation:**
   Post Data → Category Detection → Title Processing (AI if needed) → Content Processing → Gemini API (slug/tags/excerpt) → wp_insert_post() → Featured Image Download → Meta Storage

3. **Gemini AI Integration:**
   - Uses model version: `gemini-2.5-flash` (defined in `GEMINI_VERSION` constant)
   - API endpoint: `https://generativelanguage.googleapis.com/v1beta/models/`
   - Three distinct API calls: title suggestion, excerpt generation, slug/tags generation

4. **Logging System:**
   All operations → `_log_message()` → Custom DB table (`wp_sheapgamer_rss_fetcher_logs`) → Admin UI display

### Database Schema

**Custom Log Table:** `{$wpdb->prefix}sheapgamer_rss_fetcher_logs`
```
- id: bigint(20) AUTO_INCREMENT
- timestamp: datetime
- type: varchar(20) [info, error, warning, success]
- message: text
```

**Post Meta Keys:**
- `_sheapgamer_rss_guid`: Stores RSS item GUID for duplicate detection
- `_sheapgamer_rss_original_link`: Stores original RSS source URL

## Common Commands

### Testing in WordPress Environment

The plugin must be tested within a WordPress installation. There are no standalone tests.

**Installation:**
```bash
# Copy plugin to WordPress plugins directory
cp -r . /path/to/wordpress/wp-content/plugins/sheapgamer-rss-fetcher/

# Or create symlink for development
ln -s /Users/noppadolm/Projects/sheapgamer-rss-fetcher /path/to/wordpress/wp-content/plugins/
```

**Activate Plugin via WP-CLI:**
```bash
wp plugin activate sheapgamer-rss-fetcher
```

**Deactivate Plugin via WP-CLI:**
```bash
wp plugin deactivate sheapgamer-rss-fetcher
```

**View Cron Status:**
```bash
wp cron event list | grep sheapgamer
```

**Manually Trigger Cron Job:**
```bash
wp cron event run sheapgamer_rss_fetcher_cron_hook
```

**Check Plugin Logs (via database):**
```bash
wp db query "SELECT * FROM wp_sheapgamer_rss_fetcher_logs ORDER BY timestamp DESC LIMIT 20"
```

**Clear Plugin Logs:**
```bash
wp db query "TRUNCATE TABLE wp_sheapgamer_rss_fetcher_logs"
```

### Development Workflow

**Edit and Test:**
1. Make code changes in this directory
2. If plugin is symlinked, changes are immediately available
3. Test via WordPress admin panel: Settings > RSS Fetcher
4. Click "Fetch Posts Now" to trigger manual fetch
5. Check Activity Log for results

**Debugging:**
- Enable WordPress debug mode: Set `WP_DEBUG` to `true` in `wp-config.php`
- Check PHP error log for plugin errors
- Review plugin's Activity Log in admin panel
- Use browser DevTools to debug AJAX calls

## Important Implementation Details

### WordPress Integration Points

**Activation Hook:** Creates custom log table and schedules hourly cron job
**Deactivation Hook:** Clears scheduled cron job (but preserves settings)

**AJAX Actions:**
- `sheapgamer_rss_fetch_posts`: Manual post fetching
- `sheapgamer_rss_fetcher_clear_logs`: Clear activity logs
- `sheapgamer_rss_fetcher_get_logs`: Refresh log display

**Cron Hook:**
- `sheapgamer_rss_fetcher_cron_hook`: Automated hourly fetching

### Content Processing Rules

**Title Extraction:**
1. Extract text before first `<br>` tag from RSS content
2. Strip HTML tags
3. If title contains URLs or is too short, use Gemini AI for suggestion
4. Remove category prefix markers ([News], [Article], etc.)

**Content Processing:**
1. Remove first line (used as title)
2. Strip all HTML except: `<a><p><h1><h2><h3><h4><h5><h6>`
3. Normalize line breaks (max 2 consecutive)
4. Auto-link URLs with `make_clickable()`
5. Append source link at bottom

**Excerpt Generation:**
- Gemini generates Thai excerpt (max 50 words)
- Falls back to WordPress `wp_trim_words()` if AI fails

**Image Handling:**
- Tries media:content tag first
- Falls back to enclosure tag
- Last resort: extracts from content HTML
- Downloads and attaches as featured image

### Gemini AI Prompts

**Title Suggestion Prompt:**
- Asks for concise, descriptive English title (max 15 words)
- Provides original title and content snippet

**Excerpt Prompt:**
- Requests Thai excerpt (max 50 words)
- No HTML or markdown allowed

**Slug/Tags Prompt:**
- Returns JSON: `{"slug": "...", "tags": "..."}`
- Slug: URL-safe, lowercase, hyphenated
- Tags: 5-7 comma-separated relevant keywords

### Security Considerations

- All user inputs sanitized with `sanitize_text_field()`, `esc_url()`, etc.
- AJAX calls protected with nonces
- Capability checks: `edit_published_posts` required
- API key stored in WordPress options (masked password field)
- Gemini API key should never be logged or exposed in responses

### Error Handling

- All major operations wrapped with error checking
- WordPress `is_wp_error()` used for WordPress API calls
- HTTP response codes validated for remote requests
- Fallback logic for AI failures (uses basic algorithms)
- Comprehensive logging of all errors with context

## Configuration

**Required Settings:**
- **RSS Feed URL:** The RSS feed to fetch from
- **Post Limit:** Number of posts to fetch (1-25, default: 5)
- **Gemini API Key:** Required for AI features (get from Google AI Studio)

**Plugin Constants:**
- `GEMINI_VERSION`: Current AI model version
- `ID_CATEGORIES`: WordPress category ID mappings

## Post Author

All created posts are assigned to WordPress user ID `5`. Update line 771 in `sheapgamer-rss-fetcher.php` if different user ID is needed:
```php
'post_author' => 5,
```

## Duplicate Prevention

Posts are identified by RSS GUID stored in `_sheapgamer_rss_guid` post meta. The plugin checks for existing posts before creation to prevent duplicates.
