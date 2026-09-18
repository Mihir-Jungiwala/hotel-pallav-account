# Deploying Hotel Pallav to Hostinger

Written for whoever puts this live on Hostinger shared hosting. Follow it top to
bottom once; after that, "Updating a live site" at the end is all you need.

---

## 1. What the server needs

| Thing | Value | Where in hPanel |
| --- | --- | --- |
| PHP | 8.2 or newer (8.3 recommended) | Advanced > PHP Configuration |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `zip` | Advanced > PHP Configuration > PHP extensions |
| Database | MySQL 8 or MariaDB 10.4+ | Databases > Management |
| SSH | Needed for `composer` and `php artisan` | Advanced > SSH Access |
| Email | An SMTP mailbox for the sign-in and reset codes | Emails > Email Accounts |

Turn these PHP options on in **Advanced > PHP Configuration > PHP options**:
`allow_url_fopen`, and set `max_execution_time` to at least 120 so the first
`composer install` and `migrate` finish.

If your plan has no SSH, see "No SSH access" at the bottom.

---

## 2. Create the database

**Databases > Management > Create a new database.** Note the four values, they go
straight into `.env`:

- database name, for example `u123456789_pallav`
- user, for example `u123456789_pallav`
- password
- host: `localhost`

---

## 3. Put the files on the server

Laravel must **not** serve its whole folder to the internet. Only `public/` is
public. On Hostinger the tidy layout is:

```
/home/u123456789/
  domains/yourdomain.com/
    public_html/          <- the web root, only Laravel's public/ contents live here
  hotel-pallav/           <- the application itself, outside the web root
```

Over SSH:

```bash
cd ~
git clone https://github.com/Mihir-Jungiwala/hotel-pallav-account.git hotel-pallav
cd hotel-pallav
composer install --no-dev --optimize-autoloader
```

Then move the public files into the web root and point them back at the app:

```bash
cp -r ~/hotel-pallav/public/. ~/domains/yourdomain.com/public_html/
```

Edit `~/domains/yourdomain.com/public_html/index.php` and change the two paths
so they point one level out of the web root:

```php
require __DIR__.'/../../../hotel-pallav/vendor/autoload.php';

$app = require_once __DIR__.'/../../../hotel-pallav/bootstrap/app.php';
```

Count the `../` against your own path. From
`/home/u123456789/domains/yourdomain.com/public_html/index.php`, three levels up
is `/home/u123456789/`, so `../../../hotel-pallav/...` is right.

---

## 4. The .env file

Copy the example and edit it:

```bash
cd ~/hotel-pallav
cp .env.example .env
php artisan key:generate
nano .env
```

What must change from the defaults:

```ini
APP_NAME="Hotel Pallav"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Hostinger MySQL, from step 2
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_pallav
DB_USERNAME=u123456789_pallav
DB_PASSWORD=the-password-you-set

# Sessions live in the database, and the cookie is HTTPS only
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Sign-in codes and password reset codes are emailed, so this has to work
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=accounts@yourdomain.com
MAIL_PASSWORD=the-mailbox-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=accounts@yourdomain.com
MAIL_FROM_NAME="Hotel Pallav"
```

`APP_DEBUG=false` matters: with it on, an error page would show your database
password to whoever triggered it.

Check the mail settings before letting anyone rely on them:

```bash
php artisan tinker --execute="Mail::raw('Test from Hotel Pallav', fn(\$m) => \$m->to('you@yourdomain.com')->subject('Test'));"
```

If that mail never arrives, the emailed sign-in code will not arrive either.
Until it works, leave two factor off (it is off by default) and make sure at
least one administrator account can still sign in with a password.

---

## 5. Create the tables

```bash
cd ~/hotel-pallav
php artisan migrate --force
```

`--force` is required because this is production; without it the command asks a
question that a script cannot answer.

**What the migrations do on MySQL.** The "only one SuperAdmin" rule is enforced
by the database, not only by the code. SQLite uses a partial unique index, which
MySQL does not have, so on MySQL the same migration adds a generated column that
holds `1` for the single live SuperAdmin and `NULL` for everyone else, with a
unique index on it. `NULL` values never collide, so every other account is free.
This is handled for you by `App\Support\SuperAdminIndex::ensure()`, which each
migration touching the `users` table calls:

```php
// database/migrations/..._restore_single_superadmin_index.php
use App\Support\SuperAdminIndex;

public function up(): void
{
    SuperAdminIndex::ensure();   // picks the right form for the connection
}
```

If you ever add a column to `users` yourself, call it again at the end of your
migration. A test fails if the rule goes missing, so run `php artisan test`
after any change there.

Then seed the first account:

```bash
php artisan db:seed --force
```

On a fresh production database this creates one SuperAdmin:

- username `superadmin`
- password `SuperAdmin@123`
- it is marked "must change password", so the first sign-in forces a new one

**Sign in and change that password before telling anyone the address.** The
seeder creates no sample data when `APP_ENV=production`.

---

## 6. Files, links and caches

```bash
cd ~/hotel-pallav

# Uploaded logos, signatures, photos, ID proofs and resumes
php artisan storage:link

# Compile config, routes and views for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Laravel writes to these two
chmod -R 775 storage bootstrap/cache
```

`storage:link` makes `public/storage`. Because your web root is elsewhere, also
link it from the web root:

```bash
ln -s ~/hotel-pallav/storage/app/public ~/domains/yourdomain.com/public_html/storage
```

If the host refuses symlinks, copy the folder instead and repeat the copy
whenever files are uploaded:

```bash
cp -r ~/hotel-pallav/storage/app/public ~/domains/yourdomain.com/public_html/storage
```

---

## 7. HTTPS

In hPanel: **Websites > Manage > SSL**, install the free certificate, then turn
on **Force HTTPS**.

The application already forces `https://` on every generated link when
`APP_ENV=production`, and the session cookie is HTTPS only once you set
`SESSION_SECURE_COOKIE=true`. If you skip the certificate, nobody can sign in,
because the browser will refuse to send that cookie.

---

## 8. Check it over

Open the site and confirm:

- [ ] the sign-in screen loads with styling, not plain text (if it is plain, the
      `public/assets` files did not reach the web root)
- [ ] `superadmin` signs in and is asked for a new password
- [ ] the dashboard shows figures, and the 14 day chart draws
- [ ] Master Data opens and a list can be edited
- [ ] a PDF downloads, for example Reports > Day Book > Download PDF
- [ ] a file uploads and shows, for example a company logo
- [ ] `https://yourdomain.com/storage/...` opens an uploaded image
- [ ] visiting a made up address shows the purple "Page not found" screen, not a
      Laravel stack trace. A stack trace means `APP_DEBUG` is still on

---

## Updating a live site

```bash
cd ~/hotel-pallav
php artisan down                      # "be right back" page while you work

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

cp -r ~/hotel-pallav/public/assets/. ~/domains/yourdomain.com/public_html/assets/
php artisan up
```

The last copy matters: stylesheets and scripts live in `public/assets`, and the
web root holds its own copy of them.

---

## Backups

The database holds every entry, and `storage/app/public` holds every uploaded
document. Back up both.

```bash
# Database
mysqldump -u u123456789_pallav -p u123456789_pallav > ~/backup-$(date +%F).sql

# Uploaded files
tar -czf ~/uploads-$(date +%F).tar.gz -C ~/hotel-pallav/storage/app public
```

hPanel also has **Files > Backups** for automatic daily copies on most plans.
Turn it on and check once that a restore actually works.

---

## No SSH access

On the cheapest plans there is no SSH. Then:

1. Run `composer install --no-dev --optimize-autoloader` on your own machine and
   upload the `vendor` folder with the rest of the files through **File Manager**.
2. Create the `.env` file with File Manager's editor, using the values above.
   Generate `APP_KEY` locally with `php artisan key:generate --show` and paste it.
3. Migrations cannot be run from a browser safely, so create the tables by
   exporting them from a local run:
   ```bash
   # on your machine, against a local MySQL with the same structure
   php artisan migrate --force
   mysqldump -u root -p --no-data local_pallav > structure.sql
   ```
   then import `structure.sql` through **Databases > phpMyAdmin**, and insert the
   first SuperAdmin row by hand with a hashed password from
   `php artisan tinker --execute="echo Hash::make('YourPassword@1');"`.
4. Ask Hostinger support to enable SSH, or move to a plan with it. Updating a
   Laravel site without a command line is painful every single time.

---

## Things that commonly go wrong

| Symptom | Cause | Fix |
| --- | --- | --- |
| "500 Server Error", blank page | `storage` or `bootstrap/cache` not writable | `chmod -R 775 storage bootstrap/cache` |
| Site loads without styling | `public/assets` missing from the web root | copy `public/` into `public_html` again |
| "No application encryption key" | `APP_KEY` empty | `php artisan key:generate` |
| Sign-in loops back to the sign-in screen | secure cookie without HTTPS | install the SSL certificate, or set `SESSION_SECURE_COOKIE=false` while testing |
| Codes never arrive | SMTP wrong | check with the `Mail::raw` test above |
| Uploaded images 404 | `public_html/storage` link missing | recreate the symlink, or copy the folder |
| Changes do not show | cached config or views | `php artisan config:clear && php artisan view:clear`, then cache again |
| "SQLSTATE[42S01] table exists" | migrations run twice | `php artisan migrate:status`, then fix the one row in `migrations` |
