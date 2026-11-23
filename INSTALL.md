# Installation and upgrade notes

## PHP prerequisites

Confirm that the PHP runtime used by the web server has these extensions enabled:

```bash
php -m | grep -E 'PDO|pdo_sqlite|sqlite3|fileinfo'
```

The output should include `PDO`, `pdo_sqlite`, `sqlite3`, and `fileinfo`. On Debian/Ubuntu, install the package matching the PHP version (commonly `php-sqlite3` and `php-fileinfo`) and restart Apache or PHP-FPM. CLI PHP and web-server PHP may use different configuration files.

## Fresh install

1. Upload the `helpdesk/` directory.
2. Make `storage/` and `uploads/` writable by the web-server user.
3. Open `setup.php` and create the administrator.
4. Configure email and send a test message.
5. Enable OTP in Settings only after confirming delivery.
6. Remove or rename `setup.php`.

## Upgrade from v1.2.5 or later

1. Back up the full application, especially `config.php`, `storage/helpdesk.sqlite`, and `uploads/`.
2. Extract v1.3.0 application files over the existing application.
3. Preserve `config.php`, the database, and all uploaded attachments.
4. Sign in as administrator. The first database connection runs a migration that rebuilds the ticket table to allow nullable requester user IDs. Ticket data, assignments, comments, and attachment records are copied and retained. The legacy Guest Ticket Holder account is removed after its ticket references are converted. Back up first and verify the upgrade before deleting the backup.
5. Test email, then enable OTP under Settings → Security.
6. Review group memberships and role permissions. Test new-ticket submission, guest submission, profile editing, logo upload/email display, user deletion, and team/date-range reports.

Do not run the installer on an existing database. Keep the backup until the upgraded application has been validated.


### PHP upload configuration

File and image uploads are limited by PHP and web-server configuration rather than a HelpDesk-specific file-size cap. Review `upload_max_filesize`, `post_max_size`, and `max_file_uploads` in the PHP configuration used by the web SAPI (not only CLI PHP). Ensure `post_max_size` is larger than `upload_max_filesize`; restart/reload PHP-FPM or Apache after changes. The Settings page shows the active values.


## v1.3.0 permissions and email editor

After upgrading, review the Users, Groups, and Roles & permissions pages. Direct roles and group roles are additive. The System Administrator role is protected and cannot be assigned to a group. Ticket access is limited by own/group/all-ticket permissions; review team memberships and group role assignments before enabling access for staff.

The visual email-template editor loads TinyMCE Community from jsDelivr. The browser used by administrators must be able to reach `cdn.jsdelivr.net`. If external CDN access is blocked, use the HTML textarea fallback. Set `base_url` in `config.php` to the full public application URL so `{{ticket.url}}` produces correct absolute links behind reverse proxies or when hosted in a subdirectory.
