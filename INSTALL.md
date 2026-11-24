# Installation and upgrade notes

## PHP prerequisites

Confirm that the PHP runtime used by the web server has these extensions enabled:

```bash
php -m | grep -E 'PDO|pdo_sqlite|sqlite3|fileinfo'
```

The output should include `PDO`, `pdo_sqlite`, `sqlite3`, and `fileinfo`. On Debian/Ubuntu, install the package matching the PHP version (commonly `php-sqlite3` and `php-fileinfo`) and restart Apache or PHP-FPM. CLI PHP and web-server PHP may use different configuration files.

## Fresh install

1. Upload the `helpdesk/` directory (including hidden `.htaccess` files).
2. Make the directory writable by the web-server user for the duration of setup (or pre-create writable `storage/` and `uploads/`).
3. Open `setup.php`, confirm the Public URL, and create the administrator. `config.php` is written last, so a failed attempt can be retried.
4. Configure email and send a test message.
5. Enable OTP in Settings only after confirming delivery.
6. Delete or rename `setup.php`.
7. On Nginx, add the deny rules from README → *Protecting private files*, then confirm Settings shows no database-exposure warning.

## Upgrade from v1.2.5 or later (including v1.3.0 → v1.4.0)

1. Back up the full application, especially `config.php`, `storage/helpdesk.sqlite`, and `uploads/`.
2. Extract v1.4.0 over the existing application, **including hidden files** (`.htaccess` in the root, `storage/`, and `app/` are new or changed).
3. Preserve `config.php`, the database, and all uploaded attachments. Add `base_url` (and, behind a trusted proxy, `trusted_proxy_header`) to your existing `config.php`; see `config.example.php`.
4. Nginx: add the deny rules from README → *Protecting private files*.
5. Sign in as administrator. A one-time migration runs and stores `schema_version` 14 (earlier versions re-ran it on every request). Older installs also have the legacy guest-ticket rebuild applied once if still needed. Back up first and verify the upgrade before deleting the backup.
6. Open Settings and resolve any *Public URL not set* or *database appears to be downloadable* warnings.
7. Test email, then enable OTP under Settings → Security.
8. Review Roles & permissions: built-in role defaults are now applied once at upgrade and no longer re-added on every request, so your removals persist.
9. Test new-ticket submission, guest submission, sign-in lockout, profile editing, logo display on the login screen, email template editing, user deletion, and team/date-range reports.

Do not run the installer on an existing database. Keep the backup until the upgraded application has been validated.


### PHP upload configuration

File and image uploads are limited by PHP and web-server configuration rather than a HelpDesk-specific file-size cap. Review `upload_max_filesize`, `post_max_size`, and `max_file_uploads` in the PHP configuration used by the web SAPI (not only CLI PHP). Ensure `post_max_size` is larger than `upload_max_filesize`; restart/reload PHP-FPM or Apache after changes. The Settings page shows the active values.


## Permissions and email editor

Direct roles and group roles are additive. The System Administrator role is protected and cannot be assigned to a group. Ticket access is limited by own/group/all-ticket permissions; review team memberships and group role assignments before enabling access for staff. As of v1.4.0, non-administrators who can manage users or roles can only act on accounts and permissions that do not exceed their own.

The email-template editor is Jodit (MIT). It loads from `public/vendor/jodit/` if you place `jodit.min.js` and `jodit.min.css` there, otherwise from `cdn.jsdelivr.net`; if neither is reachable, use the HTML textarea fallback. Set `base_url` in `config.php` so `{{ticket.url}}` and every other email link are correct behind reverse proxies or in a subdirectory.
