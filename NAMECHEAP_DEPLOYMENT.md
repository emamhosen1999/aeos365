# Aero Enterprise Suite - Namecheap Deployment Guide

## Pre-Deployment Checklist

### 1. **Domain Configuration** ✅
- [ ] Main domain: `aeos365.com` (with wildcard SSL certificate installed)
- [ ] Subdomains needed:
  - `admin.aeos365.com` - Admin panel access
  - `tenant1.aeos365.com` - Tenant 1 application (example)
  - Any other tenant subdomains

### 2. **Database Setup** (Namecheap cPanel)
Create these databases in cPanel MySQL:

```
Database: aeos365_central     (Central/Platform database)
Database: aeos365_landing     (WordPress database)
Database: tenant1_database    (Tenant 1 database - example)
```

Database credentials will be: `yourusername_dbuser` with password

### 3. **Directory Structure on Namecheap**
```
public_html/                  ← Main domain root (aeos365.com)
├── .htaccess                 ← Already configured
├── index.php                 ← Laravel entry point
├── public/                   ← Compiled assets
│   ├── build/               ← Vite compiled files
│   ├── wordpress/           ← WordPress installation
│   └── .htaccess            ← Already configured
├── app/
├── bootstrap/
├── config/
├── database/
├── packages/
├── resources/
├── routes/
├── storage/
└── vendor/                   ← Install via composer
```

---

## Deployment Steps

### Step 1: Upload Files to Namecheap via FTP

1. Connect via FTP using Namecheap credentials:
   - Host: `aeos365.com`
   - Username: Your Namecheap FTP username
   - Password: FTP password from cPanel

2. Upload files to `public_html/`:
   - Upload all files EXCEPT:
     - `node_modules/` (too large)
     - `.env.local` (create new on server)
     - `storage/logs/*` (can be empty)
     - `bootstrap/cache/packages.php`

3. Upload WordPress files:
   - WordPress is in `public/wordpress/`
   - Already configured in `public/wordpress/wp-config.php`
   - Just copy as-is

### Step 2: Configure .env on Namecheap

SSH into your Namecheap account or use cPanel File Manager:

```bash
# SSH
ssh yourusername@aeos365.com

# Navigate to project
cd public_html

# Create .env from example
cp .env.example .env

# Edit .env with your Namecheap credentials
nano .env
```

**Update these values in `.env`:**

```env
# === PRODUCTION VALUES ===
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aeos365.com
APP_DOMAIN=aeos365.com
ADMIN_DOMAIN=admin.aeos365.com
PLATFORM_DOMAIN=aeos365.com

# === DATABASE (from cPanel) ===
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yourusername_aeos365   # from cPanel
DB_USERNAME=yourusername_dbuser     # from cPanel
DB_PASSWORD=<your-db-password>      # from cPanel

# === SESSION & CACHE ===
SESSION_DRIVER=cookie
SESSION_DOMAIN=.aeos365.com
CACHE_STORE=file
QUEUE_CONNECTION=sync

# === SECURITY ===
APP_KEY=<generate-new-key-on-server>

# === OPTIONAL ===
MAIL_DRIVER=smtp
MAIL_HOST=<mail-host>
MAIL_PORT=<mail-port>
```

### Step 3: Generate Application Key

```bash
# SSH or cPanel Terminal
cd public_html
php artisan key:generate
```

### Step 4: Run Migrations & Seeders

```bash
# Run all migrations
php artisan migrate

# Seed initial data (plans, roles, permissions)
php artisan db:seed --class=DatabaseSeeder
```

### Step 5: Install Composer Dependencies

**Option A: SSH (Recommended)**
```bash
cd public_html
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize
```

**Option B: If composer unavailable**
- Upload `vendor/` folder via FTP (or)
- Use Namecheap's "Advanced Features" to run composer

### Step 6: Fix File Permissions

```bash
# SSH commands
cd public_html

# Storage directory (writable by web server)
chmod -R 775 storage bootstrap/cache

# Set ownership to web user
chown -R nobody:nobody storage bootstrap/cache
```

### Step 7: Clear All Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Step 8: Configure WordPress

#### WordPress Database
- Database: `yourusername_aeos365_landing`
- User: `yourusername_dbuser`
- Password: Same as created in cPanel

#### WordPress URLs (Already configured in wp-config.php)
```php
define('WP_HOME', 'https://aeos365.com');           // Public URL
define('WP_SITEURL', 'https://aeos365.com/wordpress'); // Actual location
```

#### WordPress Admin Access
- URL: `https://aeos365.com/wordpress/wp-admin/`
- Credentials: `admin` / `YF7j#!OU%3E7VDNCTN` (or reset via MySQL)

### Step 9: Configure SSL Certificate

1. **Wildcard SSL** (Already on Namecheap)
   - Works for: `*.aeos365.com`
   - Includes: `aeos365.com`, `admin.aeos365.com`, `tenant1.aeos365.com`, etc.

2. **Force HTTPS**
   - Update `/public/.htaccess` to redirect HTTP → HTTPS:

```apache
# Add to top of .htaccess
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

3. **Update `.env`**
   - Change `APP_URL=https://aeos365.com` (HTTPS)

### Step 10: Test URLs

1. **Laravel Platform:**
   - Homepage: `https://aeos365.com/` → WordPress homepage
   - Registration: `https://aeos365.com/register`
   - Login: `https://aeos365.com/login`

2. **WordPress:**
   - Website: `https://aeos365.com/wordpress/`
   - Admin: `https://aeos365.com/wordpress/wp-admin/`

3. **Admin Panel:**
   - `https://admin.aeos365.com/` → Laravel admin panel

4. **Tenants (Example):**
   - `https://tenant1.aeos365.com/`

---

## Troubleshooting

### 500 Internal Server Error
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Verify writable directories
ls -la storage/
ls -la bootstrap/cache/

# Check .env file exists and is readable
cat .env
```

### Database Connection Error
```bash
# Verify credentials in Namecheap cPanel → Databases
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### WordPress Not Loading
```bash
# Check WordPress database
mysql -u yourusername_dbuser -p yourusername_aeos365_landing

# List WordPress options
SELECT * FROM wp_options WHERE option_name IN ('siteurl', 'home');
```

### Subdomain Not Accessible
1. Check DNS records in Namecheap:
   - A record: `admin.aeos365.com` → Your server IP
   - A record: `tenant1.aeos365.com` → Your server IP
2. Verify SSL certificate covers subdomain
3. Check cPanel "Addon Domains" or "Subdomains"

---

## Post-Deployment

### Security Checklist
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Verify HTTPS is working on all domains
- [ ] Enable CloudFlare DDoS protection (optional)
- [ ] Set up automated backups
- [ ] Configure email notifications for errors

### Monitoring
- [ ] Monitor storage disk usage
- [ ] Set up log rotation for `storage/logs/`
- [ ] Monitor database size
- [ ] Set up uptime monitoring

### Maintenance Commands
```bash
# Daily cache clearing
php artisan cache:clear

# Database backup
mysqldump -u yourusername_dbuser -p yourusername_aeos365 > backup.sql

# Logs cleanup
find storage/logs -mtime +30 -delete
```

---

## Support

For issues, check:
1. `storage/logs/laravel.log` - Laravel errors
2. Namecheap cPanel → Error Logs
3. `/public/wordpress/wp-content/debug.log` - WordPress errors (if enabled)

**Key Contacts:**
- Namecheap Support: https://www.namecheap.com/support/
- Laravel Docs: https://laravel.com/docs
- WordPress Docs: https://wordpress.org/support/
