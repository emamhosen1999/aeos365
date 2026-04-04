# 📋 Namecheap Deployment Checklist

## Phase 1: Pre-Deployment ✓

- [ ] Download all files from project folder
- [ ] Remove: `node_modules/`, `.env.local`, `bootstrap/cache/packages.php`
- [ ] Verify files total size for FTP transfer
- [ ] Backup any existing Namecheap data

## Phase 2: Database Setup (cPanel)

### Create MySQL Databases
- [ ] Database: `yourusername_aeos365` (Laravel central)
- [ ] Database: `yourusername_aeos365_landing` (WordPress)
- [ ] Create DB User: `yourusername_dbuser`
- [ ] Set strong password
- [ ] Grant all privileges on both databases

### Get Your Credentials
- [ ] Full DB Host: `localhost`
- [ ] DB Username: `yourusername_dbuser`
- [ ] DB Password: `__________________`
- [ ] Laravel DB: `yourusername_aeos365`
- [ ] WordPress DB: `yourusername_aeos365_landing`

## Phase 3: FTP Upload

- [ ] Connect via FTP to `aeos365.com`
- [ ] Upload all files to `public_html/`
- [ ] Verify upload completed without errors
- [ ] Check file count matches

## Phase 4: Server Configuration (SSH/cPanel Terminal)

**Navigate to project:**
```
cd public_html
```

### Setup Steps (Copy & Paste)

```
# 1. Environment
cp .env.example .env
nano .env
# Edit these fields:
# APP_URL=https://aeos365.com
# DB_DATABASE=yourusername_aeos365
# DB_USERNAME=yourusername_dbuser
# DB_PASSWORD=your_password
# Then: CTRL+X, Y, ENTER

# 2. Dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

# 3. Generate Key
php artisan key:generate

# 4. Database
php artisan migrate

# 5. Clear Caches
php artisan config:clear && php artisan cache:clear

# 6. Permissions
chmod -R 775 storage bootstrap/cache
```

- [ ] All commands executed without errors

## Phase 5: SSL & HTTPS

- [ ] Wildcard SSL certificate installed (*.aeos365.com)
- [ ] Test HTTPS: `https://aeos365.com`
- [ ] Update `.env`: `APP_URL=https://aeos365.com`

## Phase 6: WordPress Configuration

**WordPress Already Configured For:**
- Clean URLs: `https://aeos365.com/` → WordPress homepage
- Actual URL: `https://aeos365.com/wordpress/` (hidden from users)
- Database: `yourusername_aeos365_landing`

### Optional: Reset WordPress Admin Password
```
# If needed to login, SSH and run:
php artisan tinker
>>> DB::table('wp_users')->where('ID', 1)->update(['user_pass' => bcrypt('NewPassword123')])
>>> exit
```

- [ ] WordPress login credentials secure
- [ ] WordPress admin accessible at: `https://aeos365.com/wordpress/wp-admin/`

## Phase 7: DNS & Subdomains

**For Admin Panel Access:**
- [ ] Subdomain: `admin.aeos365.com` → Your server IP (A record)
- [ ] Subdomain: `tenant1.aeos365.com` (example) → Your server IP

Or use Namecheap cPanel "Addon Domains" feature:
- [ ] `admin.aeos365.com` added as addon domain
- [ ] `tenant1.aeos365.com` added as addon domain (if needed)
- [ ] DNS propagated (wait 5-30 mins)

## Phase 8: Testing (URLs to Visit)

### Platform Tests
- [ ] `https://aeos365.com/` → Shows WordPress homepage
- [ ] `https://aeos365.com/register` → Shows Laravel registration form
- [ ] `https://aeos365.com/login` → Shows Laravel login form

### Admin Tests
- [ ] `https://admin.aeos365.com/` → Shows Laravel admin login
- [ ] `https://admin.aeos365.com/dashboard` → Admin dashboard loads (after login)

### WordPress Tests
- [ ] `https://aeos365.com/wordpress/` → WordPress blog homepage
- [ ] `https://aeos365.com/wordpress/wp-admin/` → WordPress login
- [ ] Login with: `admin` / `YF7j#!OU%3E7VDNCTN` (or reset password above)

### Tenant Tests (Optional)
- [ ] `https://tenant1.aeos365.com/` → Tenant application loads (if created)

## Phase 9: Post-Deployment Security

- [ ] Verify `APP_DEBUG=false` in `.env`
- [ ] Verify `APP_ENV=production` in `.env`
- [ ] Check logs: `tail -f storage/logs/laravel.log` (no errors)
- [ ] Set up automated backups in cPanel
- [ ] Enable Namecheap DDoS protection (optional)

## Phase 10: Monitoring & Maintenance

- [ ] Set up email notifications for errors (optional)
- [ ] Monitor disk space usage
- [ ] Schedule weekly backups
- [ ] Monitor error logs weekly

---

## Troubleshooting

### 500 Error on aeos365.com
```
SSH: tail -f storage/logs/laravel.log
Check: .env file exists and correct
Check: Database connection working
```

### Can't access admin.aeos365.com
```
Check: DNS A record for admin.aeos365.com created
Check: SSL cert covers admin.aeos365.com (wildcard: *.aeos365.com)
Wait: 5-30 minutes for DNS propagation
```

### WordPress not loading
```
Check: public/wordpress/wp-config.php exists
Check: Database aeos365_landing exists and accessib
Check: WP_HOME and WP_SITEURL correct in wp-config.php
```

---

## Quick Reference

**Databases:**
- Central: `yourusername_aeos365`
- WordPress: `yourusername_aeos365_landing`

**Admin User:** (WordPress)
- Username: `admin`
- Password: `YF7j#!OU%3E7VDNCTN`

**URLs:**
- Main: `https://aeos365.com/`
- Admin: `https://admin.aeos365.com/`
- WP Admin: `https://aeos365.com/wordpress/wp-admin/`

---

**Last Updated:** April 4, 2026
**Status:** ✅ System Ready for Production Deployment
