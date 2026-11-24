# HelpDesk (Version 1.4.0)

A self-hosted PHP and SQLite help desk application with customer ticket submission, technician workflows, configurable groups and roles, email notifications, and white-label appearance settings.

## Requirements

- PHP 8.1+ (PHP 8.2+ recommended)
- PDO SQLite (`pdo_sqlite`), Fileinfo, and PHP `mail()` support
- PHP `dom` extension (recommended; used to sanitise email-template HTML — a regex fallback is used without it)
- Apache or Nginx with PHP support (Nginx needs the deny rules in *Protecting private files* below)
- Writable application `uploads/` and database storage directory
- HTTPS for production use

## Fresh installation

1. Copy the `helpdesk/` directory to your PHP web host (include hidden files such as `.htaccess`).
2. Make sure the web-server account can write to the `helpdesk/` directory during setup, or pre-create `storage/` and `uploads/` and make those writable.
3. Visit `/setup.php`, confirm the **Public URL**, and create the first administrator account. Setup creates the protected `storage/` and `uploads/` folders if needed and writes `config.php` only after the database has been created successfully, so a failed install can simply be retried.
4. Sign in and configure the sender address under **Settings → Email notifications**.
5. Send a test email and confirm delivery before enabling email OTP.
6. Delete or rename `setup.php`, and tighten permissions on the folder if you loosened them for setup.
7. Open **Settings** and confirm there is no red *database appears to be downloadable* warning (see *Protecting private files*).

For a local smoke test, run `php -S 127.0.0.1:8080` from the project directory and open `http://127.0.0.1:8080/setup.php`. Note that PHP's built-in server ignores `.htaccess`, so the database-exposure warning is expected there.

## Upgrade from v1.2.5 or later (including v1.3.0 → v1.4.0)

1. Back up the complete current HelpDesk directory, including `config.php`, `storage/helpdesk.sqlite`, and `uploads/`.
2. Extract the v1.4.0 package over the existing application files. Preserve the existing `config.php`, SQLite database, and `uploads/` contents. **Make sure hidden files are copied** — v1.4.0 adds `storage/.htaccess` and `app/.htaccess` and updates the root `.htaccess`; some archive tools and FTP clients skip dotfiles.
3. Edit `config.php` and set `base_url` to the full public URL (for example `https://support.example.com/helpdesk`). If HelpDesk runs behind a reverse proxy or CDN that overwrites a client-IP header, also set `trusted_proxy_header` (see *Configuration*). New keys can be copied from `config.example.php`.
4. On Nginx (or any server that ignores `.htaccess`), add the deny rules from *Protecting private files*.
5. Sign in as an administrator. The first request applies a one-time, locked schema migration and records `schema_version` 14; it no longer runs on every request. **Settings → System information** shows the schema version.
6. Open **Settings** and check for warnings: *Public URL not set* and *database appears to be downloadable*. Resolve both.
7. Review **Roles & permissions**. In v1.3.x built-in roles had their default permissions silently re-added on every request. v1.4.0 performs that upgrade step once; from now on removing a permission from a built-in role (other than `admin`) persists.
8. Optional: self-host the email editor (see *Rich-text email templates*).
9. Configure sender and reply-to addresses, then send a test message; enable email OTP under **Settings → Security** only after confirming that mail delivery works.

Do not run `setup.php` on an existing installation. Keep a verified backup until the upgraded application has been validated.

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
- Protect `config.php`, the SQLite database, and uploaded files from direct public access (see *Protecting private files*). Ideally keep the database outside the web root by changing `database` in `config.php`.
- Repeated failed sign-ins temporarily lock the account and the client IP (default: 5 failures per email or 20 per IP in 15 minutes; adjustable under **Settings → Security**). Verification-code emails are limited to 5 per address per hour.
- Non-administrators with user- or role-management permissions cannot modify administrator accounts or accounts holding permissions they lack, cannot assign roles that carry permissions they lack, and cannot grant permissions they do not hold.
- Logout is a CSRF-protected POST request. Responses carry `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy` headers.
- Back up the SQLite database and `uploads/` together. SQLite WAL mode may create `-wal` and `-shm` files; use SQLite's online backup API or stop writes briefly when copying a backup.
- Use strong administrator passwords and restrict server and filesystem access.
- Internal ticket notes are excluded from requester-facing notification content.
- Guest tickets retain verified contact information directly on the ticket and do not create a user account.

## Project structure

```text
app/bootstrap.php       Database, versioned migrations, session, OTP, rate limiting, authorization, mail helpers
storage/                SQLite database (protected by storage/.htaccess)
public/vendor/jodit/    Optional self-hosted Jodit editor files
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

Settings → Email notifications uses the **Jodit** editor (MIT licence) for the four ticket notification templates: a full toolbar (fonts, colours, alignment, lists, links, tables, images by URL, horizontal rules, clean-formatting, HTML source view, full-screen) plus one-click **placeholder buttons** that insert `{customer_name}`, `{ticket_no}`, `{subject}`, `{status}`, `{message}`, `{app_name}` and `{{ticket.url}}` at the cursor.

The editor loads from `public/vendor/jodit/jodit.min.js` and `jodit.min.css` when those files exist, and otherwise from jsDelivr (`cdn.jsdelivr.net`). To self-host (recommended for locked-down networks), download `es2021/jodit.min.js` and `es2021/jodit.min.css` from the `jodit` package (for example `npm pack jodit`, or the jsDelivr file listing for `jodit@4`) and place both in `public/vendor/jodit/`. If neither source is reachable the template fields remain usable as plain HTML textareas.

Notes:

- Template HTML is sanitised with a DOM-based allow-list when it is sent: scripts, forms, iframes, event handlers, unsafe CSS and non-`http(s)/mailto/tel` links are removed. Images must use `http(s)` URLs; embedded `data:` images are removed because most mail clients block them.
- `{{ticket.url}}` inserts a clickable **View your support ticket** link. It also works as a link target: create a link in the editor and enter `{{ticket.url}}` as the address. `{ticket.url}` and the legacy `{ticket_url}` are also supported.
- Links in emails are absolute. Set `base_url` in `config.php` to the full public URL, for example `https://support.example.com/helpdesk`.
- Leave a template blank to use the built-in default.

## Login screen branding

The uploaded system logo (Settings → Branding) and system name now also appear on the sign-in, registration, verification and guest-ticket screens, and the logo is used as the browser tab icon. Without a logo, the first letter of the system name is shown as before.

## Protecting private files

`config.php`, `storage/` (database), `app/`, and `uploads/` (attachments) must never be directly downloadable.

**Apache:** the supplied `.htaccess` files (root, `storage/`, `app/`, `uploads/`) handle this when `AllowOverride` permits them. If your host disables `.htaccess`, add equivalent rules to the virtual host.

**Nginx:** `.htaccess` is ignored, so add (adjusting the `/helpdesk` prefix to your install path, or removing it for a root install):

```nginx
location ~ ^/helpdesk/(storage|app|uploads)/ { deny all; return 404; }
location ~* \.(sqlite|sqlite3|db)(-wal|-shm|\.migrate\.lock)?$ { deny all; return 404; }
location ~ ^/helpdesk/(config\.php|config\.example\.php|schema\.sql|VERSION)$ { deny all; return 404; }
```

Do not block `public/` (CSS, JavaScript and the uploaded logo live in `public/` and `public/uploads/`). After configuring, open **Settings**: HelpDesk checks from your browser whether the database file is reachable and shows a red warning if it is.

## Configuration (`config.php`)

| Key | Purpose |
| --- | --- |
| `app_name` | Initial system name (also editable in Settings). |
| `base_url` | Full public URL, used for every link in emails. Strongly recommended. When blank the request host name is used after validation. |
| `timezone` | PHP timezone for timestamps. |
| `database` | Path to the SQLite file. Move it outside the web root for best protection. |
| `upload_dir` | Attachment storage path. |
| `session_name` | PHP session cookie name. |
| `trusted_proxy_header` | e.g. `HTTP_X_FORWARDED_FOR` or `HTTP_CF_CONNECTING_IP`. Set only when a reverse proxy/CDN you control **overwrites** that header; otherwise leave blank so clients cannot spoof their IP for throttling and audit logs. |

## Open-source components

The email editor, Jodit, is distributed under the MIT licence. This project still does not include a project-level LICENSE file; choose and add the intended HelpDesk application licence before public redistribution.
