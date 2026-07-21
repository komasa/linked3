# Linked3 AI — Deployment Guide

> **Version**: v27.6.2
> **Last Updated**: 2026-07-21

---

## Prerequisites

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4 | 8.2+ |
| WordPress | 6.2 | 6.5+ |
| MySQL | 5.7 | 8.0+ |
| Memory Limit | 256M | 512M+ |

---

## Installation

### Fresh Install

1. Upload `linked3-v27.6.2.zip` via Plugins → Add New → Upload Plugin
2. Activate
3. Navigate to Linked3 Dashboard → Settings → enter API keys

### Upgrade from Previous Version

**⚠️ Critical**: WordPress does not delete old files during plugin updates. If upgrading from v27.4.x or earlier:

1. **FTP method (recommended)**:
   - Connect via FTP/SFTP
   - Delete entire `wp-content/plugins/linked3-ai/` directory
   - Upload new zip via Plugins → Add New → Upload Plugin
   - Activate

2. **WP-CLI method** (if available):
   ```bash
   wp plugin deactivate linked3-ai
   wp plugin uninstall linked3-ai --skip-delete
   wp plugin install linked3-v27.6.2.zip --activate
   ```

### Post-Install Verification

1. Check **Plugins → Installed Plugins** → Linked3 AI shows version `27.6.2`
2. Navigate to **Linked3 Dashboard** — no fatal errors
3. Check **Tools → Site Health** — no PHP error notices related to Linked3

---

## Configuration

### API Keys

Navigate to **Linked3 → Settings → AI Configuration**:

- **Primary Model**: Select main AI model (GPT-4o, Claude 3.5, etc.)
- **API Key**: Enter provider API key
- **Fallback Model**: Optional secondary model for failover

### Content Writer Settings

**Linked3 → Content Writer → Settings**:

- Default word count: 2000
- SEO optimization: Enabled
- Auto-generate meta description: Enabled

### SEO Settings

**Linked3 → SEO → Settings**:

- Auto-push to search engines: Enabled
- Sitemap generation: Enabled
- Schema markup: Enabled

---

## Troubleshooting

### Fatal Error on Activation

**Symptom**: `Fatal error: Class ... not found`

**Cause**: Old plugin files not fully removed

**Fix**:
1. FTP to server
2. Delete `wp-content/plugins/linked3-ai/` entirely
3. Re-upload fresh zip
4. Activate

### AJAX Returns 0 or -1

**Cause**: Nonce verification failure

**Fix**:
1. Clear browser cache
2. Log out and back in to wp-admin
3. Check `wp-config.php` has `define('NONCE_SALT', ...)` properly set

### Genesis Generation Fails (501)

**Cause**: AI provider returns error

**Fix**:
1. Check API key validity
2. Verify API quota
3. Check error log: `wp-content/uploads/linked3-logs/`
4. Try switching to fallback model

### Memory Exhaustion

**Fix**: Add to `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

---

## Rollback

If v27.6.2 causes issues:

1. Deactivate Linked3 AI
2. FTP delete plugin directory
3. Install previous version zip
4. Activate
5. Report issue with error details

---

## File Structure

```
linked3-ai/
├── linked3.php                    # Main plugin file
├── composer.json                  # Dependencies
├── src/
│   ├── Classes/                   # Business logic
│   │   ├── AI/                    # AI dispatch pipeline
│   │   ├── Agent/                 # Agent orchestration
│   │   ├── Billing/               # SaaS billing
│   │   ├── BookFactory/           # Book generation
│   │   ├── Content/               # Content management
│   │   ├── ContentWriter/         # Long-form writing
│   │   ├── Core/                  # Core utilities
│   │   ├── Dashboard/             # Admin dashboard
│   │   ├── Diagram/               # Diagram generation
│   │   ├── Distribute/            # Content distribution
│   │   ├── Genesis/               # Genesis pipeline
│   │   ├── MetaLever/             # Meta-lever system
│   │   ├── OS/                    # OS compatibility layer
│   │   ├── Publish/               # Publishing adapters
│   │   ├── SEO/                   # SEO optimization
│   │   ├── Security/              # Security utilities
│   │   └── XHS/                   # 小红书 integration
│   └── Includes/                  # Framework layer
│       ├── Plugin.php             # Main plugin class
│       ├── HookManager.php        # Hook registration
│       ├── DependencyLoader.php   # Autoloading
│       ├── AjaxNonceGuard.php     # AJAX security middleware
│       └── EventBus.php           # Event system
├── admin/                         # Admin templates & JS
├── assets/                        # CSS, JS, images
├── lib/                           # Early-load libraries
├── languages/                     # i18n files
└── tests/                         # PHPUnit tests
```

---

## Support

- **GitHub**: https://github.com/komasa/linked3
- **Logs**: `wp-content/uploads/linked3-logs/`
- **Debug**: Set `WP_DEBUG` and `WP_DEBUG_LOG` to `true` in `wp-config.php`
