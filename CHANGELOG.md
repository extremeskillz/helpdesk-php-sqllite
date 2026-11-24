# Changelog

## 1.4.0
### Security
- Database and private folders are now protected: new `storage/.htaccess` and `app/.htaccess`, and the root `.htaccess` also denies `*.sqlite`, `-wal`, `-shm`, migration lock files, `VERSION` and `*.md`. Settings shows a red warning if the database file is reachable from the browser. Nginx rules are documented.
- Sign-in throttling: failed sign-ins lock an email address (default 5 failures) and a client IP (4× that) for a configurable window (default 15 minutes). Unknown accounts take the same time as wrong passwords. Verification-code emails are limited to 5 per address per hour.
- Privilege escalation fixed: non-administrators can no longer edit or delete administrator accounts (or any account holding permissions they lack), assign roles carrying permissions they lack, or grant permissions they do not hold when editing roles and group roles.
- Email links no longer trust a forged `Host` header: the host is validated, `base_url` is requested during setup, and Settings warns when it is empty. Added optional `trusted_proxy_header` for correct client IPs behind a proxy.
- Email-template HTML is now sanitised with a DOM allow-list (tags, attributes, URL schemes and CSS properties) instead of regular expressions.
- Logout is now a CSRF-protected POST; added `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy` headers; attachment downloads send `nosniff` and a proper UTF-8 `filename*`.

### Fixes
- Setup creates `storage/` and `uploads/`, checks PHP version and `pdo_sqlite`, and writes `config.php` last; a failed install cleans up and can be retried.
- Schema migration is versioned (`schema_version`), locked against concurrent runs, and runs once instead of on every request. Removing permissions from built-in roles is no longer silently undone; the System Administrator role always keeps the full permission catalog.
- Permission checks use one cached query per user instead of several, speeding up pages that list users.
- Fixed "Assignment changed" being logged on every save of an unassigned ticket; fixed dashboard date filters disagreeing with reports around midnight; removed a hard-coded old version string from error logs.
- `schema.sql` now matches a migrated database (ticket history, OTP, rate-limit tables, status `active` flag).

### Changes
- Replaced TinyMCE with **Jodit** (MIT licence) for email templates, with placeholder-insert buttons, dark-theme support, optional self-hosting from `public/vendor/jodit/`, and a plain-textarea fallback.
- The system logo and name now appear on the sign-in, registration, verification and guest-ticket screens, and the logo is the browser tab icon.
- New Settings → Security options for sign-in lockout; Settings → System information shows the schema version and `base_url` status.
- README, INSTALL and CHANGELOG updated, including upgrade steps from v1.3.0.

## 1.3.0
- Reworked authorization around additive direct-user and group role assignments.
- Added a granular permission catalog for ticket actions, users, groups, roles, settings, reports, categories, and statuses.
- Added group-scoped ticket visibility; `tickets.manage` is now team-scoped for compatibility rather than a blanket all-ticket bypass.
- Protected the System Administrator role from group assignment and permission removal; migration preserves users who previously received administrator access through a group.
- Added direct multi-role assignment to user management and permission-specific administration navigation and route checks.
- Added TinyMCE Community rich-text editing for customer-facing email templates, with an HTML fallback when the CDN is unavailable.
- Added clickable `{{ticket.url}}`, `{ticket.url}`, and legacy `{ticket_url}` placeholders. Links are rendered as absolute URLs using the configured application base URL.
- Added HTML allow-list filtering for saved email template content before sending.

## 1.2.9
- Added guest choice to register or continue without an account, with expanded guest contact details.
- Removed the synthetic Guest Ticket Holder account; guest tickets now use a nullable requester user reference. Existing databases are migrated automatically without discarding ticket history.
- Added user profile editing and matching administrator-editable contact fields.
- Removed application-specific upload-size caps; uploads now follow PHP/server limits, which are displayed in System Information and documented.

## 1.2.8
- Fixed signed-in users being unable to open the new-ticket page.
- Added company logo upload in Settings and logo embedding in customer-facing email templates.
- Added administrator user deletion with ticket, comment, and attachment history preservation.
- Added date-range ticket reports with team filtering and HTML5/PDF exports.

## 1.2.7
- Removed the redundant Ticket Overview panel and made dashboard filters collapsible.
- Added registered-email detection for guest submissions.
- Added configurable remembered OTP verification period.
- Added editable email templates and verification/activity timestamp migrations.

## 1.2.6
- Added configurable email OTP for logins, registrations, and guest submissions.
- Added role and group permission foundations, multiple group memberships, dashboard filters, white-label settings, and software version display.

## 1.2.5
- Previous validated baseline release.
