# WordPress Setup Guide for aeos365

## Current Status
- ✅ Folder created: `public/wordpress/`
- ✅ `.htaccess` updated to route WordPress pages to `/wordpress/` subfolder
- ✅ WordPress config template created: `wp-config-template.php`
- ✅ WordPress index template created: `index-template.php`

---

## Step 1: Extract WordPress ZIP

**Extract the WordPress ZIP file directly into:**
```
d:\laragon\www\aeos365\public\wordpress\
```

After extraction, your folder should contain:
```
public/wordpress/
├── index.php              ← WordPress entry point
├── wp-config.php          ← WordPress database config (CREATE THIS)
├── wp-load.php
├── wp-settings.php
├── wp-admin/              ← WordPress admin
├── wp-includes/           ← WordPress core
├── wp-content/            ← Themes, plugins, uploads
│   ├── themes/
│   ├── plugins/
│   └── uploads/
└── ... other WordPress files
```

---

## Step 2: Configure wp-config.php

1. **Copy template** (We already created it):
   - Template location: `public/wordpress/wp-config-template.php`
   - Target location: `public/wordpress/wp-config.php`

2. **Edit `public/wordpress/wp-config.php`** with your database credentials:

```php
// Update these with YOUR database info from Laragon:
define('DB_NAME', 'aeos365_db');        // Your database name
define('DB_USER', 'root');              // Usually 'root' in Laragon
define('DB_PASSWORD', '');              // Usually empty in Laragon

define('WP_HOME', 'http://aeos365.test');           // Your local domain
define('WP_SITEURL', 'http://aeos365.test/wordpress');
```

3. **Generate security keys** (optional but recommended):
   - Visit: https://api.wordpress.org/secret-key/1.1/salt/
   - Copy the generated keys into `wp-config.php` (replace the placeholder values)

---

## Step 3: Create WordPress Database

**In Laragon:**

1. Click **Database** → **MySQL**
2. Use phpMyAdmin to create database:
   - Database name: `aeos365_db`
   - Collation: `utf8mb4_unicode_ci`

OR run in MySQL:
```sql
CREATE DATABASE aeos365_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Step 4: Access WordPress Installer

Open in browser:
```
http://aeos365.test/wordpress/wp-admin/install.php
```

Follow the setup wizard to:
- Enter site title, admin user, password, email
- Complete installation

---

## Step 5: Verify Routes

After WordPress is installed, test these URLs:

| URL | Expected Behavior |
|-----|-------------------|
| `http://aeos365.test/` | WordPress homepage |
| `http://aeos365.test/wordpress/wp-admin/` | WordPress admin login |
| `http://aeos365.test/register` | Laravel registration page |
| `http://aeos365.test/login` | Laravel login page |

---

## Step 6: Configure WordPress Settings

1. **Login to WordPress** → `http://aeos365.test/wordpress/wp-admin/`
2. Go to **Settings** → **General**
3. Verify:
   - Site Address (URL): `http://aeos365.test`
   - WordPress Address (URL): `http://aeos365.test/wordpress`
4. Go to **Permalinks** → Use "Post name" for clean URLs

---

## File Reference

Templates we created (use these as guides when needed):
- `public/wordpress/wp-config-template.php` - Copy this to `wp-config.php`
- `public/wordpress/index-template.php` - Reference for WordPress index.php structure

---

## Laravel Routes (No changes needed)

Your existing routes already work:
- `/register` → Laravel registration (via .htaccess routing)
- `/login` → Laravel login (via .htaccess routing)
- `/` → WordPress homepage (via .htaccess routing)
- `admin.aeos365.test/*` → Laravel admin (tenancy middleware handles this)
- `tenant1.aeos365.test/*` → Laravel tenant (tenancy middleware handles this)

---

## Database Credentials for Laragon

In Laragon MySQL (Default):
```
Host: localhost
Port: 3306
User: root
Password: (empty)
```

---

## Troubleshooting

**WordPress shows "Error establishing database connection":**
- Check `wp-config.php` database credentials
- Verify database `aeos365_db` exists in phpMyAdmin
- Restart Laragon MySQL

**404 on WordPress pages:**
- Verify `.htaccess` is updated in `public/`
- Check mod_rewrite is enabled in Apache

**Laravel routes not working:**
- .htaccess should route `/register`, `/login` to Laravel
- Check if route is in `routes/web.php`

---

## Next Steps After Setup

1. ✅ Extract WordPress ZIP → `public/wordpress/`
2. ✅ Create `wp-config.php` from template
3. ✅ Create database: `aeos365_db`
4. ✅ Run WordPress installer
5. ✅ Test all routes work

Then we can:
- 🎨 Configure WordPress theme
- 🔗 Setup branding plugin for links
- 📝 Create landing pages in WordPress
- 🔐 Setup lead capture forms
