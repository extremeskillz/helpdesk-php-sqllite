# HelpDesk (Version 1.3.0)

A self-hosted PHP and SQLite help desk application with customer ticket submission, technician workflows, configurable groups and roles, email notifications, and white-label appearance settings.

## Requirements

- PHP 8.1+ (PHP 8.2+ recommended)
- PDO SQLite (`pdo_sqlite`), Fileinfo, and PHP `mail()` support
- Apache or Nginx with PHP support
- Writable application `uploads/` and database storage directory
- HTTPS for production use

## Fresh installation

1. Copy the `helpdesk/` directory to your PHP web host.
2. Ensure `storage/` and `uploads/` are writable by the web-server account.
3. Visit `/setup.php` and create the first administrator account.
4. Sign in and configure the sender address under **Settings → Email notifications**.
5. Send a test email and confirm delivery before enabling email OTP.
6. Remove or rename `setup.php` after installation.

For a local smoke test, run `php -S 127.0.0.1:8080` from the project directory and open `http://127.0.0.1:8080/setup.php`.

## Upgrade from v1.2.5 or later

1. Back up the complete current HelpDesk directory, including `config.php`, `storage/helpdesk.sqlite`, and `uploads/`.
2. Extract the v1.3.0 package over the existing application files. Preserve the existing `config.php`, SQLite database, and `uploads/` contents.
3. Ensure the web-server account can write to the database storage directory and `uploads/`.
4. Sign in as an administrator. The application applies repeat-safe schema additions and permission migration on its first database connection.
5. Open **Settings → Email notifications**, configure sender and reply-to addresses, then send a test message.
6. Enable email OTP under **Settings → Security** only after confirming that mail delivery works.
7. Review **Users**, **Groups**, and **Roles & permissions** to confirm the migrated direct roles, group roles, and access rules. Existing custom permission grants are retained; built-in roles receive the new granular equivalents.

Do not run `setup.php` on an existing installation. The v1.2.9 release preserves existing ticket records, comments, attachments, and assignments. Its automatic database migration rebuilds the tickets table to allow guest tickets without a user account, then removes the legacy synthetic guest account. Keep a verified backup until the upgraded application has been validated.

## Features carried forward and updated in v1.3.0

- Six-digit email OTP for registered-user login, account registration, and guest ticket submission; OTP is configurable by an administrator.
- Guest email verification happens before the ticket details form is displayed. Codes expire after 10 minutes, allow five attempts, and have a 60-second resend cooldown. Codes are stored as password hashes and challenges are tied to the current session.
- Configurable roles and permissions, multiple group memberships per user, and role inheritance through groups.
- Dashboard ticket filters and status summary charts.
- White-label system name, uploaded company logo, primary color, sender identity, reply-to address, and email footer. Uploaded logos are included in customer-facing ticket emails and OTP emails when configured.
- Software version displayed in the administrator footer and Settings → System information.
- Administrator ability to delete user accounts while preserving tickets, comments, and attachment records. Ticket requester identity is retained as contact details when an account is deleted.
- Date-range ticket reports with team/group filters and HTML5 and PDF export options. Team-scoped users are limited to their associated groups, assigned tickets, and own requests.

## Email delivery

The application uses PHP's native `mail()` function. The host must provide a configured mail transfer agent or platform mail transport. The email log records whether PHP accepted a message for handoff; this does not guarantee final delivery. Configure SPF, DKIM, and DMARC for the sending domain. Email OTP should remain disabled until test messages are received successfully.

## Security and operational notes

- Use HTTPS in production.
- Protect `config.php`, the SQLite database, and uploaded files from direct public access. The supplied `.htaccess` covers Apache; apply equivalent restrictions in Nginx.
- Back up the SQLite database and `uploads/` together. SQLite WAL mode may create `-wal` and `-shm` files; use SQLite's online backup API or stop writes briefly when copying a backup.
- Use strong administrator passwords and restrict server and filesystem access.
- Internal ticket notes are excluded from requester-facing notification content.
- Guest tickets retain verified contact information directly on the ticket and do not create a user account.

## Project structure

```text
app/bootstrap.php       Database, migrations, session, OTP, authorization, mail helpers
public/style.css        Responsive interface and white-label styling
public/app.js           Small client-side UI behavior
schema.sql              Fresh-install database schema
setup.php               Initial installation wizard
index.php               Application routes and views
VERSION                 Installed software version
```

## Open-source licensing

This package does not yet include a license grant. Select and add the project's intended open-source license before publishing or redistributing it. Do not assume that source availability alone grants reuse rights.


## Guest tickets, profiles, and uploads

Guest requesters can either register an account or continue without one. Guest submissions collect name, email, contact phone, company/organization, and optional department. Guest tickets are stored with a NULL user_id and their contact details on the ticket; no synthetic guest account is created. Existing installations are migrated automatically and the legacy Guest Ticket Holder account is removed after its ticket references are converted.

Registered users can update their name, phone, company/organization, and department from My Profile. Administrators can edit the same fields in User Management. Users can update their sign-in email from My Profile; when email OTP is enabled, the new address must be verified before the change is completed. Administrators can also edit user contact fields in User Management.

Uploads are governed by the PHP/server configuration, not an application-defined maximum. Configure PHP's `upload_max_filesize`, `post_max_size` (larger than `upload_max_filesize`), and `max_file_uploads`; also check web-server request-body limits and available disk space. The Settings page displays the active PHP values. The application still checks upload errors and allowed MIME types.


## Roles and permissions in v1.3.0

Permissions are additive. A user receives the combined permissions from all directly assigned roles and all roles assigned to their groups. Direct role assignment is optional; group-based assignment is the recommended day-to-day method. The built-in `admin` role is the protected System Administrator role and is never assignable through a group. It retains the complete permission catalog.

Ticket visibility is separately scoped: `tickets.view_own` allows owned tickets, `tickets.view_group` allows tickets in the user's groups or assigned to them, and `tickets.view_all` allows system-wide visibility. Editing, replies, internal notes, assignment, status changes, and priority changes each have separate permissions. The legacy `tickets.manage` permission remains supported as a team-scoped compatibility grant; it no longer bypasses ticket visibility restrictions.

Administration permissions are separated for users, groups, roles, settings, reports, categories, and statuses. Role assignment requires `roles.manage`; assigning permissions to roles also requires `permissions.manage`. User deletion and account disabling have separate permissions.

## Rich-text email templates

Settings → Email notifications includes the TinyMCE Community editor for the four ticket notification templates. It loads the GPL Community build from jsDelivr (`cdn.jsdelivr.net`); administrators' browsers need access to that CDN to use the visual editor. If the CDN is unavailable, the template fields remain usable as HTML textareas. Template HTML is filtered through an allow-list before it is sent.

Use `{{ticket.url}}` in an email template to insert a clickable **View your support ticket** link. `{ticket.url}` and the legacy `{ticket_url}` placeholder are also supported. The generated link uses the absolute HelpDesk URL. For reverse-proxy, subdirectory, or custom-domain deployments, set `base_url` in `config.php` to the full public application URL (for example, `https://support.example.com/helpdesk/`).

TinyMCE Community is distributed under GPL-2.0. This project still does not include a project-level LICENSE file; choose and add the intended HelpDesk application license before public redistribution.
